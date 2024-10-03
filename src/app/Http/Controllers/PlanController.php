<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Carbon\Carbon;
use HulkApps\AppManager\app\Traits\FailsafeHelper;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use function HulkApps\AppManager\app\appManagerCacheData;
use function HulkApps\AppManager\app\deleteAppManagerCache;

class PlanController extends Controller
{
    use FailsafeHelper;
    public function index() {

        $features = config('plan_features');

        return response()->json(['features' => $features]);
    }

    public function plans(Request $request) {

        $shopTableName = config('app-manager.shop_table_name', 'users');
        $storeFieldName = config('app-manager.field_names.name', 'name');
        $planFieldName = config('app-manager.field_names.plan_id', 'plan_id');
        $shopifyPlanFieldName = config('app-manager.field_names.shopify_plan', 'shopify_plan');
        $cacheKey = $request->has('shop_domain') ? 'app-manager.plans-'.$request->get('shop_domain') : 'app-manager.all-plans';

        $response = appManagerCacheData($cacheKey, function () use ($request, $shopTableName, $storeFieldName, $planFieldName, $shopifyPlanFieldName, $cacheKey) {
            $shopify_plan = $plan = $globalPlan = $plans = $trialActivatedAt = null;
            $choose_later = false;

            if ($request->has('shop_domain')) {
                $shopDomain = $request->get('shop_domain');
                $userData = DB::table($shopTableName)->where($storeFieldName, $shopDomain)->get();
                $shopify_plan = collect($userData)->pluck($shopifyPlanFieldName)->first();
                $activePlanId = collect($userData)->pluck($planFieldName)->first() ?? null;
                $plans = \AppManager::getPlans($shopDomain, $activePlanId);
                $plan = collect($plans)->where('id', $activePlanId)->first();
                $globalPlan = collect($plans)->where('is_global', 1)->first() ?? null;

                $trialActivatedAt = collect($userData)->pluck(config('app-manager.field_names.trial_activated_at', 'trial_activated_at'))->first() ?? null;
                $activeCharge = \AppManager::getCharge($shopDomain);
                if (empty($activeCharge['cancelled_charge']) && empty($activeCharge['active_charge']) && !$trialActivatedAt && !$plan) {
                    $choose_later = true;
                }
            }

            $defaultPlanId = null;
            $defaultPlansData = collect($plans)->where('choose_later_plan', true);
            if ($defaultPlansData) {
                if ($defaultPlansData->where('store_base_plan', true)->count()) {
                    $shopify_plans = collect($plans)->where('interval', 'EVERY_30_DAYS');
                    foreach ($shopify_plans as $index => $s) {
                        if (in_array($shopify_plan, $s['shopify_plans'])) {
                            $defaultPlanId = $s['id'];
                            break;
                        }
                    }
                }
                else {
                    $defaultPlanId = $defaultPlansData->pluck('id')->first();
                }
            }

            $promotionalDiscount=[];
            if($request->has('discount_code')){
                $discountCode = $request->get('discount_code');
                if (!empty($discountCode)) {
                    $promotionalDiscount = \AppManager::getPromotionalDiscount($discountCode, $shopDomain);
                }
            }

            $appBundleData = \AppManager::getAppBundleData();

            return [
                'plans' => $plans,
                'promotional_discount' => $promotionalDiscount,
                'shopify_plan' => $shopify_plan,
                'plan' => $plan,
                'bundle_plan' => $globalPlan,
                'bundle_details' => $appBundleData,
                'default_plan_id' => $defaultPlanId,
                'choose_later' => $choose_later,
                'has_active_charge' => (isset($activeCharge['active_charge']) && !empty($activeCharge['active_charge'])) || !$trialActivatedAt
            ];
        });

        return response()->json($response);
    }

    public function users(Request $request) {

        $data = $request->all();
        $tableName = config('app-manager.shop_table_name', 'users');
        $shopify_fields = config('app-manager.field_names');
        $search = $data['search'] ?? null;
        $sort = $data['sort'] ?? $shopify_fields['created_at'];
        $order = $data['order'] ?? 'acs';
        $plans = $data['plans'] ?? null;
        $shopify_plans = $data['shopify_plans'] ?? null;
        $itemsPerPage = $data['itemsPerPage'] ?? 25;

        $users = DB::table($tableName)->when($search, function ($q) use ($shopify_fields, $search) {
            return $q->where(($shopify_fields['name'] ?? 'name'), 'like', '%'.$search.'%')
                ->orWhere(($shopify_fields['shopify_email'] ?? 'shopify_email'), 'like', '%'.$search.'%');
        })->when($plans, function ($q) use ($shopify_fields, $plans) {
            return $q->whereIn(($shopify_fields['plan_id'] ?? 'plan_id'), $plans);
        })->when($shopify_plans, function ($q) use ($shopify_fields, $shopify_plans) {
            return $q->whereIn(($shopify_fields['shopify_plan'] ?? 'shopify_plan'), $shopify_plans);
        })->orderBy($sort, $order)->paginate($itemsPerPage);

        $users->getCollection()->transform(function ($user) use ($shopify_fields) {
            foreach ($shopify_fields as $key => $shopify_field) {
                if ($key !== $shopify_field) {
                    $user->{$key} = $user->{$shopify_field};
                }
            }
            return $user;
        });

        return response()->json($users, 200);
    }

    public function activeWithoutPlan(Request $request) {
        $tableName = config('app-manager.shop_table_name', 'users');
        $shopify_fields = config('app-manager.field_names');
        $shop_domain = $request->get('shop_domain');
        $plan_id = $request->get('plan_id');
        if (!$shop_domain) {
            return response()->json(['message' => 'shop domain is required'], 422);
        }

        $updateInfo = [
            'plan_id' => $plan_id,
            'trial_activated_at' => Carbon::now()
        ];
        if(isset($shopify_fields['total_trial_days'])){
            $plan = \AppManager::getPlan($plan_id);
            if(!empty($plan)){
                $updateInfo[$shopify_fields['total_trial_days']] = $plan['trial_days']?? 0;
            }
        }
        $user = DB::table($tableName)->where($shopify_fields['name'], $request->get('shop_domain'))
            ->limit(1)->update($updateInfo);
        if ($user) {
            $this->burstCache($request);
            return response()->json(['status' => true]);
        }
        return response()->json(['status' => false], 422);
    }

    public function burstCache(Request $request) {
        deleteAppManagerCache();
        return true;
    }

    public function failSafeBackup(Request $request) {
        // sync pending charges with app manager
        try {
            $this->syncAppManager();
        }
        catch (\Exception $e) {
            report($e);
        }

        // initialize and reset failsafe database
        $this->initializeFailsafeDB();

        $data = $request->all();
        $commanFields= [
            'created_at', 'updated_at'
        ];
        $marketingBanners = [
            'marketing_banners' => json_encode($data['app_structures'])
        ];
        DB::connection('app-manager-failsafe')->table('marketing_banners')->insert($marketingBanners);

        $plans = $this->filterData($data['plans'], ['created_at', 'updated_at','deleted_at']);
        foreach ($plans as $index => $plan) {
            $plans[$index] = $this->serializeData($plan);
            $plans[$index]['feature_plan'] = $plans[$index]['features'];
            unset($plans[$index]['features']);
        }
        //DB::connection('app-manager-failsafe')->table('plans')->insert($plans);
        $this->batchInsert('plans', $plans);

        $charges = $this->filterData($data['charges'],$commanFields);
        //DB::connection('app-manager-failsafe')->table('charges')->insert($charges);
        $this->batchInsert('charges', $charges);

        $discount_plans = $this->filterData($data['discount_plans'],$commanFields);
        //DB::connection('app-manager-failsafe')->table('discount_plan')->insert($discount_plans);
        $this->batchInsert('discount_plan', $discount_plans);

        $extend_trials = $this->filterData($data['extend_trials'],$commanFields);
        //DB::connection('app-manager-failsafe')->table('trial_extension')->insert($extend_trials);
        $this->batchInsert('trial_extension', $extend_trials);

        $plan_users = $this->filterData($data['plan_users'],$commanFields);
        //DB::connection('app-manager-failsafe')->table('plan_user')->insert($plan_users);
        $this->batchInsert('plan_user', $plan_users);

        $promotional_discounts = $this->filterData($data['promotional_discounts'],['valid_from','valid_to','created_at', 'updated_at','deleted_at']);
        //DB::connection('app-manager-failsafe')->table('discounts')->insert($promotional_discounts);
        $this->batchInsert('discounts', $promotional_discounts);

        $promotional_discounts_shops = $data['promotional_discounts_shops'];
        //DB::connection('app-manager-failsafe')->table('discount_shops')->insert($promotional_discounts_shops);
        $this->batchInsert('discount_shops', $promotional_discounts_shops);

        $promotional_discounts_plans = $data['promotional_discounts_plans'];
        //DB::connection('app-manager-failsafe')->table('discount_plans')->insert($promotional_discounts_plans);
        $this->batchInsert('discount_plans', $promotional_discounts_plans);

        $promotional_discounts_usage_log = $this->filterData($data['promotional_discounts_usage_log'],$commanFields);
        //DB::connection('app-manager-failsafe')->table('discounts_usage_log')->insert($promotional_discounts_usage_log);
        $this->batchInsert('discounts_usage_log', $promotional_discounts_usage_log);
    }

    public function filterData($data,$fields = []) {
        $data = collect($data)->map(function ($value, $key) use ($fields){
            if(!empty($fields)){
                foreach($fields as $field){
                    if(isset($value[$field])){
                        $value[$field] = \Carbon\Carbon::parse($value[$field])->format('Y-m-d H:i:s');
                    }
                }
            }
            return collect($value)->forget('app_id')->toArray();
        })->toArray();
        return $data;
    }


    public function batchInsert($table, $data, $batchSize = 50) {
        if(empty($data)){
            return;
        }
        $connection = DB::connection('app-manager-failsafe');
        try {
            $chunks = array_chunk($data, $batchSize);
            foreach ($chunks as $chunk) {
                $connection->table($table)->insert($chunk);
            }
        } finally {
            DB::disconnect('app-manager-failsafe');
        }
    }

}
