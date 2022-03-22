<?php

namespace HulkApps\AppManager;

use HulkApps\AppManager\Client\Client;

class AppManager
{
    public $client;

    public function __construct($api_endpoint, $api_key) {

        $this->client = Client::withHeaders(['token' => $api_key])->baseUri($api_endpoint);
    }

    public function getBanners() {

        $data = $this->client->get('static-contents');
        
        return $data->json();
    }
}
