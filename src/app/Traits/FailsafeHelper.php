<?php

namespace HulkApps\AppManager\app\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait FailsafeHelper {

    public function prepareMarketingBanners() {
        $marketingBannersData = DB::connection('app-manager-sqlite')->table('marketing_banners')->get()->toArray();
        return head($marketingBannersData)->marketing_banners ?? null;
    }

    public function preparePlans($shop_domain, $active_plan_id = null) {

        $activeChargePrice = $activePlanId = null;
        $plansData = DB::connection('app-manager-sqlite')->table('plans')
            ->where('shop_domain', $shop_domain)->get();
        $activeChargeData = DB::connection('app-manager-sqlite')->table('charges')
            ->where('shop_domain', $shop_domain)->where('status', 'active')->get()->toArray();
        if (!empty($activeChargeData)) {
            $activePlanId = collect($activeChargeData)->pluck('plan_id')->first();
            $activeChargePrice = collect($activeChargeData)->pluck('price')->first();
        }
        elseif ($active_plan_id) {
            $activePlanId = $active_plan_id;
            $activeChargePrice = collect($plansData)->where('id', $activePlanId)->pluck('price')->first();
        }

        $customPlanIds = DB::connection('app-manager-sqlite')->table('plan_user')
            ->where('shop_domain', $shop_domain)->pluck('plan_id')->toArray();
        array_push($customPlanIds, $activePlanId ?? null);
        $customPlanBaseIds = DB::connection('app-manager-sqlite')->table('plans')
            ->whereIn('id', $customPlanIds)->whereNotNull('base_plan')->pluck('base_plan')->toArray();

        if ($activePlanId && ($key = array_search($activePlanId, $customPlanBaseIds)) !== false) {
            unset($customPlanBaseIds[$key]);
        }

        $plans = DB::connection('app-manager-sqlite')->table('plans')->where(function ($query) use ($customPlanIds) {
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

        $customDiscounts = DB::connection('app-manager-sqlite')->table('discount_plan')->where('shop_domain', $shop_domain)
            ->orderByDesc('created_at')->get(['plan_id','discount', 'discount_type', 'cycle_count'])->first();
        if ($customDiscounts) {
            $customDiscounts = $customDiscounts->toArray();
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
            $index = isset($customDiscounts[$plan['id']]) ? $plan['id'] : (isset($customDiscounts[-1]) ? -1 : null);
            if ($index) {
                $plans[$key]['discount'] = $customDiscounts[$index]['discount'];
                $plans[$key]['discount_type'] = $customDiscounts[$index]['discount_type'];
                $plans[$key]['cycle_count'] = $customDiscounts[$index]['cycle_count'];
            }
        }
        return $plans;
    }

    public function preparePlan($data) {
        $planId = [$data['plan_id']];
        $shopDomain = [$data['shop_domain']];

        $planData = DB::connection('app-manager-sqlite')->table('plans')
            ->where('id', $planId)->first();

        if ($planData && $shopDomain) {
            $planData = json_decode(json_encode($planData), true);
            $customDiscounts = DB::connection('app-manager-sqlite')->table('discount_plan')
                ->where('shop_domain', $shopDomain)->select(['plan_id', 'discount', 'discount_type', 'cycle_count'])->first();
            $customDiscounts = json_decode(json_encode($customDiscounts), true);
            if (!empty($customDiscounts) && ($customDiscounts['plan_id'] == -1 || $planData['id'] == $customDiscounts['plan_id'])) {
                $planData['discount'] = isset($customDiscounts['discount']) ? $customDiscounts['discount'] : $planData['discount'];
                $planData['discount_type'] = isset($customDiscounts['discount_type']) ? $customDiscounts['discount_type'] : $planData['discount_type'];
                $planData['cycle_count'] = isset($customDiscounts['cycle_count']) ? $customDiscounts['cycle_count'] : $planData['cycle_count'];
            }
        }

        return $this->unSerializeData($planData);
    }

    public function prepareRemainingDays($data) {
        $trialActivatedAt = $data['trial_activated_at'];
        $planId = $data['plan_id'];
        $shopDomain = $data['shop_domain'];
        $remainingDays = 0;

        if (isset($trialActivatedAt) && !empty($trialActivatedAt) && isset($planId)) {
            $trialDays = DB::connection('app-manager-sqlite')->table('plans')
                ->where('id', $planId)->pluck('trial_days')->first();
            $trialStartDate = Carbon::parse($trialActivatedAt);
            $trialEndsDate = $trialStartDate->addDays($trialDays);
            if ($trialEndsDate->gte(now())) {
                $remainingDays = now()->diffInDays($trialEndsDate);
            }
            $trialExtendData = DB::connection('app-manager-sqlite')->table('trial_extension')
                ->where('shop_domain', $shopDomain)->where('plan_id', $planId)->orderByDesc('created_at')->first();
            if ($trialExtendData) {
                $extendTrialStartDate = Carbon::parse($trialExtendData->created_at)->addDays($trialExtendData->days);
                $remainingExtendedDays = now()->lte($extendTrialStartDate) ? now()->diffInDays($extendTrialStartDate) : 0;
                $remainingDays = $remainingDays + $remainingExtendedDays;
            }
            return $remainingDays;
        }

        $charge = DB::connection('app-manager-sqlite')->table('charges')
            ->where('shop_domain', $shopDomain)->orderByDesc('created_at')->first();
        if ($charge && $charge->trial_days) {
            $trialEndsDate = Carbon::parse($charge->trial_ends_on);
            if (now()->lte($trialEndsDate)) {
                $remainingDays = now()->diffInDays($trialEndsDate);
            }

            $trialExtendData = DB::connection('app-manager-sqlite')->table('trial_extension')
                ->where('shop_domain', $shopDomain)->where('plan_id', $charge->plan_id)->orderBy('created_at')->first();
            if ($trialExtendData) {
                $extendTrialStartDate = Carbon::parse($trialExtendData->created_at)->addDays($trialExtendData->days);
                $remainingExtendedDays = now()->lte($extendTrialStartDate) ? now()->diffInDays($extendTrialStartDate) : 0;
                $remainingDays = $remainingDays + $remainingExtendedDays;
            }
            return $remainingDays;
        }
        return 0;
    }

    public function getChargeHelper($shop_domain) {
        $chargeData = DB::connection('app-manager-sqlite')->table('charges')
            ->where('shop_domain', $shop_domain)->get();
        return [
            'active_charge' => collect($chargeData)->where('status', 'active')->first() ?? null,
            'cancelled_charge' => collect($chargeData)->where('status', 'cancelled')->sortByDesc('created_at')->first() ?? null
        ];
    }

    public function storeChargeHelper($data) {
        $data['sync'] = false;
        $data['process_type'] = 'store-charge';
        $charge = DB::connection('app-manager-sqlite')->table('charges')->insert($data);
        return ['message' => $charge ? 'success' : 'fail'];
    }

    public function cancelChargeHelper($shop_domain, $plan_id) {
        $charge = DB::connection('app-manager-sqlite')->table('charges')
            ->where('shop_domain', $shop_domain)->where('plan_id', $plan_id)
            ->update([
                'status' => 'cancelled',
                'cancelled_on' => Carbon::now(),
            ]);
        return ['message' => $charge ? 'success' : 'fail'];
    }

    public function syncAppManager() {

        $status = false;
        $db_path = Storage::disk('public')->path('app-manager/database.sqlite');
        if (\File::exists($db_path)) {
            $status = DB::connection('app-manager-sqlite')->getPdo() &&
                DB::connection('app-manager-sqlite')->getDatabaseName() &&
                \Schema::connection('app-manager-sqlite')->hasTable('charges');
        }

        if ($status) {
            $response = \AppManager::getStatus();
            if ($response->getStatusCode() == 200) {
                $charges = DB::connection('app-manager-sqlite')->table('charges')
                    ->where('sync', 0)->where('process_type', 'store-charge')->get()->toArray();

                if ($charges) {
                    foreach ($charges as $charge) {
                        $charge = json_decode(json_encode($charge), true);

                        $response = \AppManager::syncCharge($charge);
                        if ($response) {
                            DB::connection('app-manager-sqlite')->table('charges')
                                ->where('charge_id', $charge['charge_id'])->update([
                                    'sync' => 1,
                                    'process_type' => null
                                ]);
                        }
                    }
                }
            }
        }
    }

    public function initializeFailsafeDB() {

        $disk = Storage::disk('local');
        \File::ensureDirectoryExists('storage/app/app-manager',775);

        $disk->put('app-manager/database.sqlite','', 'public');

        Artisan::call('migrate', ['--force' => true,'--database' => 'app-manager-sqlite', '--path' => "/vendor/hulkapps/appmanager/migrations"]);
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
            if (in_array($index, ['interval', 'shopify_plans', 'affiliate', 'feature_plan'])) {
                $data[$index] = json_decode($datum, true);
            }
        }
        return $data;
    }
}
