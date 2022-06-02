<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Carbon\Carbon;
use HulkApps\AppManager\app\Events\PlanActivated;
use HulkApps\AppManager\Client\Client;
use HulkApps\AppManager\Exception\ChargeException;
use HulkApps\AppManager\Exception\GraphQLException;
use HulkApps\AppManager\GraphQL\GraphQL;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

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
            $requestData = ['shop' => $shop->$storeNameField, 'timestamp' => now()->unix() * 1000, 'plan' => $plan_id];

            if (!empty($shop->$storePlanField) && $trialDays) {

                $remaining = \AppManager::getRemainingDays($shop->$storeNameField, $shop->$storeTrialActivatedAtField, $shop->$storePlanField);

                $trialDays = $remaining !== null ? $remaining : $trialDays;
            }

            $discount_type = $plan['discount_type'] ?? "percentage";

            $shopifyPlan = $shop->$storeShopifyPlanField;

            $test = app()->environment('development', 'local');

            if (!empty($plan['affiliate'])) {
                $test = in_array($shopifyPlan, array_column($plan['affiliate'], 'value')) ? true : null;
            }

            $variables = [
                'name' => $plan['name'],
                'returnUrl' => route('app-manager.plan.callback')."?".http_build_query($requestData, '', '&', PHP_QUERY_RFC3986),
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
                                'discount' => $plan['discount'] ? [
                                    'value' => [
                                        $discount_type => $discount_type === "percentage" ? (float)$plan['discount'] / 100 : $plan['discount'],
                                    ],
                                    'durationLimitInIntervals' => ($plan['discount_interval'] ?? null)
                                ] : [],
                                'interval' => $plan['interval']['value'],
                            ]),
                        ],
                    ],
                ],
            ];

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

        $shop = DB::table($tableName)->where($storeName, $request->shop)->first();
        $apiVersion = config('app-manager.shopify_api_version');

        $charge = Client::withHeaders(["X-Shopify-Access-Token" => $shop->$storeToken])
            ->get("https://{$shop->$storeName}/admin/api/$apiVersion/recurring_application_charges/{$request->charge_id}.json")->json();

        $plan = \AppManager::getPlan($request->plan, $shop->id);

        if (!empty($charge['recurring_application_charge'])) {

            $charge = $charge['recurring_application_charge'];
            $charge['charge_id']  = $charge['id'];
            $charge['type'] = 'recurring';
            $charge['plan_id'] = $request->plan;
            $charge['shop_domain'] = $request->shop;
            $charge['interval'] = $plan['interval']['value'];

            if (!empty($shop->$storePlanField)) {

                \AppManager::cancelCharge($request->shop, $shop->$storePlanField);
            }

            unset($charge['api_client_id'], $charge['return_url'], $charge['decorated_return_url'], $charge['id']);
            $data = \AppManager::storeCharge($charge);

            if ($data['message'] === "success") {

                DB::table($tableName)->where($storeName, $request->shop)->update([$storePlanField => $request->plan]);

                event(new PlanActivated($plan, $charge));
            }
        } else throw new ChargeException("Invalid charge");


        return \redirect()->route('home',['shop' => $shop->$storeName]);
    }
}