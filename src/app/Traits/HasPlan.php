<?php

namespace HulkApps\AppManager\app\Traits;

use HulkApps\AppManager\Exception\MissingPlanException;

trait HasPlan
{
    public function hasPlan() {
        $shopify_fields = config('app-manager.field_names');
        if ($this->{$shopify_fields['grandfathered']}) {
            return true;
        }
        if (!$this->plan_id) {
            return false;
        }
        $remainingDays = $this->getRemainingDays();
        if ($remainingDays > 0) {
            return true;
        }
        $activeCharge = \AppManager::getCharge($this->{$shopify_fields['name']});
        return isset($activeCharge['active_charge']);
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

        $featuresByPlan = collect($planData['features'])->pluck('value', 'feature_id')->toArray();
        $planFeatures = collect(config('plan_features'))->whereIn('uuid', array_keys($featuresByPlan))->keyBy('uuid')->toArray();
        foreach ($planFeatures as $index => $planFeature) {
            $planFeatures[$index]['value'] = $featuresByPlan[$index] ?? null;
        }
        return array_values($planFeatures);
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

    public function getPlanData($planId = null) {
        if (!$planId) {
            $planId = $this->plan_id;
        }
        return \AppManager::getPlan($planId);
    }

    public function getChargeData() {
        $shopify_fields = config('app-manager.field_names');
        $shop_domain = $this->{$shopify_fields['name']};
        return \AppManager::getCharge($shop_domain);
    }

    public function setDefaultPlan($plan_id = null) {
        if (!$plan_id) {
            $shopify_fields = config('app-manager.field_names');
            $plans = \AppManager::getPlans($this->{$shopify_fields['name']});
            $freePlanId = collect($plans)->where('price', 0)->where('public', true)->pluck('id')->first();
            if (!$freePlanId) {
                throw new MissingPlanException('Free Plan is not available');
            }
            $this->plan_id = $freePlanId;
            $this->save();
        }
        else {
            $this->plan_id = $plan_id;
            $this->save();
        }
    }

    public function getPlansByFeatures($features) {
        if (count($features) == 0) {
            return [];
        }
        sort($features);
        $return = [];
        $shopify_fields = config('app-manager.field_names');
        $plans = \AppManager::getPlans($this->{$shopify_fields['name']});
        $plans = collect($plans)->where('interval', 'EVERY_30_DAYS')->where('public', true);
        foreach ($plans as $plan) {
            $planFeatures = collect($plan['features'])->pluck('slug')->toArray();
            sort($planFeatures);
            if (array_values(array_intersect($planFeatures, $features)) == $features) {
                $return[] = $plan;
            }
        }
        return $return;
    }

    public function getPlansByFeaturesWithValues($features) {
        if (count($features) == 0) {
            return [];
        }
        $return = [];
        $shopify_fields = config('app-manager.field_names');
        $plans = \AppManager::getPlans($this->{$shopify_fields['name']});
        $plans = collect($plans)->where('interval', 'EVERY_30_DAYS')->where('public', true);
        foreach ($plans as $plan) {
            $planFeatures = collect($plan['features'])->pluck('value', 'slug')->toArray();
            $flag = true;
            foreach ($features as $key => $feature) {
                $feature = str_replace('"', '', str_replace('"', '', $feature));
                if (!$feature) {
                    $flag = $flag && collect($plan['features'])->where('slug', $key)->count() > 0;
                }
                else {
                    $result = collect($plan['features'])->where('slug', $key)->first();
                    if ($result) {
                        if ($result['value_type'] == 'array') {
                            $flag = $flag && str_contains($result['value'], $feature);
                        }
                        elseif ($result['value_type'] == 'string') {
                            $result['value'] = str_replace('"', '', str_replace('"', '', $result['value']));
                            $flag = $flag && collect([$result])->where('value', $feature)->count() > 0;
                        }
                        elseif (in_array($result['value_type'], ['integer', 'double'])) {
                            if ($result['value'] != -1){
                                $flag = $flag && collect([$result])->where('value', '>', $feature)->count() > 0;
                            }
                        }
                        else {
                            $flag = $flag && collect($plan['features'])->where('slug', $key)->where('value', $feature)->count() > 0;
                        }
                    }
                    else {
                        $flag = false;
                    }
                }
            }
            if ($flag) {
                $return[] = json_decode(json_encode($plan), true);
            }
        }
        return array_values(collect($return)->keyBy('id')->toArray());
    }
}
