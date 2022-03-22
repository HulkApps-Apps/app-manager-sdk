<?php

return [

    /*
    |-------------------------------------------
    | App manager API endpoint
    |-------------------------------------------
    |
    | The endpoint of app manager apis to retrieve data for the app
    |
    | Default: https://app-manager.hulkapps.dev/api/
    |
    |-------------------------------------------
    */
    'app_manager_endpoint' => 'https://app-manager.hulkapps.dev/api/',

    /*
    |-------------------------------------------
    | App manager API secret [REQUIRED]
    |-------------------------------------------
    |
    | The secret of the app manager for authentication
    |
    |-------------------------------------------
    */
    'app_manager_secret' => '',

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
    'version' => 'latest',

    /*
    |-------------------------------------------
    | The slug/key of the app [REQUIRED]
    |-------------------------------------------
    |
    | The key of the app will tell app manager for which app you want to fetch the data
    |
    |-------------------------------------------
    */
    'app_key' => ''
];