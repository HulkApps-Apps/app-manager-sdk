<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ChargeController extends Controller
{
    public function process(Request $request,$plan_id) {

        $tableName = config('app-manager.shop_table_name', 'users');
        $storeFieldName = config('app-manager.field_names.name', 'name');

        $shop = DB::table($tableName)->where($storeFieldName, $request->shop)->firstOrFail();

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

        $trialDays = $plan['trial_days'];

        $variables = [
            'name' => $plan['name'],
            'returnUrl' => '',
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
                                    'percentage' => (float)$plan['discount'],
                                    'durationLimitInIntervals' => (int)$plan['discount_interval']
                                ]
                            ] : [],
                            'interval' => $plan['interval']['value'],
                        ],
                    ],
                ],
            ],
        ];

        return response()->json(['plan' => $variables]);
    }

    public function plans(Request $request) {

        $activePlanId = null;
        $tableName = config('app-manager.shop_table_name', 'users');
        $storeFieldName = config('app-manager.store_field_name', 'name');
        $planFieldName = config('app-manager.plan_field_name', 'plan_id');

        if ($request->has('store_domain')) {
            $storeDomain = $request->get('store_domain');
            $activePlanId = DB::table($tableName)->where($storeFieldName, $storeDomain)->pluck($planFieldName)->first();
        }

        $plans = \AppManager::getPlans();

        $response = [
            'plans' => $plans,
            'active_plan_id' => $activePlanId
        ];

        return response()->json($response);
    }

    public function storeCharge(Request $request) {

        $res = \AppManager::storeCharge($request);

        return response()->json($res->getData(), $res->getStatusCode());
    }
}