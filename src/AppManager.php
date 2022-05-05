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

    public function getPlan($plan_id, $shopify_domain = null) {

        $data = $this->client->get('plan', ['plan_id' => $plan_id, 'shopify_domain' => $shopify_domain]);

        return $data->json();
    }

    public function storeCharge($payload) {

        return $this->client->post('store-charge', $payload)->json();
    }

    public function cancelCharge($shopify_domain, $plan_id) {
        $res = $this->client->post('cancel-charge', [
            'shopify_domain' => $shopify_domain,
            'plan_id' => $plan_id
        ]);
        return response()->json(json_decode($res->getBody()->getContents()), $res->getStatusCode());
    }

    public function getRemainingDays($plan_id, $user_id) {

        $data = $this->client->get('get-remaining-days', ['plan_id' => $plan_id, 'user_id' => $user_id]);

        return $data->json();
    }
}
