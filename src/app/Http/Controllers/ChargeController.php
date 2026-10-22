<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use HulkApps\AppManager\app\Events\PlanActivated;
use HulkApps\AppManager\Client\ClientResponse;
use HulkApps\AppManager\Exception\ChargeException;
use HulkApps\AppManager\Exception\GraphQLException;
use HulkApps\AppManager\Exception\MissingShopException;
use HulkApps\AppManager\GraphQL\GraphQL;
use HulkApps\AppManager\Shopify\ShopifyApi;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function HulkApps\AppManager\app\deleteAppManagerCache;

class ChargeController extends Controller
{
    private function chargeSnapshotCacheKey($shop, $timestamp): string
    {
        return 'app-manager:charge-snapshot:' . $shop . ':' . $timestamp;
    }

    public function process(Request $request, $plan_id)
    {

        $tableName = config('app-manager.shop_table_name', 'users');
        $storeNameField = config('app-manager.field_names.name', 'name');
        $storePlanField = config('app-manager.field_names.plan_id', 'plan_id');
        $storeTrialActivatedAtField = config('app-manager.field_names.trial_activated_at', 'trial_activated_at');
        $storeShopifyPlanField = config('app-manager.field_names.shopify_plan', 'shopify_plan');
        $storePartnerDevelopmentField = config('app-manager.field_names.partner_development', 'partner_development');

        $shop = DB::table($tableName)->where($storeNameField, $request->shop)->first();

        if ($shop) {

            $plan = \AppManager::getPlan($plan_id, $request->shop);
            if ($plan['price'] == 0 && (!isset($plan['is_external_charge']) || !$plan['is_external_charge'])) {
                $apiVersion = config('app-manager.shopify_api_version');

                $storedCharge = \AppManager::getCharge($request->shop);
                if ($storedCharge && !empty($storedCharge['active_charge'])) {
                    $this->cancelShopifyCharge($shop->$storeNameField, $storedCharge['active_charge']['charge_id']);

                    if (!empty($shop->$storePlanField)) {
                        \AppManager::cancelCharge($request->shop, $shop->$storePlanField);
                    }
                }
                $storeGrandfathered = config('app-manager.field_names.grandfathered', 'grandfathered');
                $userUpdateInfo = [$storePlanField => $plan_id, $storeGrandfathered => 0];
                $shopify_fields = config('app-manager.field_names');
                if(isset($shopify_fields['total_trial_days'])){
                    $userUpdateInfo[$shopify_fields['total_trial_days']] = $plan['trial_days']?? 0;
                }
                $user = DB::table($tableName)->where($storeNameField, $request->shop)
                    ->update($userUpdateInfo);
                try {
                    $plan['shop_domain'] = $request->shop;
                    $plan['old_plan'] = $request->old_plan ?? null;
                    event(new PlanActivated($plan, null, null));
                } catch (\Exception $exception) {
                    report($exception);
                }
                if (empty($plan['public'])) {
                    try {
                        \AppManager::markCustomPlanUsed($request->shop, $plan_id);
                    } catch (\Exception $exception) {
                        report($exception);
                    }
                }
                deleteAppManagerCache();
                return response()->json(['status' => true,'plan_type' =>'free_plan']);
            }

            $query = '
        mutation appSubscriptionCreate(
            $name: String!,
            $returnUrl: URL!,
            $trialDays: Int,
            $test: Boolean,
            $lineItems: [AppSubscriptionLineItemInput!]!
        ) {
            appSubscriptionCreate(
                name: $name,
                returnUrl: $returnUrl,
                trialDays: $trialDays,
                test: $test,
                lineItems: $lineItems
            ) {
                appSubscription {
                    id
                }
                confirmationUrl
                userErrors {
                    field
                    message
                }
            }
        }
        ';

            $trialDays = (int) ($plan['trial_days'] ?? 0);
            $remaining = \AppManager::getRemainingDays(
                $shop->$storeNameField,
                $shop->$storeTrialActivatedAtField,
                $plan_id
            );
            if (is_numeric($remaining)) {
                $trialDays = max(0, (int) $remaining);
            }

            $shopifyPlan = $shop->$storeShopifyPlanField;

            $test = app()->environment('development', 'local');

            if (!empty($plan['affiliate'])) {
                $test = in_array($shopifyPlan, array_column($plan['affiliate'], 'value')) ? true : null;

                $isPartnerDevelopment = (bool) ($shop->$storePartnerDevelopmentField ?? false);
                if ($isPartnerDevelopment) {
                    $test = true;
                }
            }

            $createdAtField = config('app-manager.field_names.created_at', 'created_at');
            $shopInstalledAt = $shop->$createdAtField ?? null;

            // Resolve the winning discount the same way the billing page does:
            // the automatic discount and (if supplied) the coded one, by
            // priority — highest wins, automatic breaks a tie.
            $candidates = [];
            $automatic = \AppManager::getAutomaticDiscount($request->shop, $shopInstalledAt);
            if (!empty($automatic)) {
                $candidates[] = $automatic;
            }
            if ($request->filled('discount_code')) {
                $coded = \AppManager::getPromotionalDiscount($request->get('discount_code'), $request->shop, $shopInstalledAt);
                if (!empty($coded)) {
                    $candidates[] = $coded;
                }
            }
            usort($candidates, fn ($a, $b) =>
                [$b['priority'] ?? 0, !empty($b['is_automatic']) ? 1 : 0] <=> [$a['priority'] ?? 0, !empty($a['is_automatic']) ? 1 : 0]
            );
            $promotionalDiscount = $candidates[0] ?? [];

            // Per-plan override for the chosen plan (falls back to the campaign value/type/cycles).
            $override = $promotionalDiscount['plan_overrides'][$plan['id']] ?? [];
            $promoValue = $override['value'] ?? ($promotionalDiscount['value'] ?? null);
            $promoType = $override['type'] ?? ($promotionalDiscount['type'] ?? 'percentage');
            $promoCycles = $override['duration_intervals'] ?? ($promotionalDiscount['duration_intervals'] ?? null);

            // "Upgrades only": apply only on plans priced above the merchant's
            // current plan, so the charge stays in step with the billing page.
            $currentPlanPrice = 0;
            if (!empty($promotionalDiscount['upgrades_only'])) {
                $currentCharge = \AppManager::getCharge($request->shop);
                $currentPlanPrice = (float) ($currentCharge['active_charge']['price'] ?? 0);
            }

            $promoApplies = !empty($promotionalDiscount)
                && (float) ($promoValue ?? 0) > 0
                && empty($plan['is_global'])
                && (empty($promotionalDiscount['plan_relation']) || in_array($plan['id'], $promotionalDiscount['plan_relation']))
                && (empty($promotionalDiscount['upgrades_only']) || (float) $plan['price'] > $currentPlanPrice);

            $discount = [];
            if ($promoApplies) {
                $discount_type = $promoType;

                $discount = [
                    'value' => [
                        $discount_type => $discount_type === "percentage" ? (float)$promoValue / 100 : ($promoValue <= $plan['price'] ? $promoValue : $plan['price']),
                    ],
                ];
                if((int)$promoCycles){
                    $discount['durationLimitInIntervals'] = (int)$promoCycles;
                }
            } elseif ($plan['discount']) {
                $discount_type = $plan['discount_type'] ?? "percentage";

                $discount = [
                    'value' => [
                        $discount_type => $discount_type === "percentage" ? (float)$plan['discount'] / 100 : $plan['discount'],
                    ],
                ];
                if((int)$plan['cycle_count']){
                    $discount['durationLimitInIntervals'] = (int)$plan['cycle_count'];
                }
            }

            $promotionalDiscountId = $promoApplies ? ($promotionalDiscount['id'] ?? 0) : 0;

            $discountSnapshot = [];
            if (!empty($discount)) {
                if ($promoApplies) {
                    $discountSnapshot = [
                        'discount_value' => (float) $promoValue,
                        'discount_type' => $promoType,
                        'discount_duration_intervals' => (int) ($promoCycles ?? 0),
                        'discount_source' => 'promotional',
                        'promotional_discount_id' => (int) ($promotionalDiscount['id'] ?? 0),
                    ];
                } elseif ($plan['discount']) {
                    $discountSnapshot = [
                        'discount_value' => (float) $plan['discount'],
                        'discount_type' => $plan['discount_type'] ?? 'percentage',
                        'discount_duration_intervals' => (int) ($plan['cycle_count'] ?? 0),
                        'discount_source' => !empty($plan['discount_is_custom']) ? 'custom' : 'plan',
                    ];
                }
            }

            $chargeTimestamp = now()->unix() * 1000;
            $requestData = ['shop' => $shop->$storeNameField, 'timestamp' => $chargeTimestamp, 'plan' => $plan_id];

            if (!empty($discountSnapshot)) {
                Cache::put(
                    $this->chargeSnapshotCacheKey($shop->$storeNameField, $chargeTimestamp),
                    $discountSnapshot,
                    now()->addHour()
                );
            }

            if($request->has('old_plan') && !empty($request->old_plan)){
                $requestData['old_plan'] = $request->old_plan;
            }

            if(!empty($promotionalDiscountId)){
                $requestData['discount'] = $promotionalDiscountId;
            }

            //add host
            if($request->has('host') && !empty($request->host)){
                $requestData['host'] = $request->host;
            }


            $returnUrlRoute = route('app-manager.plan.callback');

            $customCallbackRoute = config('app-manager.callback_route');
            if (!empty($customCallbackRoute)) {
                $returnUrlRoute = $customCallbackRoute;
            }

            if (strpos($returnUrlRoute, '?') !== false)
                $returnToUrl = $returnUrlRoute . "&" . http_build_query($requestData, '', '&', PHP_QUERY_RFC3986);
            else
                $returnToUrl = $returnUrlRoute . "?" . http_build_query($requestData, '', '&', PHP_QUERY_RFC3986);

            $variables = [
                'name' => $plan['name'],
                'returnUrl' => $returnToUrl,
                'trialDays' => $trialDays,
                'test' => $test,
                'lineItems' => [
                    [
                        'plan' => [
                            'appRecurringPricingDetails' => array_filter([
                                'price' => [
                                    'amount' => $plan['price'],
                                    'currencyCode' => 'USD',
                                ],
                                'discount' => $discount,
                                'interval' => $plan['interval']['value'],
                            ]),
                        ],
                    ],
                ],
            ];

            //allow adding additional charge
            if($plan['interval']['value'] == 'EVERY_30_DAYS' && isset($plan['is_external_charge']) && $plan['is_external_charge']){
                $cappedAmount = ($request->has('capped_amount') && $request->capped_amount > 0)?$request->capped_amount:$plan['external_charge_limit'];
                $terms = ($request->has('terms') && $request->terms !== null)?$request->terms:$plan['terms'];
                $variables['lineItems'][]['plan']['appUsagePricingDetails'] =array_filter([
                    'terms' => $terms,
                    'cappedAmount' => [
                        'amount' => $cappedAmount,
                        'currencyCode' => "USD"
                    ]
                ]);
            }

            try {
                $response = GraphQL::shop(get_object_vars($shop))->query($query)->withParams($variables)->send();
            } catch (GraphQLException $exception) {

                report($exception);
                return response()->json(['error' => $exception->getMessage()], $exception->getCode()?:500);
            }

            return response()->json(['redirect_url' => $response['appSubscriptionCreate']['confirmationUrl']]);
        }

        throw new ModelNotFoundException("Shop $request->shop not found");
    }

    public function callback(Request $request)
    {
        $tableName = config('app-manager.shop_table_name', 'users');
        $storeName = config('app-manager.field_names.name', 'name');
        $storePlanField = config('app-manager.field_names.plan_id', 'plan_id');
        $storeGrandfathered = config('app-manager.field_names.grandfathered', 'grandfathered');

        if (empty($request->shop)) {
            throw new ChargeException("Shop Required");
        }

        $shop = DB::table($tableName)->where($storeName, $request->shop)->first();
        if (empty($shop)) {
            throw new ChargeException("Invalid charge");
        }
        $apiVersion = config('app-manager.shopify_api_version');
        $responseData = ['shop' => $shop->$storeName];
        //add host
        if($request->has('host') && !empty($request->host)){
            $responseData['host'] = $request->host;
        }
        // Cancel charge
        if(!$request->charge_id){
            return \redirect()->route('home',$responseData);
        }
        if (!ctype_digit((string) $request->charge_id)) {
            throw new ChargeException("Invalid charge");
        }

        // The merchant is already being billed: a stale token here used to end
        // in "Invalid charge" with the plan never recorded.
        $charge = app(ShopifyApi::class)
            ->rest($shop->$storeName, 'get', "recurring_application_charges/{$request->charge_id}.json")
            ->json();

        if (!empty($charge['recurring_application_charge'])) {

            $charge = $charge['recurring_application_charge'];

            if (strtolower($charge['status'] ?? '') !== 'active') {
                return \redirect()->route('home', $responseData);
            }

            // Read the plan from Shopify's stored return_url: the query string can be edited.
            parse_str((string) parse_url($charge['return_url'] ?? '', PHP_URL_QUERY), $bound);
            if (empty($bound['plan']) || ($bound['shop'] ?? null) !== $shop->$storeName) {
                throw new ChargeException("Invalid charge");
            }
            $planId = $bound['plan'];
            $discountId = $bound['discount'] ?? null;

            // TODO: an empty plan or failed storeCharge() leaves a paying merchant without the plan; report and retry.
            // TODO: pass the shop domain, not $shop->id, so custom discounts apply.
            $plan = \AppManager::getPlan($planId, $shop->id);
            $plan['old_plan'] = $bound['old_plan'] ?? null;

            $charge['charge_id']  = $charge['id'];
            $charge['type'] = 'recurring';
            $charge['plan_id'] = $planId;
            $charge['shop_domain'] = $request->shop;
            $charge['interval'] = $plan['interval']['value'];

            $snapshotKey = $this->chargeSnapshotCacheKey($request->shop, $bound['timestamp'] ?? null);
            $discountSnapshot = Cache::get($snapshotKey);
            if (is_array($discountSnapshot)) {
                foreach ($discountSnapshot as $field => $value) {
                    if ($value !== null && $value !== '') {
                        $charge[$field] = $value;
                    }
                }
                Cache::forget($snapshotKey);
            }

            /*if (!empty($shop->$storePlanField)) {
                \AppManager::cancelCharge($request->shop, $shop->$storePlanField);
            }*/

            unset($charge['api_client_id'], $charge['return_url'], $charge['decorated_return_url'], $charge['id'], $charge['id'],$charge['currency']);
            $data = \AppManager::storeCharge($charge);

            if ($data['message'] === "success") {
                deleteAppManagerCache();
                $userUpdateInfo = [$storePlanField => $planId, $storeGrandfathered => 0];
                $shopify_fields = config('app-manager.field_names');
                if(isset($shopify_fields['total_trial_days'])){
                    $userUpdateInfo[$shopify_fields['total_trial_days']] = $plan['trial_days']?? 0;
                }
                DB::table($tableName)->where($storeName, $request->shop)->update($userUpdateInfo);
                $chargeData = \AppManager::getCharge($shop->$storeName);

                $discountedPlans = [];
                if(!empty($discountId))
                    $discountedPlans = \AppManager::getRelatedDiscountedPlans($discountId);

                try {
                    event(new PlanActivated($plan, $charge, $chargeData['cancelled_charge'] ?? null));
                    if (!empty($discountId) &&  !$plan['is_global']) {
                        if (empty($discountedPlans) || in_array($planId, $discountedPlans)) {
                            $discountApplied = \AppManager::discountUsed($shop->$storeName, $discountId);
                        }
                    }

                } catch (\Exception $exception) {
                    report($exception);
                }
            }
        } else throw new ChargeException("Invalid charge");


        return \redirect()->route('home',$responseData);
    }

    public function cancelCharge(Request $request)
    {
        $tableName = config('app-manager.shop_table_name', 'users');
        $storeNameField = config('app-manager.field_names.name', 'name');

        $shop = DB::table($tableName)->where($storeNameField, $request->shop)->first();

        $apiVersion = config('app-manager.shopify_api_version');

        if (! $shop) {
            return response()->json(['message' => "Shop {$request->shop} not found"], 404);
        }

        // Called by the portal's queued job, with no merchant in the app.
        if ($request->get('charge_id')) {
            $this->cancelShopifyCharge($shop->$storeNameField, $request->get('charge_id'));
        }
    }

    public function activateGlobalPlan(Request $request)
    {
        $tableName = config('app-manager.shop_table_name', 'users');
        $storeNameField = config('app-manager.field_names.name', 'name');
        $storePlanField = config('app-manager.field_names.plan_id', 'plan_id');
        $storeTrialActivatedAtField = config('app-manager.field_names.trial_activated_at', 'trial_activated_at');

        $shop = DB::table($tableName)->where($storeNameField, $request->shop)->first();

        if ($shop) {
            $plan = \AppManager::getPlan($request->plan_id, $request->shop);

            $storeGrandfathered = config('app-manager.field_names.grandfathered', 'grandfathered');
            $userUpdateInfo = [$storePlanField => $request->plan_id, $storeGrandfathered => 0];
            $shopify_fields = config('app-manager.field_names');
            if(isset($shopify_fields['total_trial_days'])){
                $userUpdateInfo[$shopify_fields['total_trial_days']] = $plan['trial_days']?? 0;
            }
            $user = DB::table($tableName)->where($storeNameField, $request->shop)
                ->update($userUpdateInfo);
            $chargeData = \AppManager::getCharge($request->shop);
            try {
                $plan['shop_domain'] = $request->shop;
                $plan['old_plan'] = $request->old_plan ?? null;
                event(new PlanActivated($plan, $chargeData['bundle_charge'], $chargeData['cancelled_charge']));
            } catch (\Exception $exception) {
                report($exception);
            }
            deleteAppManagerCache();
        }
        return response()->json(['status' => true,'plan_type' =>'global_plan']);
    }

    public function cancelGlobalPlan(Request $request)
    {
        $tableName = config('app-manager.shop_table_name', 'users');
        $storeNameField = config('app-manager.field_names.name', 'name');
        $storePlanField = config('app-manager.field_names.plan_id', 'plan_id');
        $storeTrialActivatedAtField = config('app-manager.field_names.trial_activated_at', 'trial_activated_at');

        $shop = DB::table($tableName)->where($storeNameField, $request->shop)->first();

        if ($shop) {
            $storeGrandfathered = config('app-manager.field_names.grandfathered', 'grandfathered');
            $userUpdateInfo = [$storePlanField => $request->free_plan_id ?? null, $storeGrandfathered => 0];
            $shopify_fields = config('app-manager.field_names');
            if(isset($shopify_fields['total_trial_days'])){
                $userUpdateInfo[$shopify_fields['total_trial_days']] =  0;
            }
            $user = DB::table($tableName)->where($storeNameField, $request->shop)
                ->update($userUpdateInfo);
            if($request->free_plan_id){
                $plan = \AppManager::getPlan($request->free_plan_id, $request->shop);
                try {
                    $plan['shop_domain'] = $request->shop;
                    $plan['old_plan'] = $request->old_plan ?? null;
                    event(new PlanActivated($plan, null, null));
                } catch (\Exception $exception) {
                    report($exception);
                }
            }
            deleteAppManagerCache();
        }
        return response()->json(['status' => true,'plan_type' =>'cancel_plan']);
    }

    /**
     * Never fails the caller. No token at all means the shop is almost certainly
     * uninstalled, and Shopify cancels an app's charges itself on uninstall.
     */
    private function cancelShopifyCharge($shopDomain, $chargeId)
    {
        try {
            $response = app(ShopifyApi::class)->rest(
                $shopDomain,
                'delete',
                "recurring_application_charges/{$chargeId}.json"
            );
        } catch (MissingShopException $exception) {
            Log::warning('App Manager: no Shopify token to cancel charge with; shop is likely uninstalled', [
                'shop' => $shopDomain,
                'charge_id' => $chargeId,
            ]);

            return;
        }

        $this->reportUnconfirmedCancellation($response, $shopDomain, $chargeId);
    }

    /**
     * A refused cancellation leaves the merchant billed while the app believes
     * the charge is gone, so it is reported. 404 means it is already gone.
     */
    private function reportUnconfirmedCancellation(ClientResponse $response, $shopDomain, $chargeId)
    {
        if ($response->isSuccess() || $response->status() === 404) {
            return;
        }

        report(new ChargeException(sprintf(
            'Shopify did not cancel recurring charge %s for %s: HTTP %s %s',
            $chargeId,
            $shopDomain,
            $response->status(),
            $response->body()
        )));
    }
}
