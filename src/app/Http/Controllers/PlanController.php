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

    public function plans() {

        $storeName = 'demo-anil.myshopify.com';

        $tableName = config('app-manager.table_name', 'users');
        $storeFieldName = config('app-manager.store_field_name', 'name');
        $planFieldName = config('app-manager.plan_field_name', 'plan_id');

        $activePlan = DB::table($tableName)->where($storeFieldName, $storeName)->pluck($planFieldName)->first();

        $plans = \AppManager::getPlans();

        $response = [
            'plans' => $plans,
            'active_plan_id' => $activePlan
        ];

        return response()->json($response);
    }

    public function planCharge(Request $request) {
        $request->validate([
            'charge_id' => 'required|number',
            'plan_id' => 'required|number',
            'user_id' => 'required|number',
            'activated_on' => 'required',
        ]);
    }
}