<?php
/**
 * ValidationServiceProvider - Implements a Service Provider for Validation.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace Nova\Validation;

use Nova\Validation\DatabasePresenceVerifier;
use Nova\Validation\Factory;
use Nova\Validation\Language\Translator;
use Nova\Support\ServiceProvider;


class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the Provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Register the Service Provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTranslator();

        $this->registerPresenceVerifier();

        $this->app->bindShared('validator', function($app)
        {
            $translator = $app['validation.translator'];

            // Get a Validation Factory instance.
            $validator = new Factory($translator);

            if (isset($app['validation.presence'])) {
                $presenceVerifier = $app['validation.presence'];

                $validator->setPresenceVerifier($presenceVerifier);
            }

            return $validator;
        });
    }

    /**
     * Register the Database Presence Verifier.
     *
     * @return void
     */
    protected function registerPresenceVerifier()
    {
        $this->app->bindShared('validation.presence', function($app)
        {
            return new DatabasePresenceVerifier($app['db']);
        });
    }

    /**
     * Register the Database Presence Verifier.
     *
     * @return void
     */
    protected function registerTranslator()
    {
        $this->app->bindShared('validation.translator', function($app)
        {
            return new Translator();
        });
    }

    /**
     * Get the services provided by the Provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('validator');
    }
}
