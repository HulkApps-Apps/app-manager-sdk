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
        return Str::startsWith($data->getStatusCode(), '2') || Str::startsWith($data->getStatusCode(), '4') ? $data->json() : json_decode($this->prepareMarketingBanners());
    }

    public function getPlans($shop_domain) {

        $data = $this->client->get('plans', ['shop_domain' => $shop_domain]);
        return Str::startsWith($data->getStatusCode(), '2') || Str::startsWith($data->getStatusCode(), '4') ? $data->json() : $this->preparePlans();
    }

    public function getPlan($plan_id, $shop_domain = null) {

        $data = $this->client->get('plan', ['plan_id' => $plan_id, 'shop_domain' => $shop_domain]);
        return Str::startsWith($data->getStatusCode(), '2') || Str::startsWith($data->getStatusCode(), '4') ? $data->json() : $this->preparePlan(['plan_id' => $plan_id, 'shop_domain' => $shop_domain]);
    }

    public function storeCharge($payload) {

        $data = $this->client->post('store-charge', $payload);
        return Str::startsWith($data->getStatusCode(), '2') || Str::startsWith($data->getStatusCode(), '4') ? $data->json() : $this->storeChargeHelper($payload);
    }

    public function cancelCharge($shop_domain, $plan_id) {

        $data = $this->client->post('cancel-charge', [
            'shop_domain' => $shop_domain,
            'plan_id' => $plan_id
        ]);
        return Str::startsWith($data->getStatusCode(), '2') || Str::startsWith($data->getStatusCode(), '4') ? $data->json() : $this->cancelChargeHelper($shop_domain, $plan_id);
    }

    public function syncCharge($payload) {

        $data = $this->client->post('sync-charge', $payload);
        return $data->json();
    }

    public function getRemainingDays($shop_domain, $trial_activated_at = null ,$plan_id = null) {

        $data = $this->client->get('get-remaining-days', [
            'shop_domain' => $shop_domain,
            'trial_activated_at' => $trial_activated_at,
            'plan_id' => $plan_id
        ]);

        return Str::startsWith($data->getStatusCode(), '2') || Str::startsWith($data->getStatusCode(), '4') ? $data->json() : $this->prepareRemainingDays([
            'shop_domain' => $shop_domain,
            'trial_activated_at' => $trial_activated_at,
            'plan_id' => $plan_id
        ]);
    }

    public function getCharge($shop_domain) {

        $data = $this->client->get('get-charge', [
            'shop_domain' => $shop_domain,
        ]);
        return Str::startsWith($data->getStatusCode(), '2') || Str::startsWith($data->getStatusCode(), '4') ? $data->json() : $this->getChargeHelper($shop_domain);
    }

    public function getStatus() {
        return $this->client->get('get-status');
    }
}
