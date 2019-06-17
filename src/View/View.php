<?php
/**
 * View
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 4.0
 */

namespace Nova\View;

use Nova\Support\Contracts\ArrayableInterface as Arrayable;
use Nova\Support\Contracts\RenderableInterface as Renderable;
use Nova\Support\Contracts\MessageProviderInterface as MessageProvider;
use Nova\Support\MessageBag;
use Nova\Support\Arr;
use Nova\Support\Str;
use Nova\View\Engines\EngineInterface;
use Nova\View\Factory;

use ArrayAccess;
use Closure;
use Exception;


/**
 * View class to load template and views files.
 */
class View implements ArrayAccess, Renderable
{
    /**
     * The View Factory instance.
     *
     * @var \Nova\View\Factory
     */
    protected $factory;

    /**
     * The View Engine instance.
     *
     * @var \Nova\View\Engines\EngineInterface
     */
    protected $engine;

    /**
     * @var string The given View name.
     */
    protected $view = null;

    /**
     * @var string The path to the View file on disk.
     */
    protected $path = null;

    /**
     * @var array Array of local data.
     */
    protected $data = array();


    /**
     * Constructor
     * @param mixed $path
     * @param array $data
     */
    public function __construct(Factory $factory, EngineInterface $engine, $view, $path, $data = array())
    {
        $this->factory = $factory;
        $this->engine  = $engine;

        //
        $this->view = $view;
        $this->path = $path;

        $this->data = ($data instanceof Arrayable) ? $data->toArray() : (array) $data;
    }

    /**
     * Get the string contents of the View.
     *
     * @param  \Closure  $callback
     * @return string
     */
    public function render(Closure $callback = null)
    {
        try {
            $contents = $this->renderContents();

            $response = isset($callback) ? $callback($this, $contents) : null;

            // Once we have the contents of the view, we will flush the sections if we are
            // done rendering all views so that there is nothing left hanging over when
            // another view gets rendered in the future by the application developer.
            $this->factory->flushSectionsIfDoneRendering();

            return $response ?: $contents;
        }
        catch (Exception $e) {
            $this->factory->flushSections();

            throw $e;
        }
    }

    /**
     * Render the View and return the result.
     *
     * @return string
     */
    public function renderContents()
    {
        // We will keep track of the amount of views being rendered so we can flush
        // the section after the complete rendering operation is done. This will
        // clear out the sections for any separate views that may be rendered.
        $this->factory->incrementRender();

        $this->factory->callComposer($this);

        $contents = $this->getContents();

        // Once we've finished rendering the view, we'll decrement the render count
        // so that each sections get flushed out next time a view is created and
        // no old sections are staying around in the memory of an environment.
        $this->factory->decrementRender();

        return $contents;
    }

    /**
     * Get the sections of the rendered view.
     *
     * @return array
     */
    public function renderSections()
    {
        return $this->render(function ($view)
        {
            return $this->factory->getSections();
        });
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @return string
     */
    protected function getContents()
    {
        return $this->engine->get($this->path, $this->gatherData());
    }

    /**
     * Return all variables stored on local and shared data.
     *
     * @return array
     */
    public function gatherData()
    {
        return array_map(function ($value)
        {
            return ($value instanceof Renderable) ? $value->render() : $value;

        }, array_merge($this->factory->getShared(), $this->data));
    }

    /**
     * Add a view instance to the view data.
     *
     * <code>
     *     // Add a View instance to a View's data
     *     $view = View::make('foo')->nest('footer', 'Partials/Footer');
     *
     *     // Equivalent functionality using the "with" method
     *     $view = View::make('foo')->with('footer', View::make('Partials/Footer'));
     * </code>
     *
     * @param  string  $key
     * @param  string  $view
     * @param  array   $data
     * @return View
     */
    public function nest($key, $view, array $data = array())
    {
        // The nested View instance inherits the parent Data if none is given.
        if (empty($data)) {
            $data = $this->getData();
        }

        return $this->with($key, $this->factory->make($view, $data));
    }

    /**
     * Add a key / value pair to the view data.
     *
     * Bound data will be available to the view as variables.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return View
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Add validation errors to the view.
     *
     * @param  \Nova\Support\Contracts\MessageProviderInterface|array  $provider
     * @return \Nova\View\View
     */
    public function withErrors($provider)
    {
        if ($provider instanceof MessageProvider) {
            $this->with('errors', $provider->getMessageBag());
        } else {
            $this->with('errors', new MessageBag((array) $provider));
        }

        return $this;
    }

    /**
     * Add a key / value pair to the shared view data.
     *
     * Shared view data is accessible to every view created by the application.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return View
     */
    public function shares($key, $value)
    {
        $this->factory->share($key, $value);

        return $this;
    }

    /**
     * Returns true if the variable is set in the view data.
     *
     * @param  string  $key
     * @return bool
     */
    public function has($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Get the View Factory instance.
     *
     * @return \Nova\View\Factory
     */
    public function getFactory()
    {
        return $this->factory;
    }

    /**
     * Get the name of the view.
     *
     * @return string
     */
    public function getName()
    {
        return $this->view;
    }

    /**
     * Get the array of view data.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the path to the view file.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set the path to the view.
     *
     * @param  string  $path
     * @return void
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Implementation of the ArrayAccess offsetExists method.
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * Implementation of the ArrayAccess offsetGet method.
     */
    public function offsetGet($offset)
    {
        return Arr::get($this->data, $offset);
    }

    /**
      * Implementation of the ArrayAccess offsetSet method.
      */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    /**
     * Implementation of the ArrayAccess offsetUnset method.
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    /**
     * Magic Method for handling dynamic data access.
     */
    public function __get($key)
    {
        return Arr::get($this->data, $key);
    }

    /**
     * Magic Method for handling the dynamic setting of data.
     */
    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Magic Method for checking dynamically set data.
     */
    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Magic Method for handling dynamic functions.
     *
     * @param  string  $method
     * @param  array   $params
     * @return \Nova\View\View|static|void
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $params)
    {
        // Add the support for the dynamic withX Methods.
        if (Str::startsWith($method, 'with')) {
            $name = Str::camel(substr($method, 4));

            return $this->with($name, array_shift($params));
        }

        throw new \BadMethodCallException("Method [$method] does not exist on " .get_class($this));
    }


    /**
     * Get the evaluated string content of the View.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (\Exception $e) {
            return '';
        }
    }

}
