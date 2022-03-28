<?php

namespace HulkApps\AppManager;

use Illuminate\Support\Facades\Facade;

class AppManagerFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'app-manager';
    }
}
