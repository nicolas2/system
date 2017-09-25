<?php
/**
 * Route - manage a route to an HTTP request and an assigned callback function.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace Nova\Routing;

use Nova\Container\Container;
use Nova\Http\Request;
use Nova\Http\Exception\HttpResponseException;
use Nova\Routing\Matching\HostValidator;
use Nova\Routing\Matching\MethodValidator;
use Nova\Routing\Matching\SchemeValidator;
use Nova\Routing\Matching\UriValidator;
use Nova\Routing\ControllerDispatcher;
use Nova\Routing\RouteCompiler;
use Nova\Routing\RouteDependencyResolverTrait;
use Nova\Support\Arr;
use Nova\Support\Str;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Closure;
use BadMethodCallException;
use LogicException;
use ReflectionFunction;


/**
 * The Route class is responsible for routing an HTTP request to an assigned Callback function.
 */
class Route
{
    use RouteDependencyResolverTrait;

    /**
     * The URI pattern the Route responds to.
     *
     * @var string
     */
    protected $uri;

    /**
     * Supported HTTP methods.
     *
     * @var array
     */
    protected $methods = array();

    /**
     * The route action array.
     *
     * @var array
     */
    protected $action = array();

    /**
     * The default values for the Route.
     *
     * @var array
     */
    protected $defaults = array();

    /**
     * The regular expression requirements.
     *
     * @var array
     */
    protected $wheres = array();

    /**
     * The matched Route parameters.
     *
     * @var array
     */
    protected $parameters;

    /**
     * The parameter names for the route.
     *
     * @var array|null
     */
    protected $parameterNames;

    /**
     * The compiled version of the Route.
     *
     * @var \Symfony\Component\Routing\CompiledRoute
     */
    protected $compiled = null;

    /**
     * The container instance used by the route.
     *
     * @var \Nova\Container\Container
     */
    protected $container;

    /**
     * The Router instance used by the route.
     *
     * @var \Nova\Routing\Router  $router
     */
    protected $router;

    /**
     * The validators used by the routes.
     *
     * @var array
     */
    protected static $validators;


    /**
     * Constructor.
     *
     * @param string|array $methods HTTP methods
     * @param string $uri URL pattern
     * @param string|array|callable $action Callback function or options
     */
    public function __construct($methods, $uri, $action)
    {
        $this->uri = $uri;

        $this->methods = (array) $methods;

        $this->action = $this->parseAction($action);

        if (in_array('GET', $this->methods) && ! in_array('HEAD', $this->methods)) {
            $this->methods[] = 'HEAD';
        }

        if (isset($this->action['prefix'])) {
            $this->prefix($this->action['prefix']);
        }
    }

    /**
     * Run the route action and return the response.
     *
     * @param  \Nova\Http\Request  $request
     * @return mixed
     */
    public function run(Request $request)
    {
        if (! isset($this->container)) {
            $this->container = new Container();
        }

        try {
            if (! $this->isControllerAction()) {
                return $this->runCallable($request);
            }

            return $this->runController($request);
        }
        catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Checks whether the route's action is a controller.
     *
     * @return bool
     */
    protected function isControllerAction()
    {
        return is_string($this->action['uses']);
    }

    /**
     * Run the route action and return the response.
     *
     * @param  \Nova\Http\Request  $request
     * @return mixed
     */
    protected function runCallable(Request $request)
    {
        $callable = $this->action['uses'];

        $parameters = $this->resolveMethodDependencies(
            $this->parametersWithoutNulls(), new ReflectionFunction($callable)
        );

        return call_user_func_array($callable, $parameters);
    }

    /**
     * Run the route action and return the response.
     *
     * @param  \Nova\Http\Request  $request
     *
     * @return mixed
     */
    protected function runController(Request $request)
    {
        list($controller, $method) = explode('@', $this->action['uses']);

        return $this->controllerDispatcher()->dispatch(
            $this, $request, $this->container->make($controller), $method
        );
    }

    /**
     * Get the dispatcher for the route's controller.
     *
     * @return \Nova\Routing\ControllerDispatcher
     */
    public function controllerDispatcher()
    {
        if ($this->container->bound('routing.controller.dispatcher')) {
            return $this->container['routing.controller.dispatcher'];
        }

        return new ControllerDispatcher($this->router, $this->container);
    }

    /**
     * Checks if a Request matches the Route pattern.
     *
     * @param \Http\Request $request The dispatched Request instance
     * @param bool $includingMethod Wheter or not is matched the HTTP Method
     * @return bool Match status
     */
    public function matches(Request $request, $includingMethod = true)
    {
        $this->compileRoute();

        foreach ($this->getValidators() as $validator) {
            if (! $includingMethod && ($validator instanceof MethodValidator)) {
                continue;
            }

            if (! $validator->matches($this, $request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compile the Route pattern for matching and return it.
     *
     * @return string
     * @throws \LogicException
     */
    public function compileRoute()
    {
        if (! isset($this->compiled)) {
            return $this->compiled = with(new RouteCompiler($this))->compile();
        }

        return $this->compiled;
    }

    /**
     * Parse the Route Action into a standard array.
     *
     * @param  \Closure|array  $action
     * @return array
     */
    protected function parseAction($action)
    {
        if (is_string($action) || is_callable($action)) {
            // A string or Closure is given as Action.
            return array('uses' => $action);
        } else if (! isset($action['uses'])) {
            // Find the Closure in the Action array.
            $action['uses'] = $this->findClosure($action);
        }

        return $action;
    }

    /**
     * Find the Closure in an action array.
     *
     * @param  array  $action
     * @return \Closure
     */
    protected function findClosure(array $action)
    {
        return Arr::first($action, function ($key, $value)
        {
            return is_callable($value);
        });
    }

    /**
     * Get the route validators for the instance.
     *
     * @return array
     */
    public static function getValidators()
    {
        if (isset(static::$validators)) {
            return static::$validators;
        }

        return static::$validators = array(
            new UriValidator(), new MethodValidator(),
            new SchemeValidator(), new HostValidator(),
        );
    }

    /**
     * Add before filters to the route.
     *
     * @param  string  $filters
     * @return $this
     */
    public function before($filters)
    {
        return $this->addFilters('before', $filters);
    }

    /**
     * Add after filters to the route.
     *
     * @param  string  $filters
     * @return $this
     */
    public function after($filters)
    {
        return $this->addFilters('after', $filters);
    }

    /**
     * Add the given Filters to the route by type.
     *
     * @param  string  $type
     * @param  string  $filters
     * @return \Nova\Routing\Route
     */
    protected function addFilters($type, $filters)
    {
        if (isset($this->action[$type])) {
            $this->action[$type] .= '|' .$filters;
        } else {
            $this->action[$type] = $filters;
        }

        return $this;
    }

    /**
     * Get the "before" filters for the route.
     *
     * @return array
     */
    public function beforeFilters()
    {
        if (! isset($this->action['before'])) {
            return array();
        }

        $filters = $this->action['before'];

        return $this->parseFilters($filters);
    }

    /**
     * Get the "after" filters for the route.
     *
     * @return array
     */
    public function afterFilters()
    {
        if (! isset($this->action['after'])) {
            return array();
        }

        $filters = $this->action['after'];

        return $this->parseFilters($filters);
    }

    /**
     * Parse the given filter string.
     *
     * @param  string  $filters
     * @return array
     */
    protected function parseFilters($filters)
    {
        return Arr::build(static::explodeFilters($filters), function ($key, $value)
        {
            return static::parseFilter($value);
        });
    }

    /**
     * Turn the filters into an array if they aren't already.
     *
     * @param  array|string  $filters
     * @return array
     */
    protected static function explodeFilters($filters)
    {
        if (! is_array($filters)) {
            return explode('|', $filters);
        }

        $results = array();

        foreach ($filters as $filter) {
            $results = array_merge($results, explode('|', $filter));
        }

        return $results;
    }

    /**
     * Parse the given filter into name and parameters.
     *
     * @param  string  $filter
     * @return array
     */
    public static function parseFilter($filter)
    {
        if (! Str::contains($filter, ':')) {
            return array($filter, array());
        }

        list($name, $parameters) = explode(':', $filter, 2);

        return array($name, explode(',', $parameters));
    }

    /**
     * Get a given parameter from the route.
     *
     * @param  string  $name
     * @param  mixed   $default
     * @return string
     */
    public function getParameter($name, $default = null)
    {
        return $this->parameter($name, $default);
    }

    /**
     * Get a given parameter from the route.
     *
     * @param  string  $name
     * @param  mixed   $default
     * @return string
     */
    public function parameter($name, $default = null)
    {
        return Arr::get($this->parameters(), $name, $default);
    }

    /**
     * Set a parameter to the given value.
     *
     * @param  string  $name
     * @param  mixed   $value
     * @return void
     */
    public function setParameter($name, $value)
    {
        $this->parameters();

        $this->parameters[$name] = $value;
    }

    /**
     * Unset a parameter on the route if it is set.
     *
     * @param  string  $name
     * @return void
     */
    public function forgetParameter($name)
    {
        $this->parameters();

        unset($this->parameters[$name]);
    }

    /**
     * Get the key / value list of parameters for the route.
     *
     * @return array
     *
     * @throws \LogicException
     */
    public function parameters()
    {
        if (! isset($this->parameters)) {
            throw new LogicException("Route is not bound.");
        }

        return array_map(function ($value)
        {
            return is_string($value) ? rawurldecode($value) : $value;

        }, $this->parameters);
    }

    /**
     * Get the key / value list of parameters without null values.
     *
     * @return array
     */
    public function parametersWithoutNulls()
    {
        return array_filter($this->parameters(), function ($value)
        {
            return ! is_null($value);
        });
    }

    /**
     * Get all of the parameter names for the route.
     *
     * @return array
     */
    public function parameterNames()
    {
        if (isset($this->parameterNames)) {
            return $this->parameterNames;
        }

        return $this->parameterNames = $this->compileParameterNames();
    }

    /**
     * Get the parameter names for the route.
     *
     * @return array
     */
    protected function compileParameterNames()
    {
        preg_match_all('/\{(.*?)\}/', $this->domain() .$this->uri, $matches);

        return array_map(function ($value)
        {
            return trim($value, '?');

        }, $matches[1]);
    }

    /**
     * Bind the Route to a given Request for execution.
     *
     * @param  \Nova\Http\Request  $request
     * @return $this
     */
    public function bind(Request $request)
    {
        $this->compileRoute();

        $this->bindParameters($request);

        return $this;
    }

    /**
     * Extract the parameter list from the request.
     *
     * @param  \Nova\Http\Request  $request
     * @return array
     */
    public function bindParameters(Request $request)
    {
        $parameters = $this->matchToKeys(
            array_slice($this->bindPathParameters($request), 1)
        );

        if (! is_null($this->compiled->getHostRegex())) {
            $parameters = $this->bindHostParameters($request, $parameters);
        }

        return $this->parameters = $this->replaceDefaults($parameters);
    }

    /**
     * Get the parameter matches for the path portion of the URI.
     *
     * @param  \Nova\Http\Request  $request
     * @return array
     */
    protected function bindPathParameters(Request $request)
    {
        preg_match($this->compiled->getRegex(), '/' .$request->decodedPath(), $matches);

        return $matches;
    }

    /**
     * Extract the parameter list from the host part of the request.
     *
     * @param  \Nova\Http\Request  $request
     * @param  array  $parameters
     * @return array
     */
    protected function bindHostParameters(Request $request, $parameters)
    {
        preg_match($this->compiled->getHostRegex(), $request->getHost(), $matches);

        return array_merge($this->matchToKeys(array_slice($matches, 1)), $parameters);
    }

    /**
     * Combine a set of parameter matches with the route's keys.
     *
     * @param  array  $matches
     * @return array
     */
    protected function matchToKeys(array $matches)
    {
        $parameterNames = $this->parameterNames();

        if (count($parameterNames) == 0) {
            return array();
        }

        $parameters = array_intersect_key($matches, array_flip($parameterNames));

        return array_filter($parameters, function ($value)
        {
            return is_string($value) && (strlen($value) > 0);
        });
    }

    /**
     * Replace null parameters with their defaults.
     *
     * @param  array  $parameters
     * @return array
     */
    protected function replaceDefaults(array $parameters)
    {
        foreach ($parameters as $key => &$value) {
            $value = isset($value) ? $value : Arr::get($this->defaults, $key);
        }

        return $parameters;
    }

    /**
     * Set a default value for the route.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function defaults($key, $value)
    {
        $this->defaults[$key] = $value;

        return $this;
    }

    /**
     * Get the regular expression requirements on the route.
     *
     * @return array
     */
    public function patterns()
    {
        return $this->wheres;
    }

    /**
     * Set a regular expression requirement on the route.
     *
     * @param  array|string  $name
     * @param  string  $expression
     * @return $this
     */
    public function where($name, $expression = null)
    {
        foreach ($this->parseWhere($name, $expression) as $name => $expression) {
            $this->wheres[$name] = $expression;
        }

        return $this;
    }

    /**
     * Parse arguments to the where method into an array.
     *
     * @param  array|string  $name
     * @param  string  $expression
     * @return array
     */
    protected function parseWhere($name, $expression)
    {
        return is_array($name) ? $name : array($name => $expression);
    }

    /**
     * Add a prefix to the route URI.
     *
     * @param  string  $prefix
     * @return \Nova\Routing\Route
     */
    public function prefix($prefix)
    {
        $this->uri = trim($prefix, '/') .'/' .trim($this->uri, '/');

        return $this;
    }

    /**
     * Get the URI associated with the route.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->uri();
    }

    /**
     * Get the URI associated with the route.
     *
     * @return string
     */
    public function uri()
    {
        return $this->uri;
    }

    /**
     * @return array
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * @return array
     */
    public function methods()
    {
        return $this->methods;
    }

    /**
     * Determine if the route only responds to HTTP requests.
     *
     * @return bool
     */
    public function httpOnly()
    {
        return in_array('http', $this->action, true);
    }

    /**
     * Determine if the route only responds to HTTPS requests.
     *
     * @return bool
     */
    public function httpsOnly()
    {
        return $this->secure();
    }

    /**
     * Determine if the route only responds to HTTPS requests.
     *
     * @return bool
     */
    public function secure()
    {
        return in_array('https', $this->action, true);
    }

    /**
     * Get the domain defined for the Route.
     *
     * @return string|null
     */
    public function domain()
    {
        if (isset($this->action['domain'])) {
            return $this->action['domain'];
        }
    }

    /**
     * @return string|null
     */
    public function getUri()
    {
        return $this->uri();
    }

    /**
     * Set the URI that the route responds to.
     *
     * @param  string  $uri
     * @return \Nova\Routing\Route
     */
    public function setUri($uri)
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->parameters();
    }

    /**
     * Get the prefix of the route instance.
     *
     * @return string
     */
    public function getPrefix()
    {
        if (isset($this->action['prefix'])) {
            return $this->action['prefix'];
        }
    }

    /**
     * Get the name of the route instance.
     *
     * @return string
     */
    public function getName()
    {
        if (isset($this->action['as'])) {
            return $this->action['as'];
        }
    }

    /**
     * Get the action name for the route.
     *
     * @return string
     */
    public function getActionName()
    {
        return isset($this->action['controller']) ? $this->action['controller'] : 'Closure';
    }

    /**
     * Return the Action array.
     *
     * @return array
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Set the Action array for the Route.
     *
     * @param  array  $action
     * @return \Nova\Routing\Route
     */
    public function setAction(array $action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get the compiled version of the Route.
     *
     * @return \Symfony\Component\Routing\CompiledRoute
     */
    public function getCompiled()
    {
        return $this->compiled;
    }

    /**
     * Set the container instance on the route.
     *
     * @param  \Nova\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Set the router instance on the route.
     *
     * @param  \Nova\Routing\Router  $router
     * @return $this
     */
    public function setRouter(Router $router)
    {
        $this->router = $router;

        return $this;
    }

    /**
     * Dynamically access route parameters.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->parameter($key);
    }
}
