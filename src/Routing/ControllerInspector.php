<?php

namespace Nova\Routing;

use Nova\Helpers\Inflector;
use Nova\Support\Str;

use ReflectionClass;
use ReflectionMethod;


class ControllerInspector
{
    /**
     * An array of HTTP verbs.
     *
     * @var array
     */
    protected $verbs = array('any', 'get', 'post', 'put', 'patch', 'delete', 'head', 'options');


    /**
     * Get the routable methods for a controller.
     *
     * @param  string  $controller
     * @param  string  $prefix
     * @return array
     */
    public function getRoutable($controller, $prefix)
    {
        $routable = array();

        $reflection = new ReflectionClass($controller);

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $name = $method->name;

            if (! $this->isRoutable($method)) {
                continue;
            }

            $data = $this->getMethodData($method, $prefix);

            $routable[$name][] = $data;

            if ($data['plain'] == $prefix .'/index') {
                $routable[$name][] = $this->getIndexData($data, $prefix);
            }
        }

        return $routable;
    }

    /**
     * Determine if the given controller method is routable.
     *
     * @param  \ReflectionMethod  $method
     * @return bool
     */
    public function isRoutable(ReflectionMethod $method)
    {
        if ($method->class == 'Nova\Routing\Controller') {
            return false;
        }

        $path = str_replace('\\', '/', $method->class);

        if (preg_match('#^.+/Controllers/BaseController$#', $path) === 1) {
            return false;
        }

        return Str::startsWith($method->name, $this->verbs);
    }

    /**
     * Get the method data for a given method.
     *
     * @param  \ReflectionMethod  $method
     * @param  string  $prefix
     * @return array
     */
    public function getMethodData(ReflectionMethod $method, $prefix)
    {
        $verb = $this->getVerb($name = $method->name);

        $uri = $this->addUriWildcards($plain = $this->getPlainUri($name, $prefix));

        return compact('verb', 'plain', 'uri');
    }

    /**
     * Get the routable data for an index method.
     *
     * @param  array   $data
     * @param  string  $prefix
     * @return array
     */
    protected function getIndexData($data, $prefix)
    {
        return array('verb' => $data['verb'], 'plain' => $prefix, 'uri' => $prefix);
    }

    /**
     * Extract the verb from a controller action.
     *
     * @param  string  $name
     * @return string
     */
    public function getVerb($name)
    {
        return head(explode('_', Inflector::tableize($name)));
    }

    /**
     * Determine the URI from the given method name.
     *
     * @param  string  $name
     * @param  string  $prefix
     * @return string
     */
    public function getPlainUri($name, $prefix)
    {
        return $prefix .'/' .implode('-', array_slice(explode('_', Inflector::tableize($name)), 1));
    }

    /**
     * Add wildcards to the given URI.
     *
     * @param  string  $uri
     * @return string
     */
    public function addUriWildcards($uri)
    {
        return $uri .'/{one?}/{two?}/{three?}/{four?}/{five?}/{six?}/{seven?}';
    }

}
