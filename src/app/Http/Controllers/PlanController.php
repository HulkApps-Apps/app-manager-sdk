<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index() {

        $features = config('plan_features');

        return response()->json(['features' => $features]);
    }

    public function plans(Request $request) {

        $activePlanId = null;
        $tableName = config('app-manager.shop_table_name', 'users');
        $storeFieldName = config('app-manager.field_names.name', 'name');
        $planFieldName = config('app-manager.field_names.plan_id', 'plan_id');

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

    public function plan(Request $request) {
        $plan = \AppManager::getPlan($request->all());
        return response()->json($plan);
    }

    public function users(Request $request) {
        $tableName = config('app-manager.shop_table_name', 'users');
        $shopify_fields = config('app-manager.field_names');
        $users = DB::table($tableName)->get();

        $users->map(function ($user) use ($shopify_fields) {
            foreach ($shopify_fields as $key => $shopify_field) {
                if ($key !== $shopify_field) {
                    $user->{$key} = $user->{$shopify_field};
                }
            }
            return $user;
        });
        return response()->json($users, 200);
    }

    public function storeCharge(Request $request) {

        $res = \AppManager::storeCharge($request);

        return response()->json($res->getData(), $res->getStatusCode());
    }
}