<?php

namespace Nova\View;

use Nova\View\Console\ViewClearCommand;
use Nova\Support\ServiceProvider;


class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the Provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Register the application services.
     */
    public function register()
    {
        $this->app->singleton('command.view.clear', function ($app)
        {
            return new ViewClearCommand($app['files']);
        });

        $this->commands('command.view.clear');
    }
}
