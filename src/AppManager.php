<?php

namespace HulkApps\AppManager;

use Carbon\Carbon;
use HulkApps\AppManager\app\Traits\FailsafeHelper;
use HulkApps\AppManager\Client\Client;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;


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

    public function getPromotionalDiscount($shop_domain = null, $codeType, $code, $reinstall) {

        try {
            $data = $this->client->get('discount', ['shop_domain' => $shop_domain, 'reinstall' => $reinstall, 'code_type' => $codeType, 'code' => $code]);
            if($data->getStatusCode() === 404)
                return [];
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->prepareDiscount(['shop_domain' => $shop_domain, 'reinstall' => $reinstall, 'code_type' => $codeType, 'code' => $code]);
        }
        catch (\Exception $e) {
            report($e);
            return $this->prepareDiscount(['shop_domain' => $shop_domain, 'reinstall' => $reinstall, 'code_type' => $codeType, 'code' => $code]);
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

    public function discountUsed($storeName, $discount_id){
        try {
            $data = $this->client->post('use-discount', ['shop_domain' => $storeName, 'discount_id' => (int) $discount_id]);
            return (Str::startsWith($data->getStatusCode(), '2') || (Str::startsWith($data->getStatusCode(), '4') && $data->getStatusCode() != 429)) ? $data->json() : $this->storePromotionalDiscountHelper($storeName, $discount_id);
        }
        catch (\Exception $e) {
            report($e);
            return $this->storePromotionalDiscountHelper($storeName, $discount_id);
        }
    }

    public function setCookie($destinationUrl)
    {
        $url = url()->current();
        $host = parse_url($url, PHP_URL_HOST);
        $discountCode = collect(explode('/', parse_url($url, PHP_URL_PATH)))->get(2, '');

        try {
            $lifetime = time() + 60 * 60 * 24 * 365;
            Cookie::queue('ShopCircleDiscount', $discountCode, $lifetime, '/', $host, true, true, false, 'None');
            $queryString = request()->getQueryString();
            Cache::flush();
            return redirect()->to($destinationUrl);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }

    public function resolveFromCookies(): ?array
    {
        if (Cookie::has('ShopCircleDiscount') === true) {
            return [
                'codeType' => 'normal',
                'code' => Cookie::get('ShopCircleDiscount'),
            ];
        }

        return null;
    }

    public function checkIfIsReinstall($created_at): bool
    {
        $created_at = Carbon::parse($created_at);
        return $created_at->isBefore(now()->subMinutes(5));
    }

}