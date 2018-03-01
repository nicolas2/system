<?php

namespace Nova\Theme\Support\Providers;

use Nova\Support\ServiceProvider;


class ThemeServiceProvider extends ServiceProvider
{
    /**
     * The provider class names.
     *
     * @var array
     */
    protected $providers = array();



    protected function bootstrapFrom($path)
    {
        $app = $this->app;

        return require $path;
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        foreach ($this->providers as $provider) {
            $this->app->register($provider);
        }
    }

}
