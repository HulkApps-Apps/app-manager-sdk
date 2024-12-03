<?php

namespace HulkApps\AppManager;

use HulkApps\AppManager\app\Traits\FailsafeHelper;
use HulkApps\AppManager\Client\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use function HulkApps\AppManager\app\deleteAppManagerCache;


class AppManager
{
    use FailsafeHelper;
    public $client;

    public function __construct($api_endpoint, $api_key) {

        $this->client = Client::withHeaders(['token' => $api_key, 'Accept' => 'application/json'])->withoutVerifying()->timeout(10)->baseUri($api_endpoint);
    }

    public function getBanners() {

        try {
            $data = $this->client->get('static-contents');
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : json_decode($this->prepareMarketingBanners());
        }
        catch (\Exception $e) {
            report($e);
            return json_decode($this->prepareMarketingBanners());
        }
    }

    public function getPlans($shop_domain, $active_plan_id = null) {

        try {
            $payload = ['shop_domain' => $shop_domain];
            if ($active_plan_id) {
                $payload['active_plan_id'] = $active_plan_id;
            }
            $data = $this->client->get('plans', $payload);
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->preparePlans($shop_domain, $active_plan_id);
        }
        catch (\Exception $e) {
            report($e);
            return $this->preparePlans($shop_domain);
        }
    }

    public function getPlan($plan_id, $shop_domain = null) {

        try {
            $data = $this->client->get('plan', ['plan_id' => $plan_id, 'shop_domain' => $shop_domain]);
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->preparePlan(['plan_id' => $plan_id, 'shop_domain' => $shop_domain]);
        }
        catch (\Exception $e) {
            report($e);
            return $this->preparePlan(['plan_id' => $plan_id, 'shop_domain' => $shop_domain]);
        }
    }

    public function getBundlePlan($activePlanId = null)
    {
        try {
            $data = $this->client->get('get-bundle-plan', ['active_plan_id' => $activePlanId]);
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : [];
        }
        catch (\Exception $e) {
            report($e);
        }
    }

    public function getAppBundleData()
    {
        try {
            $data = $this->client->get('app-bundle-data');
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : [];
        }
        catch (\Exception $e) {
            report($e);
        }
    }

    public function checkAndActivateGlobalPlan($shop_domain) {

        try {
            $data = $this->client->post('activate-global-plan', ['shop_domain' => $shop_domain]);
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : [];
        }
        catch (\Exception $e) {
            report($e);
        }
    }

    public function getPromotionalDiscount($code, $shop_domain = null) {

        try {
            $data = $this->client->get('discount', ['shop_domain' => $shop_domain, 'code' => $code]);
            if($data->getStatusCode() === 404)
                return [];
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->prepareDiscount(['shop_domain' => $shop_domain, 'code' => $code]);
        }
        catch (\Exception $e) {
            report($e);
            return $this->prepareDiscount(['shop_domain' => $shop_domain,  'code' => $code]);
        }
    }

    public function storeCharge($payload) {

        try {
            $data = $this->client->post('store-charge', $payload);
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->storeChargeHelper($payload);
        }
        catch (\Exception $e) {
            report($e);
            return $this->storeChargeHelper($payload);
        }
    }

    public function cancelCharge($shop_domain, $plan_id) {

        try {
            $data = $this->client->post('cancel-charge', [
                'shop_domain' => $shop_domain,
                'plan_id' => $plan_id
            ]);
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->cancelChargeHelper($shop_domain, $plan_id);
        }
        catch (\Exception $e) {
            report($e);
            return $this->cancelChargeHelper($shop_domain, $plan_id);
        }
    }

    public function updateCharge($shop_domain, $plan_id) {
        try {
            $data = $this->client->post('update-charge', [
                'shop_domain' => $shop_domain,
                'plan_id' => $plan_id
            ]);
            return $data->json();
        }
        catch (\Exception $e) {
            report($e);
        }
    }

    public function syncCharge($payload) {

        $data = $this->client->post('sync-charge', $payload);
        return $data->json();
    }

    public function syncDiscountUsageLog($payload) {

        $data = $this->client->post('use-discount-sync', $payload);
        return $data->json();
    }

    public function getRemainingDays($shop_domain, $trial_activated_at = null ,$plan_id = null) {

        try {
            $data = $this->client->get('get-remaining-days', [
                'shop_domain' => $shop_domain,
                'trial_activated_at' => $trial_activated_at,
                'plan_id' => $plan_id
            ]);

            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->prepareRemainingDays([
                'shop_domain' => $shop_domain,
                'trial_activated_at' => $trial_activated_at,
                'plan_id' => $plan_id
            ]);
        }
        catch (\Exception $e) {
            report($e);
            return $this->prepareRemainingDays([
                'shop_domain' => $shop_domain,
                'trial_activated_at' => $trial_activated_at,
                'plan_id' => $plan_id
            ]);
        }
    }

    public function getCharge($shop_domain) {

        try {
            $data = $this->client->get('get-charge', [
                'shop_domain' => $shop_domain
            ]);
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->getChargeHelper($shop_domain);
        }
        catch (\Exception $e) {
            report($e);
            return $this->getChargeHelper($shop_domain);
        }
    }

    public function hasPlan($shopDetails) {
        try {
            $data = $this->client->get('has-plan',$shopDetails);
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->hasPlanHelper($shopDetails);

        }
        catch (\Exception $e) {
            report($e);
            return $this->hasPlanHelper($shopDetails);
        }
    }

    // for migration only
    public function getCharges() {
        return $this->client->get('get-charges')->json();
    }

    public function getStatus() {
        return $this->client->get('get-status');
    }

    public function getRelatedDiscountedPlans($discount_id) {
        try {
            $data = $this->client->get('get-related-discounted-plans', ['discount_id' => (int) $discount_id]);
            if($data->getStatusCode() === 404)
                return [];
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->prepareRelatedDiscountedPlans((int) $discount_id);
        }
        catch (\Exception $e) {
            report($e);
            return $this->prepareRelatedDiscountedPlans((int) $discount_id);
        }
    }

    public function discountUsed($shop_domain, $discount_id){
        try {
            $data = $this->client->post('use-discount', ['shop_domain' => $shop_domain, 'discount_id' => (int) $discount_id]);
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->storePromotionalDiscountHelper($shop_domain, $discount_id);
        }
        catch (\Exception $e) {
            report($e);
            return $this->storePromotionalDiscountHelper($shop_domain, $discount_id);
        }
    }

    public function deleteAppManagerCache() {
        try {
            deleteAppManagerCache();
        }
        catch (\Exception $e) {
            report($e);
        }
    }

    public function saveToLocalStorage($destinationUrl)
    {
        $url = url()->current();
        $discountCode = collect(explode('/', parse_url($url, PHP_URL_PATH)))->get(2, '');

        try {
            deleteAppManagerCache();
            $response = redirect()->to($destinationUrl. '?discount_code=' . $discountCode);
            return $response;
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function getAddons($status = null) {
        try {
            $payload = [];
            if ($status) {
                $payload['status'] = $status;
            }
            $data = $this->client->get('addons', $payload);
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->prepareAddons($status);
        }
        catch (\Exception $e) {
            report($e);
            return $this->prepareAddons($status);
        }
    }
}