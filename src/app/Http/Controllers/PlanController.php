<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function index() {

        $features = config('plan_features');

        return response()->json(['features' => $features]);
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