<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Illuminate\Routing\Controller;

class PlanController extends Controller
{
    public function index() {

        $features = config('plan_features');

        return response()->json(['features' => $features]);
    }
}