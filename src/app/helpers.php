<?php

namespace HulkApps\AppManager\app;


use Illuminate\Support\Facades\Cache;

if (! function_exists('appManagerCacheData')) {
    function appManagerCacheData($key, $callback)
    {
        $cacheDriver = config('app-manager.cache_driver');

        return ($cacheDriver === 'file')
            ? Cache::rememberForever($key, $callback)
            : Cache::tags('app-manager')->rememberForever($key, $callback);
    }
}

if (! function_exists('deleteAppManagerCache')) {
    function deleteAppManagerCache() {
        $cacheDriver = config('app-manager.cache_driver');
        ($cacheDriver === 'file')? Cache::flush() : Cache::tags('app-manager')->flush();
    }

}


