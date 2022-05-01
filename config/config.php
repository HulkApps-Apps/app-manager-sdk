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
    'api' => env('APP_MANAGER_URI', 'https://app-manager.hulkapps.com/api/'),

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

    /*
    |-------------------------------------------
    | The Shopify User(Shop) table name
    |-------------------------------------------
    |
    | The table in which user or shop credentials are stored
    |
    | Default: users
    |
    |-------------------------------------------
    */
    'shop_table_name' => env('SHOP_TABLE_NAME', 'users'),

    /*
    |-------------------------------------------
    | Shopify's store name field
    |-------------------------------------------
    |
    | Name of the field in which shopify store name is store
    |
    | Default: name
    |
    |-------------------------------------------
    */
    'store_field_name' => env('STORE_FIELD_NAME', 'name'),

    /*
    |-------------------------------------------
    | Shopify's plan name field
    |-------------------------------------------
    |
    | Name of the field in which shopify plan is store
    |
    | Default: plan_id
    |
    |-------------------------------------------
    */
    'plan_field_name' => env('PLAN_FIELD_NAME', 'plan_id'),

    /*
    |--------------------------------------------------------------------------
    | Shopify API Version
    |--------------------------------------------------------------------------
    |
    | This option is for the app's API version string.
    | Use "YYYY-MM" or "unstable". Refer to Shopify documentation
    | at https://shopify.dev/api/usage/versioning#release-schedule
    | for the current stable version.
    |
    */

    'shopify_api_version' => env('SHOPIFY_API_VERSION', config('shopify-app.api_version')),
];