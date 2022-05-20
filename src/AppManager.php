<?php

namespace HulkApps\AppManager;

use HulkApps\AppManager\app\Traits\FailsafeHelper;
use HulkApps\AppManager\Client\Client;
use Illuminate\Support\Str;

class AppManager
{
    use FailsafeHelper;
    public $client;

    public function __construct($api_endpoint, $api_key) {

        $this->client = Client::withHeaders(['token' => $api_key, 'Accept' => 'application/json'])->withoutVerifying()->baseUri($api_endpoint);
    }

    public function getBanners() {

        $data = $this->client->get('static-contents');
        if (Str::startsWith($data->getStatusCode(), '2') || Str::startsWith($data->getStatusCode(), '4')) {
            return $data->json();
        }
        else {
            return $this->prepareMarketingBanners();
        }

    }

    public function getPlans() {

        $data = $this->client->get('plans');
        if (Str::startsWith($data->getStatusCode(), '2') || Str::startsWith($data->getStatusCode(), '4')) {
            return $data->json();
        }
        else {
            return $this->preparePlans();
        }
    }

    public function getPlan($plan_id, $shop_domain = null) {

        $data = $this->client->get('plan', ['plan_id' => $plan_id, 'shop_domain' => $shop_domain]);
        if (Str::startsWith($data->getStatusCode(), '2') || Str::startsWith($data->getStatusCode(), '4')) {
            return $data->json();
        }
        else {
            return $this->preparePlan(['plan_id' => $plan_id, 'shop_domain' => $shop_domain]);
        }
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

    public function getRemainingDays($shop_domain, $trial_activated_at = null ,$plan_id = null) {

        $data = $this->client->get('get-remaining-days', [
            'shop_domain' => $shop_domain,
            'trial_activated_at' => $trial_activated_at,
            'plan_id' => $plan_id
        ]);

        if (Str::startsWith($data->getStatusCode(), '2') || Str::startsWith($data->getStatusCode(), '4')) {
            return $data->json();
        }
        else {
            return $this->prepareRemainingDays([
                'shop_domain' => $shop_domain,
                'trial_activated_at' => $trial_activated_at,
                'plan_id' => $plan_id
            ]);
        }
    }

    public function getCharge($shop_domain) {

        $data = $this->client->get('get-charge', [
            'shop_domain' => $shop_domain,
        ]);
        return $data->json();
    }
}
