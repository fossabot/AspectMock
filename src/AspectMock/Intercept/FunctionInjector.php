<?php
namespace AspectMock\Intercept;

/**
 * Class FunctionInjector
 * @package AspectMock\Intercept
 */
class FunctionInjector
{
    /**
     * @var string
     */
    protected $template = <<<EOF
<?php
namespace {{ns}};
if (!function_exists('{{ns}}\{{func}}')) {
    function {{func}}() {
        if ((\$__am_res = __amock_before_func('{{ns}}','{{func}}', func_get_args())) !== __AM_CONTINUE__) {
            return \$__am_res;
        }
        return call_user_func_array('{{func}}', func_get_args());
    }
}
EOF;

    /**
     * @var string
     */
    protected $templateByRefOptional = <<<EOF
<?php
namespace {{ns}};
if (!function_exists('{{ns}}\{{func}}')) {
    function {{func}}({{arguments}}) {
         \$args = [];
         switch(count(func_get_args())) {
{{code}}         }
         if ((\$__am_res = __amock_before_func('{{ns}}','{{func}}', \$args)) !== __AM_CONTINUE__) {
             return \$__am_res;
         }
         return call_user_func_array('{{func}}', \$args);
    }
}
EOF;

    /**
     * @var
     */
    protected $namespace;

    /**
     * @var
     */
    protected $function;
    /**
     * @var
     */
    protected $fileName;

    /**
     * FunctionInjector constructor.
     * @param $namespace
     * @param $function
     * @throws \ReflectionException
     */
    public function __construct($namespace, $function)
    {
        $this->namespace = $namespace;
        $this->function = $function;
        $this->placeOptionalAndReferenceFunction($namespace, $function);
        $this->place('ns', $this->namespace);
        $this->place('func', $this->function);
    }

    /**
     * @param \ReflectionParameter $parameter
     * @param $internal
     * @return string
     * @throws \Exception
     */
    public function getParameterDeclaration(\ReflectionParameter $parameter, $internal): string
    {
        $text = (string)$parameter;
        if (preg_match('@Parameter\s#[0-9]+\s\[\s<(required|optional)>(.*)(\sor NULL)(.*)\s\]@', $text, $match)) {
            $text = $match[2].$match[4];
        } elseif (preg_match('@Parameter\s#[0-9]+\s\[\s<(required|optional)>\s(.*)\s\]@', $text, $match)) {
            $text = $match[2];
        } else {
            throw new \Exception('reflection api changed. adjust code.');
        }
        if ($internal && $parameter->isOptional()) {
            $text .= '=NULL';
        }
        return $text;
    }

    /**
     * @param $namespace
     * @param $function
     * @throws \ReflectionException
     */
    public function placeOptionalAndReferenceFunction($namespace, $function)
    {
        $reflect = new \ReflectionFunction($function);
        $parameters = [];
        $byRef = false;
        $optionals = false;
        $names = [];
        $internal = $reflect->isInternal();
        foreach ($reflect->getParameters() as $parameter) {
            $name = '$'.$parameter->getName();
            $newName = '$p'.$parameter->getPosition();
            $declaration = str_replace($name, $newName, $this->getParameterDeclaration($parameter, $internal));
            $name = $newName;
            if (!$optionals && $parameter->isOptional()) {
                $optionals = true;
            }
            if ($parameter->isPassedByReference()) {
                $name = '&'.$name;
                $byRef = true;
            }
            $names[] = $name;
            $parameters[$newName] = $declaration;
        }
        if ($byRef) {
            $this->template = $this->templateByRefOptional;
            $this->place('arguments', implode(', ', $parameters));
            $code = '';
            for ($i = count($parameters); $i > 0; $i--) {
                $code .= "             case {$i}: \$args = [" . implode(', ', $names) . "]; break;\n";
                array_pop($names);
            }
            $this->place('code', $code);
        }
    }

    /**
     *
     */
    public function save()
    {
        $this->fileName = tempnam(sys_get_temp_dir(), $this->function);
        file_put_contents($this->fileName, $this->template);
    }

    /**
     *
     */
    public function inject()
    {
        require_once $this->fileName;
    }

    /**
     * @return mixed
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * @return string
     */
    public function getPHP()
    {
        return $this->template;
    }

    /**
     * @param $var
     * @param $value
     */
    protected function place($var, $value)
    {
        $this->template = str_replace("{{{$var}}}", $value, $this->template);
    }
} 
