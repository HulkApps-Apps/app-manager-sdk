<?php

return [

    /*
    |-------------------------------------------
    | App manager API endpoint
    |-------------------------------------------
    |
    | The endpoint of app manager apis to retrieve data for the app
    |
    | Default: https://app-manager.hulkapps.com/api/
    |
    |-------------------------------------------
    */
    'api' => env('APP_MANAGER_URI', 'https://app-manager.hulkapps.com/api/v1'),

    /*
    |-------------------------------------------
    | App manager API secret [REQUIRED]
    |-------------------------------------------
    |
    | The secret of the app manager for authentication
    |
    |-------------------------------------------
    */
    'secret' => env('APP_MANAGER_SECRET', ''),

    /*
    |-------------------------------------------
    | The App manager api
    |-------------------------------------------
    |
    | The api version of app manager that SDK will use
    |
    | Default: latest
    |
    |-------------------------------------------
    */
    'version' => env('APP_MANAGER_API_VER', 'latest'),

    /*
    |-------------------------------------------
    | The slug/key of the app [REQUIRED]
    |-------------------------------------------
    |
    | The key of the app will tell app manager for which app you want to fetch the data
    |
    |-------------------------------------------
    */
];