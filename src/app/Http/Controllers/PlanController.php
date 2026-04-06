<?php

namespace HulkApps\AppManager\app\Http\Controllers;

use Carbon\Carbon;
use HulkApps\AppManager\app\Traits\FailsafeHelper;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use function HulkApps\AppManager\app\appManagerCacheData;
use function HulkApps\AppManager\app\deleteAppManagerCache;
use function HulkApps\AppManager\app\isValidUser;
use Composer\InstalledVersions;

class PlanController extends Controller
{
    use FailsafeHelper;
    public function index() {

        $features = config('plan_features');

        return response()->json(['features' => $features]);
    }

    public function plans(Request $request) {

        $mostPopularPlanIdsRaw = config('app-manager.most_popular_plan_ids', []);
        $mostPopularPlanIds = [];
        if (!empty($mostPopularPlanIdsRaw) && is_array($mostPopularPlanIdsRaw)) {
            foreach ($mostPopularPlanIdsRaw as $v) {
                if ($v !== '' && is_numeric($v)) {
                    $mostPopularPlanIds[] = (int) $v;
                }
            }
            $mostPopularPlanIds = array_values(array_unique($mostPopularPlanIds));
        }

        $shopTableName = config('app-manager.shop_table_name', 'users');
        $storeFieldName = config('app-manager.field_names.name', 'name');
        $planFieldName = config('app-manager.field_names.plan_id', 'plan_id');
        $shopifyPlanFieldName = config('app-manager.field_names.shopify_plan', 'shopify_plan');
        $cacheKey = $request->has('shop_domain') ? 'app-manager.plans-'.$request->get('shop_domain') : 'app-manager.all-plans';

        $response = appManagerCacheData($cacheKey, function () use ($request, $shopTableName, $storeFieldName, $planFieldName, $shopifyPlanFieldName, $cacheKey, $mostPopularPlanIds) {
            $shopify_plan = $plan = $plans = $trialActivatedAt = null;
            $choose_later = false;

            if ($request->has('shop_domain')) {
                $shopDomain = $request->get('shop_domain');
                $userData = DB::table($shopTableName)->where($storeFieldName, $shopDomain)->get();
                $shopify_plan = collect($userData)->pluck($shopifyPlanFieldName)->first();
                $activePlanId = collect($userData)->pluck($planFieldName)->first() ?? null;
                $plans = \AppManager::getPlans($shopDomain, $activePlanId);
                $plan = collect($plans)->where('id', $activePlanId)->first();
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


            return [
                'plans' => $plans,
                'promotional_discount' => $promotionalDiscount,
                'shopify_plan' => $shopify_plan,
                'plan' => $plan,
                'default_plan_id' => $defaultPlanId,
                'most_popular_plan_ids' => $mostPopularPlanIds,
                'choose_later' => $choose_later,
                'has_active_charge' => (isset($activeCharge['active_charge']) && !empty($activeCharge['active_charge'])) || !$trialActivatedAt,
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
        unset($shopify_fields['shopify_token']);
        $selectedFields = array_values($shopify_fields);
        if (empty($selectedFields)) {
            $selectedFields = ['name', 'plan_id', 'created_at', 'shopify_plan', 'trial_activated_at'];
        }

        $users = DB::table($tableName)
            ->select($selectedFields)
            ->when($search, function ($q) use ($shopify_fields, $search) {
            return $q->where(($shopify_fields['name'] ?? 'name'), 'like', '%'.$search.'%')
                ->orWhere(($shopify_fields['shopify_email'] ?? 'shopify_email'), 'like', '%'.$search.'%');
        })->when($plans, function ($q) use ($shopify_fields, $plans) {
            return $q->whereIn(($shopify_fields['plan_id'] ?? 'plan_id'), $plans);
        })->when($shopify_plans, function ($q) use ($shopify_fields, $shopify_plans) {
            return $q->whereIn(($shopify_fields['shopify_plan'] ?? 'shopify_plan'), $shopify_plans);
        })->orderBy($sort, $order)->paginate($itemsPerPage);

        $users->getCollection()->transform(function ($user) use ($shopify_fields) {
            foreach ($shopify_fields as $key => $shopify_field) {
                if ($key !== $shopify_field && isset($user->{$shopify_field})) {
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

    public function failSafeBackup(Request $request)
    {
        $backupType = $request->input('backup_type', 'full');

        if ($backupType === 'incremental') {
             $this->failSafeIncrementalBackup($request);
        }else{
            $this->rebuildFailsafe($request);
        }

    }

    public function failSafeIncrementalBackup(Request $request)
    {
        // sync pending charges with app manager
        try {
            $this->syncAppManager();
        } catch (\Exception $e) {
            report($e);
        }

        $syncType = $request->input('sync_type');
        $payload  = $request->input('payload');

        $this->initializeFailsafeDB();

        $dateFields = ['created_at', 'updated_at', 'deleted_at', 'valid_from', 'valid_to', 'cancelled_on'];

        switch ($syncType) {
            case 'plans':
                if (isset($payload['features'])) {
                    $payload['feature_plan'] = $payload['features'];
                    unset($payload['features']);
                }
                $filteredPlans = $this->filterData($payload, $dateFields);
                DB::connection('app-manager-failsafe')->table('plans')->updateOrInsert(['id' => $filteredPlans['id']], $filteredPlans);
                break;

            case 'plan-delete':
                DB::connection('app-manager-failsafe')->table('plans')->where('id', $payload['id'])->delete();
                break;

            case 'plan-user-delete':
                DB::connection('app-manager-failsafe')->table('plan_user')->where('shop_domain', $payload['shop_domain'])->delete();
                break;

            case 'charges':
                $filteredCharges = $this->filterData($payload, $dateFields);
                DB::connection('app-manager-failsafe')->table('charges')->updateOrInsert(['id' => $filteredCharges['id']], $filteredCharges);
                break;

            case 'charges-cancel':
                DB::connection('app-manager-failsafe')
                    ->table('charges')
                    ->where('shop_domain', $payload['shop_domain'])
                    ->update([
                        'status' => 'cancelled',
                        'cancelled_on' => $this->formatDate($payload['cancelled_on'] ?? now()->toDateTimeString()),
                        'updated_at' => $this->formatDate(now())
                    ]);
                break;

            case 'banners':
                DB::connection('app-manager-failsafe')
                    ->table('marketing_banners')
                    ->updateOrInsert(
                        ['id' => 1],
                        [
                            'marketing_banners' => is_string($payload) ? $payload : json_encode($payload),
                            'updated_at' => now(),
                            'created_at' => DB::raw('IFNULL(created_at, NOW())')
                        ]
                    );
                break;

            case 'promotional-discounts':
                $filteredDiscount = $this->filterData($payload, $dateFields, ['pivot']);

                $mainData = collect($filteredDiscount)->forget(['shops_relation', 'apps_relation', 'plans_relation', 'usage_relation'])->toArray();

                DB::connection('app-manager-failsafe')->table('discounts')->updateOrInsert(['id' => $mainData['id']], $mainData);

                $relations = [
                    'shops_relation' => 'discount_shops',
                    'plans_relation' => 'discount_plans',
                    'usage_relation' => 'discounts_usage_log'
                ];

                foreach ($relations as $key => $tableName) {
                    if (isset($payload[$key])) {
                        DB::connection('app-manager-failsafe')->table($tableName)->where('discount_id', $payload['id'])->delete();
                        $filteredRows = $this->filterData($payload[$key], $dateFields, ['pivot']);
                        DB::connection('app-manager-failsafe')->table($tableName)->insert($filteredRows);
                    }
                }
                break;

            case 'promotional-discounts-delete':
                DB::connection('app-manager-failsafe')
                    ->table('discounts')
                    ->where('id', $payload['id'])
                    ->update([
                        'deleted_at' => isset($payload['deleted_at']) ? $this->formatDate($payload['deleted_at']) : now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString()
                    ]);
                break;

            case 'promotional-discounts-app-removed':
                DB::connection('app-manager-failsafe')->table('discounts')->where('id', $payload['id'])->delete();
                DB::connection('app-manager-failsafe')->table('discount_shops')->where('discount_id', $payload['id'])->delete();
                DB::connection('app-manager-failsafe')->table('discounts_usage_log')->where('discount_id', $payload['id'])->delete();
                break;

            default:
                $tableMap = [
                    'plan-discount' => 'discount_plan',
                    'plan-user'     => 'plan_user',
                    'extend-trial'  => 'trial_extension',
                ];

                if (array_key_exists($syncType, $tableMap)) {
                    $tableName = $tableMap[$syncType];
                    $filteredData = $this->filterData($payload, $dateFields);
                    DB::connection('app-manager-failsafe')->table($tableName)->updateOrInsert(['id' => $filteredData['id']], $filteredData);
                } else {
                    \Log::error("Failsafe: Unhandled sync type: {$syncType}");
                }
                break;
        }
    }

    public function rebuildFailsafe(Request $request) {
        // sync pending charges with app manager
        try {
            $this->syncAppManager();
        }
        catch (\Exception $e) {
            report($e);
        }

        // initialize and reset failsafe database
        $this->initializeFailsafeDbFullWipe();

        $data = $request->all();
        $commanFields= [
            'created_at', 'updated_at'
        ];
        $marketingBanners = [
            'marketing_banners' => json_encode($data['app_structures'])
        ];
        DB::connection('app-manager-failsafe')->table('marketing_banners')->insert($marketingBanners);

        $plans = $this->filterDataForFullFailsafeBackup($data['plans'], ['created_at', 'updated_at','deleted_at']);
        foreach ($plans as $index => $plan) {
            $plans[$index] = $this->serializeData($plan);
            $plans[$index]['feature_plan'] = $plans[$index]['features'];
            unset($plans[$index]['features']);
        }
        //DB::connection('app-manager-failsafe')->table('plans')->insert($plans);
        $this->batchInsert('plans', $plans);

        $charges = $this->filterDataForFullFailsafeBackup($data['charges'],$commanFields);
        //DB::connection('app-manager-failsafe')->table('charges')->insert($charges);
        $this->batchInsert('charges', $charges);

        $discount_plans = $this->filterDataForFullFailsafeBackup($data['discount_plans'],$commanFields);
        //DB::connection('app-manager-failsafe')->table('discount_plan')->insert($discount_plans);
        $this->batchInsert('discount_plan', $discount_plans);

        $extend_trials = $this->filterDataForFullFailsafeBackup($data['extend_trials'],$commanFields);
        //DB::connection('app-manager-failsafe')->table('trial_extension')->insert($extend_trials);
        $this->batchInsert('trial_extension', $extend_trials);

        $plan_users = $this->filterDataForFullFailsafeBackup($data['plan_users'],$commanFields);
        //DB::connection('app-manager-failsafe')->table('plan_user')->insert($plan_users);
        $this->batchInsert('plan_user', $plan_users);

        $promotional_discounts = $this->filterDataForFullFailsafeBackup($data['promotional_discounts'],['valid_from','valid_to','created_at', 'updated_at','deleted_at'],false);
        //DB::connection('app-manager-failsafe')->table('discounts')->insert($promotional_discounts);
        $this->batchInsert('discounts', $promotional_discounts);

        $promotional_discounts_shops = $data['promotional_discounts_shops'];
        //DB::connection('app-manager-failsafe')->table('discount_shops')->insert($promotional_discounts_shops);
        $this->batchInsert('discount_shops', $promotional_discounts_shops);

        $promotional_discounts_plans = $data['promotional_discounts_plans'];
        //DB::connection('app-manager-failsafe')->table('discount_plans')->insert($promotional_discounts_plans);
        $this->batchInsert('discount_plans', $promotional_discounts_plans);

        $promotional_discounts_usage_log = $this->filterDataForFullFailsafeBackup($data['promotional_discounts_usage_log'],$commanFields,false);
        //DB::connection('app-manager-failsafe')->table('discounts_usage_log')->insert($promotional_discounts_usage_log);
        $this->batchInsert('discounts_usage_log', $promotional_discounts_usage_log);
    }

    public function filterData($data, $dateFields = [], $excludeKeys = ['app_id', 'pivot'])
    {
        $rows = $this->standardizeDataFormat($data);

        $processed = array_map(function ($row) use ($dateFields, $excludeKeys) {
            return $this->serializeRowForFailsafe((array) $row, $dateFields, $excludeKeys);
        }, $rows);

        return (isset($data['id']) || is_object($data)) ? $processed[0] : $processed;
    }

    private function serializeRowForFailsafe(array $row, array $dateFields, array $excludeKeys)
    {
        return collect($row)
            ->forget($excludeKeys)
            ->map(function ($value, $key) use ($dateFields) {
                if (in_array($key, $dateFields) && !empty($value)) {
                    return $this->formatDate($value);
                }

                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                }

                return $value;
            })
            ->toArray();
    }

    private function standardizeDataFormat($data)
    {
        if (isset($data['id']) || (is_object($data) && isset($data->id))) {
            return [$data];
        }
        return $data;
    }

    private function formatDate($value)
    {
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $value;
        }
    }

    public function filterDataForFullFailsafeBackup($data,$fields = [], $forgetAppId = true) {
        $data = collect($data)->map(function ($value, $key) use ($fields, $forgetAppId){
            if(!empty($fields)){
                foreach($fields as $field){
                    if(isset($value[$field])){
                        $value[$field] = \Carbon\Carbon::parse($value[$field])->format('Y-m-d H:i:s');
                    }
                }
            }
            return $forgetAppId ? collect($value)->forget('app_id')->toArray() : $value;
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

    public function getSdkVersions(Request $request)
    {
        try {
            $frontendVersion = $this->getFrontendSdkVersion($request->get('frontend_sdk'));
        } catch (\Throwable $e) {
            report($e);
            $frontendVersion = null;
        }

        return response()->json([
            'backend_sdk_version' => $this->getBackendSdkVersion($request->get('backend_sdk')),
            'frontend_sdk_version' => $frontendVersion,
        ]);
    }

    private function getBackendSdkVersion($package)
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            return null;
        }

        if (!\Composer\InstalledVersions::isInstalled($package)) {
            return null;
        }

        return \Composer\InstalledVersions::getPrettyVersion($package);
    }


    private function getFrontendSdkVersion($package)
    {
        $path = base_path('package-lock.json');
        if (!is_readable($path)) {
            return null;
        }

        $lock = json_decode((string) file_get_contents($path), true);
        if (!is_array($lock)) {
            return null;
        }

        // npm 7+ (lockfileVersion 2+)
        if (!empty($lock['packages']) && is_array($lock['packages'])) {
            $key = 'node_modules/'.$package;

            return $lock['packages'][$key]['version'] ?? null;
        }

        // lockfileVersion 1 — top-level "dependencies"
        return $lock['dependencies'][$package]['version'] ?? null;
    }

}
