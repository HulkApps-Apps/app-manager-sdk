<?php

namespace HulkApps\AppManager\app\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

trait FailsafeHelper {

    public function prepareMarketingBanners() {
        $marketingBannersData = DB::connection('app-manager-sqlite')->table('marketing_banners')->get()->toArray();
        return head($marketingBannersData)->marketing_banners ?? null;
    }

    public function preparePlans() {
        $plans = DB::connection('app-manager-sqlite')->table('plans')
            ->where('public', 1)->get()->toArray();
        $plans = json_decode(json_encode($plans), true);
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
                }
            }
            $featuresByPlans = collect($featuresByPlans)->groupBy('plan_id')->toArray();
        }

        foreach ($plans as $key => $plan) {
            $plans[$key]['interval'] = json_decode($plan['interval'], true)['value'];
            $plans[$key]['shopify_plans'] = collect(json_decode($plan['shopify_plans'], true))->pluck('value')->toArray();
            $plans[$key]['features'] = isset($featuresByPlans[$plan['id']]) ? collect($featuresByPlans[$plan['id']])->keyBy('feature_id')->toArray() : null;
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
//			$planData = $planData->toArray();
            $customDiscounts = DB::connection('app-manager-sqlite')->table('discount_plan')
                ->where('plan_id', $planData['id'])->where('shop_domain', $shopDomain)
                ->select(['discount', 'discount_type', 'cycle_count'])->first();
            $planData['discount'] = $customDiscounts ?: $planData['discount'];
            $planData['discount_type'] = $customDiscounts ?: $planData['discount_type'];
            $planData['cycle_count'] = $customDiscounts ?: $planData['cycle_count'];
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
        return DB::connection('app-manager-sqlite')->table('charges')
                ->where('shop_domain', $shop_domain)->where('status', 'active')->first() ?? null;
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

    public function initializeFailsafeDB() {

        if (!\Storage::exists('app-manager')) {
            \Storage::makeDirectory('app-manager',775);
        }

        \Storage::put('app-manager/database.sqlite','');

        Artisan::call('migrate', ['--database' => 'app-manager-sqlite', '--path' => "/vendor/hulkapps/appmanager/migrations"]);
    }

    public function serializeData ($data) {
        foreach ($data as $index => $datum) {
            if (gettype($datum) == 'array') {
                $data[$index] = json_encode($datum);
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
