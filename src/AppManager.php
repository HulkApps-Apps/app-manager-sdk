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

    public function getPlan($plan_id, $shop_domain = null) {

        $data = $this->client->get('plan', ['plan_id' => $plan_id, 'shop_domain' => $shop_domain]);

        return $data->json();
    }

    public function storeCharge($payload) {

        return $this->client->post('store-charge', $payload)->json();
    }

    public function cancelCharge($shop_domain, $plan_id) {

        return $this->client->post('cancel-charge', [
            'shop_domain' => $shop_domain,
            'plan_id' => $plan_id
        ])->json();
    }

    public function getRemainingDays($shop_domain) {

        return $this->client->get('get-remaining-days', ['shop_domain' => $shop_domain])->json();
    }
}
