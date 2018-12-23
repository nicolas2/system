<?php

namespace Nova\Auth\Access;

use Nova\Auth\Access\GateInterface;
use Nova\Auth\Access\HandlesAuthorizationTrait;
use Nova\Auth\Access\Response;
use Nova\Auth\Access\AuthorizationException;
use Nova\Auth\UserInterface as User;
use Nova\Container\Container;
use Nova\Support\Str;

use InvalidArgumentException;


class Gate implements GateInterface
{
    use HandlesAuthorizationTrait;

    /**
     * The container instance.
     *
     * @var \Nova\Container\Container
     */
    protected $container;

    /**
     * The user resolver callable.
     *
     * @var callable
     */
    protected $userResolver;

    /**
     * All of the defined abilities.
     *
     * @var array
     */
    protected $abilities = array();

    /**
     * All of the defined policies.
     *
     * @var array
     */
    protected $policies = array();

    /**
     * All of the registered before callbacks.
     *
     * @var array
     */
    protected $beforeCallbacks = array();

    /**
     * All of the registered after callbacks.
     *
     * @var array
     */
    protected $afterCallbacks = array();


    /**
     * Create a new gate instance.
     *
     * @param  \Nova\Container\Container  $container
     * @param  callable  $userResolver
     * @param  array  $abilities
     * @param  array  $policies
     * @param  array  $beforeCallbacks
     * @param  array  $afterCallbacks
     * @return void
     */
    public function __construct(Container $container,
                                callable $userResolver,
                                array $abilities = array(),
                                array $policies = array(),
                                array $beforeCallbacks = array(),
                                array $afterCallbacks = array())
    {
        $this->policies  = $policies;
        $this->container = $container;
        $this->abilities = $abilities;

        $this->userResolver = $userResolver;

        $this->afterCallbacks  = $afterCallbacks;
        $this->beforeCallbacks = $beforeCallbacks;
    }

    /**
     * Determine if a given ability has been defined.
     *
     * @param  string  $ability
     * @return bool
     */
    public function has($ability)
    {
        return isset($this->abilities[$ability]);
    }

    /**
     * Define a new ability.
     *
     * @param  string  $ability
     * @param  callable|string  $callback
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function define($ability, $callback)
    {
        if (is_callable($callback)) {
            $this->abilities[$ability] = $callback;
        } elseif (is_string($callback) && Str::contains($callback, '@')) {
            $this->abilities[$ability] = $this->buildAbilityCallback($callback);
        } else {
            throw new InvalidArgumentException("Callback must be a callable or a 'Class@method' string.");
        }

        return $this;
    }

    /**
     * Create the ability callback for a callback string.
     *
     * @param  string  $callback
     * @return \Closure
     */
    protected function buildAbilityCallback($callback)
    {
        return function () use ($callback)
        {
            list ($class, $method) = explode('@', $callback);

            $instance = $this->resolvePolicy($class);

            return call_user_func_array(array($instance, $method), func_get_args());
        };
    }

    /**
     * Define a policy class for a given class type.
     *
     * @param  string  $class
     * @param  string  $policy
     * @return $this
     */
    public function policy($class, $policy)
    {
        $this->policies[$class] = $policy;

        return $this;
    }

    /**
     * Register a callback to run before all Gate checks.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function before(callable $callback)
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to run after all Gate checks.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function after(callable $callback)
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Determine if the given ability should be granted for the current user.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function allows($ability, $arguments = array())
    {
        if (! is_array($arguments)) {
            $arguments = array_slice(func_get_args(), 1);
        }

        return $this->check($ability, $arguments);
    }

    /**
     * Determine if the given ability should be denied for the current user.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function denies($ability, $arguments = array())
    {
        if (! is_array($arguments)) {
            $arguments = array_slice(func_get_args(), 1);
        }

        return ! $this->check($ability, $arguments);
    }

    /**
     * Determine if the given ability should be granted for the current user.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function check($ability, $arguments = array())
    {
        if (! is_array($arguments)) {
            $arguments = array_slice(func_get_args(), 1);
        }

        try {
            $result = $this->raw($ability, $arguments);
        }
        catch (AuthorizationException $e) {
            return false;
        }

        return (bool) $result;
    }

    /**
     * Determine if the given ability should be granted for the current user.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return \Nova\Auth\Access\Response
     *
     * @throws \Nova\Auth\Access\AuthorizationException
     */
    public function authorize($ability, $arguments = array())
    {
        if (! is_array($arguments)) {
            $arguments = array_slice(func_get_args(), 1);
        }

        $result = $this->raw($ability, $arguments);

        if ($result instanceof Response) {
            return $result;
        }

        return $result ? $this->allow() : $this->deny();
    }

    /**
     * Get the raw result for the given ability for the current user.
     *
     * @param  string  $ability
     * @param  array  $arguments
     * @return mixed
     */
    protected function raw($ability, array $arguments)
    {
        if (is_null($user = $this->resolveUser())) {
            return false;
        }

        $result = $this->callBeforeCallbacks($user, $ability, $arguments);

        if (is_null($result)) {
            $result = $this->callAuthCallback($user, $ability, $arguments);
        }

        $this->callAfterCallbacks($user, $ability, $arguments, $result);

        return $result;
    }

    /**
     * Resolve and call the appropriate authorization callback.
     *
     * @param  \Nova\Auth\UserInterface  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @return bool
     */
    protected function callAuthCallback(User $user, $ability, array $arguments)
    {
        $callback = $this->resolveAuthCallback($user, $ability, $arguments);

        return call_user_func_array($callback, array_merge(array($user), $arguments));
    }

    /**
     * Call all of the before callbacks and return if a result is given.
     *
     * @param  \Nova\Auth\UserInterface  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @return bool|null
     */
    protected function callBeforeCallbacks(User $user, $ability, array $arguments)
    {
        $arguments = array_merge(array($user, $ability), $arguments);

        foreach ($this->beforeCallbacks as $callback) {
            if (! is_null($result = call_user_func_array($callback, $arguments))) {
                return $result;
            }
        }
    }

    /**
     * Call all of the after callbacks with check result.
     *
     * @param  \Nova\Auth\UserInterface  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @param  bool  $result
     * @return void
     */
    protected function callAfterCallbacks(User $user, $ability, array $arguments, $result)
    {
        $arguments = array_merge(array($user, $ability, $result), $arguments);

        foreach ($this->afterCallbacks as $callback) {
            call_user_func_array($callback, $arguments);
        }
    }

    /**
     * Resolve the callable for the given ability and arguments.
     *
     * @param  \Nova\Auth\UserInterface  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @return callable
     */
    protected function resolveAuthCallback(User $user, $ability, array $arguments)
    {
        if ($this->firstArgumentCorrespondsToPolicy($arguments)) {
            return $this->resolvePolicyCallback($user, $ability, $arguments);
        }

        //
        else if (isset($this->abilities[$ability])) {
            return $this->abilities[$ability];
        }

        return function ()
        {
            return false;
        };
    }

    /**
     * Determine if the first argument in the array corresponds to a policy.
     *
     * @param  array  $arguments
     * @return bool
     */
    protected function firstArgumentCorrespondsToPolicy(array $arguments)
    {
        if (! isset($arguments[0])) {
            return false;
        }

        $argument = $arguments[0];

        if (is_object($argument)) {
            $class = get_class($argument);

            return isset($this->policies[$class]);
        }

        return is_string($argument) && isset($this->policies[$argument]);
    }

    /**
     * Resolve the callback for a policy check.
     *
     * @param  \Nova\Auth\UserInterface  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @return callable
     */
    protected function resolvePolicyCallback(User $user, $ability, array $arguments)
    {
        return function () use ($user, $ability, $arguments)
        {
            $class = head($arguments);

            if (method_exists($instance = $this->getPolicyFor($class), 'before')) {
                $parameters = array_merge(array($user, $ability), $arguments);

                if (! is_null($result = call_user_func_array(array($instance, 'before'), $parameters))) {
                    return $result;
                }
            }

            if (! method_exists($instance, $method = Str::camel($ability))) {
                return false;
            }

            return call_user_func_array(array($instance, $method), array_merge(array($user), $arguments));
        };
    }

    /**
     * Get a policy instance for a given class.
     *
     * @param  object|string  $class
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function getPolicyFor($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (! isset($this->policies[$class])) {
            throw new InvalidArgumentException("Policy not defined for [{$class}].");
        }

        return $this->resolvePolicy($this->policies[$class]);
    }

    /**
     * Build a policy class instance of the given type.
     *
     * @param  object|string  $class
     * @return mixed
     */
    public function resolvePolicy($class)
    {
        return $this->container->make($class);
    }

    /**
     * Get a guard instance for the given user.
     *
     * @param  \Nova\Auth\UserInterface|mixed  $user
     * @return static
     */
    public function forUser($user)
    {
        $callback = function () use ($user)
        {
            return $user;
        };

        return new static(
            $this->container, $callback, $this->abilities,
            $this->policies, $this->beforeCallbacks, $this->afterCallbacks
        );
    }

    /**
     * Resolve the user from the user resolver.
     *
     * @return mixed
     */
    protected function resolveUser()
    {
        return call_user_func($this->userResolver);
    }
}
