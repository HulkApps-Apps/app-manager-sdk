<?php

namespace HulkApps\AppManager;

use GuzzleHttp\Client;

class AppManager
{
    private $client;

    public function __construct($api_endpoint, $api_key)
    {
        $client = new Client([
            'base_uri' => $api_endpoint,
            'defaults' => [
                'headers' => ['token' => $api_key]
            ]
        ]);
    }
}
