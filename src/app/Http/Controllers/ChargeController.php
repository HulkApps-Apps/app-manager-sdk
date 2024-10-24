<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use HulkApps\AppManager\app\Events\PlanActivated;
use HulkApps\AppManager\Client\Client;
use HulkApps\AppManager\Exception\ChargeException;
use HulkApps\AppManager\Exception\GraphQLException;
use HulkApps\AppManager\GraphQL\GraphQL;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use function HulkApps\AppManager\app\deleteAppManagerCache;

class ChargeController extends Controller
{
    public function process(Request $request, $plan_id)
    {

        $tableName = config('app-manager.shop_table_name', 'users');
        $storeNameField = config('app-manager.field_names.name', 'name');
        $storePlanField = config('app-manager.field_names.plan_id', 'plan_id');
        $storeTrialActivatedAtField = config('app-manager.field_names.trial_activated_at', 'trial_activated_at');
        $storeShopifyPlanField = config('app-manager.field_names.shopify_plan', 'shopify_plan');

        $shop = DB::table($tableName)->where($storeNameField, $request->shop)->first();

        if ($shop) {

            $plan = \AppManager::getPlan($plan_id, $request->shop);
            if ($plan['price'] == 0 && (!isset($plan['is_external_charge']) || !$plan['is_external_charge'])) {
                $apiVersion = config('app-manager.shopify_api_version');

                $storedCharge = \AppManager::getCharge($request->shop);
                if ($storedCharge && !empty($storedCharge['active_charge'])) {
                    $storeTokenField = config('app-manager.field_names.shopify_token', 'shopify_token');
                    $charge = Client::withHeaders(["X-Shopify-Access-Token" => $shop->$storeTokenField])
                        ->delete("https://{$shop->$storeNameField}/admin/api/$apiVersion/recurring_application_charges/{$storedCharge['active_charge']['charge_id']}.json");

                    if (!empty($shop->$storePlanField)) {
                        \AppManager::cancelCharge($request->shop, $shop->$storePlanField);
                    }
                }
                $storeGrandfathered = config('app-manager.field_names.grandfathered', 'grandfathered');
                $userUpdateInfo = [$storePlanField => $plan_id, $storeTrialActivatedAtField => null,$storeGrandfathered => 0];
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

            $trialDays = $plan['trial_days'] ?? 0;

            if (!empty($shop->$storePlanField) && $trialDays) {

                $remaining = \AppManager::getRemainingDays($shop->$storeNameField, $shop->$storeTrialActivatedAtField, $shop->$storePlanField);
                if($remaining !== null){
                    if($shop->$storePlanField != null){
                        $currentPlan = \AppManager::getPlan($shop->$storePlanField);
                        $usedDays = $currentPlan['trial_days'] - $remaining;
                        if($usedDays > 0){
                            $days = $trialDays - $usedDays;
                            $trialDays = $days > 0?$days:0;
                        }
                    }else{
                        $trialDays = $remaining;
                    }
                }
                //$trialDays = $remaining !== null ? $remaining : $trialDays;
            }


            $shopifyPlan = $shop->$storeShopifyPlanField;

            $test = app()->environment('development', 'local');

            if (!empty($plan['affiliate'])) {
                $test = in_array($shopifyPlan, array_column($plan['affiliate'], 'value')) ? true : null;
            }

            $promotionalDiscount=[];
            if($request->has('discount_code')){
                $discountCode = $request->get('discount_code');
                if (!empty($discountCode)) {
                    $promotionalDiscount = \AppManager::getPromotionalDiscount($discountCode, $request->shop);
                }
            }

            $discount = [];
            if($plan['discount']){
                $discount_type = $plan['discount_type'] ?? "percentage";

                $discount = [
                    'value' => [
                        $discount_type => $discount_type === "percentage" ? (float)$plan['discount'] / 100 : $plan['discount'],
                    ],
                ];
                if((int)$plan['cycle_count']){
                    $discount['durationLimitInIntervals'] = (int)$plan['cycle_count'];
                }
            }elseif ($promotionalDiscount){
                if(($promotionalDiscount['plan_relation'] && !in_array($plan['id'], $promotionalDiscount['plan_relation'])) || $plan['is_global']){
                    $discount = [];
                }
                else{
                    $discount_type = $promotionalDiscount['type'] ?? "percentage";

                    $discount =[
                        'value' => [
                            $discount_type => $discount_type === "percentage" ? (float)$promotionalDiscount['value'] / 100 : ($promotionalDiscount['value'] <= $plan['price'] ? $promotionalDiscount['value'] : $plan['price']),
                        ],
                    ];
                    if((int)$promotionalDiscount['duration_intervals']){
                        $discount['durationLimitInIntervals'] = (int)$promotionalDiscount['duration_intervals'];
                    }
                }
            }

            $promotionalDiscountId = $plan['discount'] && $promotionalDiscount ? 0 : ($promotionalDiscount ? $promotionalDiscount['id'] : 0);

            $requestData = ['shop' => $shop->$storeNameField, 'timestamp' => now()->unix() * 1000, 'plan' => $plan_id];

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

            //allow to add additional charge
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
        $storeToken = config('app-manager.field_names.shopify_token');
        $storePlanField = config('app-manager.field_names.plan_id', 'plan_id');
        $storeGrandfathered = config('app-manager.field_names.grandfathered', 'grandfathered');

        $shop = DB::table($tableName)->where($storeName, $request->shop)->first();
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

        $charge = Client::withHeaders(["X-Shopify-Access-Token" => $shop->$storeToken])
            ->get("https://{$shop->$storeName}/admin/api/$apiVersion/recurring_application_charges/{$request->charge_id}.json")->json();

        $plan = \AppManager::getPlan($request->plan, $shop->id);
        $plan['old_plan'] = $request->old_plan ?? null;

        if (!empty($charge['recurring_application_charge'])) {

            $charge = $charge['recurring_application_charge'];
            $charge['charge_id']  = $charge['id'];
            $charge['type'] = 'recurring';
            $charge['plan_id'] = $request->plan;
            $charge['shop_domain'] = $request->shop;
            $charge['interval'] = $plan['interval']['value'];

            /*if (!empty($shop->$storePlanField)) {
                \AppManager::cancelCharge($request->shop, $shop->$storePlanField);
            }*/

            unset($charge['api_client_id'], $charge['return_url'], $charge['decorated_return_url'], $charge['id'], $charge['id'],$charge['currency']);
            $data = \AppManager::storeCharge($charge);

            if ($data['message'] === "success") {
                deleteAppManagerCache();
                $userUpdateInfo = [$storePlanField => $request->plan, $storeGrandfathered => 0];
                $shopify_fields = config('app-manager.field_names');
                if(isset($shopify_fields['total_trial_days'])){
                    $userUpdateInfo[$shopify_fields['total_trial_days']] = $plan['trial_days']?? 0;
                }
                DB::table($tableName)->where($storeName, $request->shop)->update($userUpdateInfo);
                $chargeData = \AppManager::getCharge($shop->$storeName);

                $discountedPlans = [];
                if(!empty($request->discount))
                    $discountedPlans = \AppManager::getRelatedDiscountedPlans($request->discount);

                try {
                    event(new PlanActivated($plan, $charge, $chargeData['cancelled_charge'] ?? null));
                    if (!empty($request->discount) &&  !$plan['is_global']) {
                        if (empty($discountedPlans) || in_array($request->plan, $discountedPlans)) {
                            $discountApplied = \AppManager::discountUsed($shop->$storeName, $request->discount);
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

        if ($request->get('charge_id')) {
            $storeTokenField = config('app-manager.field_names.shopify_token', 'shopify_token');
            $charge = Client::withHeaders(["X-Shopify-Access-Token" => $shop->$storeTokenField])
                ->delete("https://{$shop->$storeNameField}/admin/api/$apiVersion/recurring_application_charges/{$request->get('charge_id')}.json");
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
            $userUpdateInfo = [$storePlanField => $request->plan_id, $storeTrialActivatedAtField => null,$storeGrandfathered => 0];
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
            $userUpdateInfo = [$storePlanField => $request->free_plan_id ?? null, $storeTrialActivatedAtField => null,$storeGrandfathered => 0];
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
}