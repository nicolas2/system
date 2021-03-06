<?php

namespace Nova\Support\Facades;

use Nova\Support\Facades\Facade;


/**
 * @see \Nova\Session\SessionManager
 * @see \Nova\Session\Store
 */
class Session extends Facade
{
    /**
     * Return the Application instance.
     *
     * @return \Nova\Pagination\Factory
     */
    public static function instance()
    {
        $accessor = static::getFacadeAccessor();

        return static::resolveFacadeInstance($accessor);
    }

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'session'; }

}
