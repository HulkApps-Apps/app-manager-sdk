<?php

namespace HulkApps\AppManager;

use HulkApps\AppManager\Client\Client;

class AppManager
{
    public $client;

    public function __construct($api_endpoint, $api_key) {

        $this->client = Client::withHeaders(['token' => $api_key, 'Accept' => 'application/json'])->withoutVerifying()->baseUri($api_endpoint);
    }

    public function getBanners() {

        $data = $this->client->get('static-contents');
        
        return $data->json();
    }

    public function getPlans() {

        $data = $this->client->get('plans');

        return $data->json();
    }

    public function getPlan($request) {

        $data = $this->client->get('plan', $request->all());

        return response()->json(json_decode($data->getBody()->getContents()), $data->getStatusCode());
    }

    public function storeCharge($request) {

        $res = $this->client->post('store-charge', $request->all());

        return response()->json(json_decode($res->getBody()->getContents()), $res->getStatusCode());
    }
}
