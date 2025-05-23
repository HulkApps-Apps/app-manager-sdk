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

        if ($cacheDriver === 'redis') {
                Cache::tags('app-manager')->flush();
        } else {
            Cache::flush();
        }
    }

}

if (! function_exists('isValidUser')) {
    function isValidUser($request) {
        $secret = config('app-manager.secret');
        $requestSecret = $request->header('token');
        return $secret === $requestSecret;
    }
}


