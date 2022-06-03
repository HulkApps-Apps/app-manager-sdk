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
    | Shopify users fields
    |-------------------------------------------
    |
    | Mapped shop user fields to your table
    |
    |-------------------------------------------
    */
    'field_names' => [
        'name' => env('NAME', 'name'), // demo-chirag-parmar.myshopify.com
        'shopify_email' => env('SHOPIFY_EMAIL', 'shopify_email'), // chirag.p@hulkapps.com
        'shopify_plan' => env('SHOPIFY_PLAN', 'shopify_plan'), // partner_test
        'shopify_token' => env('SHOPIFY_TOKEN', 'password'), //
        'plan_id' => env('PLAN_ID', 'plan_id'), // 1
        'created_at' => env('CREATED_AT', 'created_at'), // 2022-04-15 10:43:05
        'trial_activated_at' => env('TRIAL_ACTIVATED_AT', 'trial_activated_at'), // 2022-04-15 10:43:05
        'grandfathered' => env('GRAND_FATHERED', 'grandfathered'), // true
    ],

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

    /*
    |-------------------------------------------
    | The Authorization token
    |-------------------------------------------
    |
    | Authorization token to access app manager admin API
    |
    |-------------------------------------------
    */
    'bearer_token' => env('BEARER_TOKEN', ''),


    /*
    |-------------------------------------------
    | Plan page route
    |-------------------------------------------
    |
    | Route of plan page
    |
    |-------------------------------------------
    */

    'plan_route' => env('PLAN_PAGE_ROUTE', '/plans')
];