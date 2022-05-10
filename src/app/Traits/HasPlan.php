<?php

namespace HulkApps\AppManager\app\Traits;

use HulkApps\AppManager\Exception\MissingPlanException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait HasPlan
{
    public function hasPlan() {
        if (!$this->plan_id) {
            return false;
        }
        $planData = \AppManager::getPlan($this->plan_id);
        $trialDays = $planData['trial_days'];
        $tableName = config('app-manager.shop_table_name', 'users');
        $shopify_fields = config('app-manager.field_names');
        $trial_activated_at = DB::table($tableName)->where('id', $this->id)->pluck($shopify_fields['trial_activated_at'])->first();
        if ($trial_activated_at && now()->diffInDays(Carbon::parse($trial_activated_at)) < $trialDays ) {
            return true;
        }
        return false;
    }

    public function planFeatures() {
        $planId = $this->plan_id;

        if (!$planId) {
            throw new MissingPlanException("Plan not found");
        }

        $planData = \AppManager::getPlan($planId);

        if (empty($planData['features'] ?? [])) {
            return [];
        }

        $featuresByPlan = collect($planData['features'])->pluck('value', 'feature_id')->keys()->toArray();
        return collect(config('plan_features'))->whereIn('uuid', $featuresByPlan)->toArray();
    }

    public function hasFeature($slug) {
        return collect($this->planFeatures())->where('slug', $slug)->count() > 0;
    }

    public function getFeature($slug) {

        $response = $this->planFeatures();

        $feature = collect($response)->where('slug', $slug)->first();

        if ($feature) {
            settype($feature['value'], $feature['value_type']);
            return $feature['value'];
        } else {
            return null;
        }
    }

    public function getRemainingDays() {
        $shopify_fields = config('app-manager.field_names');
        $shop_domain = $this->{$shopify_fields['name']};

        $trial_activated_at = $this->{$shopify_fields['trial_activated_at']};
        $plan_id = $this->{$shopify_fields['plan_id']};

        return \AppManager::getRemainingDays($shop_domain, $trial_activated_at, $plan_id);
    }
}
