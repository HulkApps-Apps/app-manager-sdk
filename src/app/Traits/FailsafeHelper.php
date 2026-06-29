<?php

namespace HulkApps\AppManager\app\Traits;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

trait FailsafeHelper {

    public function prepareMarketingBanners() {
        $marketingBannersData = DB::connection('app-manager-failsafe')->table('marketing_banners')->get()->toArray();
        return head($marketingBannersData)->marketing_banners ?? null;
    }

    public function prepareAppFaqs()
    {
        return DB::connection('app-manager-failsafe')
            ->table('app_faqs')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    public function preparePlans($shop_domain, $active_plan_id = null, $shopify_plan = null) {

        $activeChargePrice = $activePlanId = null;
        $plansData = DB::connection('app-manager-failsafe')->table('plans')->get();
        $activeChargeData = DB::connection('app-manager-failsafe')->table('charges')
            ->where('shop_domain', $shop_domain)->where('status', 'active')->get()->toArray();
        if (!empty($activeChargeData)) {
            $activePlanId = collect($activeChargeData)->pluck('plan_id')->first();
            $activeChargePrice = collect($activeChargeData)->pluck('price')->first();
        }
        elseif ($active_plan_id) {
            $activePlanId = $active_plan_id;
            $activeChargePrice = collect($plansData)->where('id', $activePlanId)->pluck('price')->first();
        }

        $customPlanIds = DB::connection('app-manager-failsafe')->table('plan_user')
            ->where('shop_domain', $shop_domain)->where('used', false)->pluck('plan_id')->toArray();
        array_push($customPlanIds, $activePlanId ?? null);
        $customPlanBaseIds = DB::connection('app-manager-failsafe')->table('plans')
            ->whereIn('id', $customPlanIds)->whereNotNull('base_plan')->pluck('base_plan')->toArray();

        if ($activePlanId && ($key = array_search($activePlanId, $customPlanBaseIds)) !== false) {
            unset($customPlanBaseIds[$key]);
        }

        $plans = DB::connection('app-manager-failsafe')->table('plans')->where(function ($query) use ($customPlanIds) {
            $query->where('public', 1)
                ->orWhereIn('id', $customPlanIds);
        })->when(!empty($customPlanBaseIds), function ($query) use ($customPlanBaseIds) {
            $query->whereNotIn('id', $customPlanBaseIds);
        })->get()->toArray();

        $featuresByPlans = collect($plans)->pluck('feature_plan')->toArray();
        $temp = [];
        foreach ($featuresByPlans as $index => $featuresByPlan) {
            $featuresByPlans[$index] = json_decode($featuresByPlan);
            foreach ($featuresByPlans[$index] as $feature) {
                $temp[] = $feature;
            }
        }
        $featuresByPlans = $temp;
        if ($featuresByPlans) {
            $features = config('plan_features');

            if ($features) {
                $features = collect($features)->keyBy('uuid')->toArray();
                foreach ($featuresByPlans as $key => $featuresByPlan) {
                    $featuresByPlans[$key]->name = $features[$featuresByPlan->feature_id]['name'] ?? null;
                    $featuresByPlans[$key]->format = $features[$featuresByPlan->feature_id]['format'] ?? null;
                    $featuresByPlans[$key]->slug = $features[$featuresByPlan->feature_id]['slug'] ?? null;
                    $featuresByPlans[$key]->name = $features[$featuresByPlan->feature_id]['name'] ?? null;
                }
            }
            $featuresByPlans = collect($featuresByPlans)->groupBy('plan_id')->toArray();
        }

        $detailsByPlans = !empty($plans)
            ? collect($plans)->pluck('details', 'id')
                ->map(function ($details) {
                    return json_decode($details, true) ?? [];
                })->toArray()
            : [];

        $customDiscounts = DB::connection('app-manager-failsafe')->table('discount_plan')->where('shop_domain', $shop_domain)
            ->where('used', false)
            ->orderByDesc('created_at')->get(['plan_id','discount', 'discount_type', 'cycle_count'])->first();
        if ($customDiscounts) {
            $customDiscounts = json_decode(json_encode($customDiscounts), true);
            $customDiscounts = [
                $customDiscounts['plan_id'] => $customDiscounts
            ];
        }

        $plans = json_decode(json_encode($plans), true);
        foreach ($plans as $key => $plan) {
            if ($activePlanId && $plan['id'] == $activePlanId) {
                $plans[$key]['price'] = $activeChargePrice;
            }
            $plans[$key]['interval'] = json_decode($plan['interval'], true)['value'];
            $plans[$key]['shopify_plans'] = collect(json_decode($plan['shopify_plans'], true))->pluck('value')->toArray();
            $plans[$key]['features'] = isset($featuresByPlans[$plan['id']]) ? collect($featuresByPlans[$plan['id']])->keyBy('feature_id')->toArray() : null;
            $plans[$key]['details'] = $detailsByPlans[$plan['id']] ?? [];;
            $index = isset($customDiscounts[$plan['id']]) ? $plan['id'] : (isset($customDiscounts[-1]) ? -1 : null);
            if ($index) {
                $plans[$key]['discount'] = $customDiscounts[$index]['discount'];
                $plans[$key]['discount_type'] = $customDiscounts[$index]['discount_type'];
                $plans[$key]['cycle_count'] = $customDiscounts[$index]['cycle_count'];
                $plans[$key]['discount_is_custom'] = true;
            }

            $plans[$key]['fail_safe_response'] = true;
        }

        return $this->filterPlansByShopifyPlan($plans, $shopify_plan, $customPlanIds);
    }

    public function preparePlan($data) {
        $planId = $data['plan_id'];
        $shopDomain = $data['shop_domain'] ?? null;

        $planData = DB::connection('app-manager-failsafe')->table('plans')->where('id', $planId)->first();
        $planData = json_decode(json_encode($planData), true);

        if ($planData && $shopDomain) {
            $customDiscounts = DB::connection('app-manager-failsafe')->table('discount_plan')
                ->where('shop_domain', $shopDomain)->where('used', false)->select(['plan_id', 'discount', 'discount_type', 'cycle_count'])->first();
            $customDiscounts = json_decode(json_encode($customDiscounts), true);
            if (!empty($customDiscounts) && ($customDiscounts['plan_id'] == -1 || $planData['id'] == $customDiscounts['plan_id'])) {
                $planData['discount'] = !empty($customDiscounts['discount']) ? $customDiscounts['discount'] : $planData['discount'];
                $planData['discount_type'] = !empty($customDiscounts['discount_type']) ? $customDiscounts['discount_type'] : $planData['discount_type'];
                $planData['cycle_count'] = !empty($customDiscounts['cycle_count']) ? $customDiscounts['cycle_count'] : $planData['cycle_count'];
                $planData['discount_is_custom'] = true;
            }
        }

        $planData['features'] = $planData['feature_plan'];
        unset($planData['feature_plan']);

        return $this->unSerializeData($planData);
    }

    public function prepareDiscount($data) {
        $code =  hex2bin($data['code']);
        $shopDomain = $data['shop_domain'];
        $now = now();

        $discountData = DB::connection('app-manager-failsafe')->table('discounts')
            ->where('enabled', true)
            ->whereNull('deleted_at')
            ->where('valid_from', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $now);
            })
            ->where('code', $code)
            ->first();

        if (empty($discountData)) {
            return [];
        }

        $discountShop = DB::connection('app-manager-failsafe')->table('discount_shops')
            ->where('discount_id', $discountData->id)
            ->count();

        $discountPlan = DB::connection('app-manager-failsafe')->table('discount_plans')
            ->where('discount_id', $discountData->id)
            ->get()->pluck('plan_id')->toArray();

        $discountUsage = DB::connection('app-manager-failsafe')->table('discounts_usage_log')
            ->where('discount_id', $discountData->id)
            ->count();

        $discountUsageByDomain = DB::connection('app-manager-failsafe')->table('discounts_usage_log')
            ->where('discount_id', $discountData->id)
            ->where('domain', $shopDomain)
            ->count();

        if ($discountShop > 0) {
            $discountShopSpecific = DB::connection('app-manager-failsafe')->table('discount_shops')
                ->where('discount_id', $discountData->id)
                ->where('domain', $shopDomain)
                ->first();
            if(empty($discountShopSpecific)){
                return [];
            }
        }

        if ($discountData->max_usage !== null
            && $discountData->max_usage !== 0
        ) {
            if ($discountUsage >= $discountData->max_usage) {
                return [];
            }
        }

        if (
            $discountData->multiple_uses === 0
            && !empty($discountUsageByDomain)
        ) {
            return [];
        }

        if ($discountData->multiple_apps === 0)
        {
            $discountUsageByApp = DB::connection('app-manager-failsafe')->table('discounts_usage_log')
                ->where('discount_id', $discountData->id)
                ->where('domain', $shopDomain)
                ->where('app_id', '!=', $discountData->app_id)
                ->first();
            if ($discountUsageByApp) {
                return [];
            }
        }

        $discountData = json_decode(json_encode($discountData), true);
        $discountData['plan_relation'] = $discountPlan;
        return $this->unSerializeData($discountData);
    }

    public function prepareRelatedDiscountedPlans($discount_id) {
        $discountPlans = DB::connection('app-manager-failsafe')->table('discount_plans')
            ->where('discount_id', $discount_id)->get()->pluck('plan_id')->toArray();
        return $discountPlans;
    }

    public function prepareRemainingDays($data) {
        $trialActivatedAt = $data['trial_activated_at'];
        $planId = $data['plan_id'];
        $shopDomain = $data['shop_domain'];

        if (empty($planId)) {
            return null;
        }

        $planTrialDays = (int) (DB::connection('app-manager-failsafe')->table('plans')
            ->where('id', $planId)->value('trial_days') ?? 0);

        // Extension stack - sum every record for this plan.
        $extensionDays = (int) DB::connection('app-manager-failsafe')->table('trial_extension')
            ->where('shop_domain', $shopDomain)->where('plan_id', $planId)->sum('days');

        $consumed = $this->prepareConsumedTrialDays($shopDomain, $trialActivatedAt);

        return max(0, ($planTrialDays + $extensionDays) - $consumed);
    }

    /**
     * Offline mirror of API/PlanController::consumedTrialDays(). Total trial days
     * already consumed, excluding time spent on a free plan (paused via
     * charge.cancelled_on). Derived from existing tables only.
     */
    public function prepareConsumedTrialDays($shopDomain, $trialActivatedAt = null): int
    {
        $windows = [];
        $earliestChargeStart = null;

        $charges = DB::connection('app-manager-failsafe')->table('charges')
            ->where('shop_domain', $shopDomain)
            ->where('trial_days', '>', 0)
            ->whereNotNull('trial_ends_on')
            ->get();

        foreach ($charges as $charge) {
            $trialEnd = Carbon::parse($charge->trial_ends_on);
            $start = $trialEnd->copy()->subDays((int) $charge->trial_days);

            if ($earliestChargeStart === null || $start->lt($earliestChargeStart)) {
                $earliestChargeStart = $start;
            }

            $end = now();
            if (!empty($charge->cancelled_on)) {
                $cancelledOn = Carbon::parse($charge->cancelled_on);
                if ($cancelledOn->lt($end)) {
                    $end = $cancelledOn;
                }
            }
            if ($trialEnd->lt($end)) {
                $end = $trialEnd;
            }
            if ($end->gt($start)) {
                $windows[] = [$start, $end];
            }
        }

        if (!empty($trialActivatedAt)) {
            $chooseLaterPlan = DB::connection('app-manager-failsafe')->table('plans')
                ->where('choose_later_plan', true)
                ->orderByDesc('id')
                ->first();

            if ($chooseLaterPlan && (int) $chooseLaterPlan->trial_days > 0) {
                $extensionDays = (int) DB::connection('app-manager-failsafe')->table('trial_extension')
                    ->where('shop_domain', $shopDomain)
                    ->where('plan_id', $chooseLaterPlan->id)
                    ->sum('days');

                $length = (int) $chooseLaterPlan->trial_days + $extensionDays;
                $start = Carbon::parse($trialActivatedAt);
                $naturalEnd = $start->copy()->addDays($length);
                $end = now()->lt($naturalEnd) ? now() : $naturalEnd;
                // No-charge trial ends when the first charge starts.
                if ($earliestChargeStart !== null && $earliestChargeStart->lt($end)) {
                    $end = $earliestChargeStart;
                }
                if ($end->gt($start)) {
                    $windows[] = [$start, $end];
                }
            }
        }

        if (empty($windows)) {
            return 0;
        }

        usort($windows, function ($a, $b) {
            return $a[0] <=> $b[0];
        });

        $total = 0;
        [$curStart, $curEnd] = $windows[0];

        foreach (array_slice($windows, 1) as [$start, $end]) {
            if ($start->lte($curEnd)) {
                if ($end->gt($curEnd)) {
                    $curEnd = $end;
                }
            } else {
                $total += $curStart->diffInDays($curEnd);
                [$curStart, $curEnd] = [$start, $end];
            }
        }
        $total += $curStart->diffInDays($curEnd);

        return (int) $total;
    }

    public function getChargeHelper($shop_domain) {
        $chargeData = DB::connection('app-manager-failsafe')->table('charges')
            ->where('shop_domain', $shop_domain)->get();

        $activeCharge = collect($chargeData->where('status', 'active')->first())->toArray();
        if (!empty($activeCharge)) {
            $activeCharge = array_merge($activeCharge, $this->effectiveChargePricing($activeCharge));
        }

        return [
            'active_charge' => $activeCharge,
            'cancelled_charge' => collect($chargeData->where('status', 'cancelled')->sortByDesc('created_at')->first())->toArray()
        ];
    }

    public function effectiveChargePricing(array $charge): array
    {
        $base = round((float) ($charge['price'] ?? 0), 2);

        $noDiscount = [
            'effective_price' => $base,
            'strike_price' => null,
            'is_discount_active' => false,
            'remaining_intervals' => null,
            'discount_ends_on' => null,
        ];

        try {
            $now = Carbon::now();

            $value = $charge['discount_value'] ?? null;
            if (empty($value) || (float) $value <= 0) {
                return $noDiscount;
            }

            $type = $charge['discount_type'] ?? 'percentage';
            $discounted = $type === 'percentage'
                ? $base - ($base * (float) $value / 100)
                : $base - (float) $value;
            $discounted = round(max(0, $discounted), 2);

            $duration = (int) ($charge['discount_duration_intervals'] ?? 0);

            if ($duration <= 0) {
                return [
                    'effective_price' => $discounted,
                    'strike_price' => $base,
                    'is_discount_active' => true,
                    'remaining_intervals' => null,
                    'discount_ends_on' => null,
                ];
            }

            $anchorValue = $charge['trial_ends_on'] ?? $charge['activated_on'] ?? $charge['created_at'] ?? null;
            $anchor = $anchorValue ? Carbon::parse($anchorValue) : $now->copy();

            $intervalDays = ($charge['interval'] ?? null) === 'ANNUAL' ? 365 : 30;
            $elapsed = (int) floor(max(0, $anchor->diffInDays($now, false)) / $intervalDays);
            $endsOn = $anchor->copy()->addDays($duration * $intervalDays)->toDateString();

            if ($elapsed >= $duration) {
                return [
                    'effective_price' => $base,
                    'strike_price' => null,
                    'is_discount_active' => false,
                    'remaining_intervals' => 0,
                    'discount_ends_on' => $endsOn,
                ];
            }

            return [
                'effective_price' => $discounted,
                'strike_price' => $base,
                'is_discount_active' => true,
                'remaining_intervals' => $duration - $elapsed,
                'discount_ends_on' => $endsOn,
            ];
        } catch (\Throwable $e) {
            report($e);

            return $noDiscount;
        }
    }

    public function storeChargeHelper($data) {
        $data['sync'] = false;
        $data['process_type'] = 'store-charge';
        unset($data['capped_amount'], $data['balance_used'], $data['balance_remaining'], $data['risk_level']);
        $charge = DB::connection('app-manager-failsafe')->table('charges')->insert($data);
        return ['message' => $charge ? 'success' : 'fail'];
    }

    public function cancelChargeHelper($shop_domain, $plan_id) {
        $charge = DB::connection('app-manager-failsafe')->table('charges')
            ->where('shop_domain', $shop_domain)->where('plan_id', $plan_id)
            ->update([
                'status' => 'cancelled',
                'cancelled_on' => Carbon::now(),
            ]);
        return ['message' => $charge ? 'success' : 'fail'];
    }

    public function storePromotionalDiscountHelper($shop, $discount_id){
        $data['discount_id'] = $discount_id;
        $data['domain'] = $shop;
        $data['sync'] = false;
        $data['process_type'] = 'use-discount';
        $data['app_id'] = DB::connection('app-manager-failsafe')->table('discounts')->where('id', $discount_id)->pluck('app_id')->first();
        $discountUsageLog = DB::connection('app-manager-failsafe')->table('discounts_usage_log')->insert($data);
        return ['message' => $discountUsageLog ? 'success' : 'fail'];
    }

    public function syncAppManager()
    {
        $status = false;
        try {
            $status = DB::connection('app-manager-failsafe')->getPdo() &&
                DB::connection('app-manager-failsafe')->getDatabaseName() &&
                \Schema::connection('app-manager-failsafe')->hasTable('charges') &&
                \Schema::connection('app-manager-failsafe')->hasTable('discounts_usage_log');
        }
        catch (\Exception $extends){
            $status = false;
        }
        if(!$status){
            $this->initializeFailsafeDBFullWipe();
            $status = true;
        }

        if ($status) {
            $response = \AppManager::getStatus();
            if ($response->getStatusCode() == 200) {
                $charges = DB::connection('app-manager-failsafe')->table('charges')
                    ->where('sync', 0)->where('process_type', 'store-charge')->get()->toArray();

                $discountsUsageLog = DB::connection('app-manager-failsafe')->table('discounts_usage_log')
                    ->where('sync', 0)->where('process_type', 'use-discount')->get()->toArray();

                if ($charges) {
                    foreach ($charges as $charge) {
                        $charge = json_decode(json_encode($charge), true);

                        $response = \AppManager::syncCharge($charge);
                        if ($response) {
                            DB::connection('app-manager-failsafe')->table('charges')
                                ->where('charge_id', $charge['charge_id'])->update([
                                    'sync' => 1,
                                    'process_type' => null
                                ]);
                        }
                    }
                }

                if ($discountsUsageLog) {
                    foreach ($discountsUsageLog as $discountUsageLog) {
                        $discountUsageLog = json_decode(json_encode($discountUsageLog), true);

                        $response = \AppManager::syncDiscountUsageLog(['shop_domain' => $discountUsageLog['domain'], 'discount_id' => (int) $discountUsageLog['discount_id']]);

                        if ($response) {
                            DB::connection('app-manager-failsafe')->table('discounts_usage_log')
                                ->where('id', $discountUsageLog['id'])->update([
                                    'sync' => 1,
                                    'process_type' => null
                                ]);
                        }
                    }
                }
            }
        }
    }

    public function initializeFailsafeDBFullWipe() {
        $db = DB::connection('app-manager-failsafe');
        $database = $db->getConfig('database');
        if(!empty($database)){
            Artisan::call('migrate:fresh', ['--force' => true,'--database' => 'app-manager-failsafe', '--path' => "/vendor/hulkapps/appmanager/migrations"]);
        }
    }

    public function initializeFailsafeDB() {
        $db = DB::connection('app-manager-failsafe');
        if(!empty($db->getConfig('database'))){
            Artisan::call('migrate', [
                '--force' => true,
                '--database' => 'app-manager-failsafe',
                '--path' => "/vendor/hulkapps/appmanager/migrations"
            ]);
        }
    }

    public function serializeData ($data) {
        if (gettype($data) == 'array' || gettype($data) == 'object') {
            foreach ($data as $index => $datum) {
                if (gettype($datum) == 'array') {
                    $data[$index] = json_encode($datum);
                }
            }
        }
        return $data;
    }

    public function unSerializeData ($data) {
        foreach ($data as $index => $datum) {
            if (in_array($index, ['interval', 'shopify_plans', 'affiliate', 'features', 'details'])) {
                $data[$index] = json_decode($datum, true);
            }
        }
        return $data;
    }

    public function hasPlanHelper($data){
        if (boolval($data['grandfathered'])) {
            return ['has_plan' => true];
        }
        $planPrice = DB::connection('app-manager-failsafe')->table('plans')
            ->where('id',$data['plan_id'])->pluck('price')->first();
        if ($planPrice && $planPrice == 0) {
            return ['has_plan' => true];
        }

        $remainingDays = $this->prepareRemainingDays([
            'trial_activated_at' => $data['trial_activated_at'],
            'plan_id' => $data['plan_id'],
            'shop_domain' => $data['shop_domain']
        ]);
        if ($remainingDays && $remainingDays > 0) {
            return ['has_plan' => true];
        }

        $activeCharge = DB::connection('app-manager-failsafe')->table('charges')
            ->where('shop_domain',$data['shop_domain'])->where('status','active')->get()->toArray();
        if (!empty($activeCharge)) {
            return ['has_plan' => true];
        }

        return ['has_plan' => false];
    }

    private function filterPlansByShopifyPlan(array $plans, $shopify_plan, array $customPlanIds): array
    {
        if (!$shopify_plan) {
            return $plans;
        }

        $filterTierList = DB::connection('app-manager-failsafe')
            ->table('app_filters')
            ->pluck('shopify_plans')
            ->flatMap(fn ($json) => json_decode($json, true) ?? [])
            ->all();

        if (empty($filterTierList)) {
            return $plans;
        }

        $merchantInFilter = in_array(trim($shopify_plan), $filterTierList, true);
        $customPlanIds = array_filter($customPlanIds, fn ($id) => $id !== null);

        return array_values(array_filter($plans, function ($plan) use ($customPlanIds, $merchantInFilter, $filterTierList) {
            if (in_array($plan['id'], $customPlanIds, true)) {
                return true;
            }
            if (!empty($plan['public']) && (float)($plan['price'] ?? -1) == 0) {
                return true;
            }

            $shopifyPlans = $plan['shopify_plans'] ?? [];
            $planInTiers = !empty($shopifyPlans) && count(array_intersect($filterTierList, $shopifyPlans)) > 0;

            return $merchantInFilter ? $planInTiers : !$planInTiers;
        }));
    }
}
