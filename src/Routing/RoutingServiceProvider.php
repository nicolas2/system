<?php
/**
 * RoutingServiceProvider - Implements a Service Provider for Routing.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace Nova\Routing;

use Nova\Routing\ControllerDispatcher;
use Nova\Routing\ResponseFactory;
use Nova\Routing\Router;
use Nova\Routing\Redirector;
use Nova\Routing\UrlGenerator;
use Nova\Support\ServiceProvider;


class RoutingServiceProvider extends ServiceProvider
{

    /**
     * Boot the Service Provider.
     */
    public function boot()
    {
        $this->registerAssetDispatcher();
    }

    /**
     * Register the Service Provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRouter();

        $this->registerControllerDispatcher();

        $this->registerUrlGenerator();

        $this->registerRedirector();

        $this->registerResponseFactory();
    }

    /**
     * Register the Router instance.
     *
     * @return void
     */
    protected function registerRouter()
    {
        $this->app['router'] = $this->app->share(function ($app)
        {
            return new Router($app['events'], $app);
        });
    }

    /**
     * Register the URL generator service.
     *
     * @return void
     */
    protected function registerControllerDispatcher()
    {
        $this->app->singleton('routing.controller.dispatcher', function ($app)
        {
            return new ControllerDispatcher($app['router'], $app);
        });
    }

    /**
     * Register the URL generator service.
     *
     * @return void
     */
    protected function registerUrlGenerator()
    {
        $this->app['url'] = $this->app->share(function ($app)
        {
            // The URL Generator needs the Route Collection that exists on the Router.
            $routes = $app['router']->getRoutes();

            $url = new UrlGenerator($routes, $app->rebinding('request', function ($app, $request)
            {
                $app['url']->setRequest($request);
            }));

            $url->setSessionResolver(function ()
            {
                return $this->app['session'];
            });

            return $url;
        });
    }

    /**
     * Register the Redirector service.
     *
     * @return void
     */
    protected function registerRedirector()
    {
        $this->app['redirect'] = $this->app->share(function ($app)
        {
            $redirector = new Redirector($app['url']);

            if (isset($app['session.store'])) {
                $redirector->setSession($app['session.store']);
            }

            return $redirector;
        });
    }

    /**
     * Register the response factory implementation.
     *
     * @return void
     */
    protected function registerResponseFactory()
    {
        $this->app->singleton('response.factory', function ($app)
        {
            return new ResponseFactory();
        });
    }

    /**
     * Register the Assets Dispatcher.
     */
    public function registerAssetDispatcher()
    {
        $config = $this->app['config'];

        //
        $driver = $config->get('routing.assets.driver', 'default');

        if ($driver == 'custom') {
            $className = $config->get('routing.assets.dispatcher');
        } else {
            $className = 'Nova\Routing\Assets\\' .ucfirst($driver) .'Dispatcher';
        }

        // Bind the calculated class name to the Assets Dispatcher Interface.
        $this->app->bind('Nova\Routing\Assets\DispatcherInterface', $className);
    }

}
