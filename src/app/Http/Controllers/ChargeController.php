<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Carbon\Carbon;
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
        $storeFieldName = config('app-manager.field_names.name', 'name');

        $shop = DB::table($tableName)->where($storeFieldName, $request->shop)->first();

        if ($shop) {

            $plan = \AppManager::getPlan($plan_id, $shop->id);

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
            $requestData = ['shop' => $shop->$storeFieldName, 'timestamp' => now()->unix() * 1000, 'plan' => $plan_id];

            if ($shop->plan_id && $trialDays) {

                $trialDays = \AppManager::getRemainingDays($shop->$storeFieldName);
            }

            $variables = [
                'name' => $plan['name'],
                'returnUrl' => route('app-manager.plan.callback')."?".http_build_query($requestData, '', '&', PHP_QUERY_RFC3986),
                'trialDays' => $trialDays,
                'test' => $plan['test'],
                'lineItems' => [
                    [
                        'plan' => [
                            'appRecurringPricingDetails' => [
                                'price' => [
                                    'amount' => $plan['price'],
                                    'currencyCode' => 'USD',
                                ],
                                'discount' => $plan['discount'] ? [
                                    'value' => [
                                        'percentage' => (float)$plan['discount'] / 100,
                                    ],
                                    'durationLimitInIntervals' => ($plan['discount_interval'] ?? null)
                                ] : [],
                                'interval' => $plan['interval']['value'],
                            ],
                        ],
                    ],
                ],
            ];

            try {
                $response = GraphQL::shop(get_object_vars($shop))->query($query)->withParams($variables)->send();
            } catch (GraphQLException $exception) {

                report($exception);
                return response()->json(['error' => $exception->getMessage()], $exception->getCode());
            }

            return response()->json(['redirect_url' => $response['appSubscriptionCreate']['confirmationUrl']]);
        }

        throw new ModelNotFoundException("Shop $request->shop not found");
    }

    public function callback(Request $request)
    {

        $tableName = config('app-manager.shop_table_name', 'users');
        $storeFieldName = config('app-manager.field_names.name', 'name');
        $storeFieldName = config('app-manager.field_names.name', 'name');

        $shop = DB::table($tableName)->where($storeFieldName, $request->shop)->first();


//        $charge = Client::withHeaders(["X-Shopify-Access-Token" => $shop->])->get("/admin/{$typeString}s/{$chargeRef->toNative()}/activate.json")->json()


        try {
            \AppManager::storeCharge($request->all());
        } catch (ChargeException $chargeException) {
            report($chargeException);
        }

        return Redirect::route('home');
    }
}