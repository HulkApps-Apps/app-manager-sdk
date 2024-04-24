<?php

namespace HulkApps\AppManager\app\Traits;

use HulkApps\AppManager\Exception\MissingPlanException;
use Illuminate\Support\Facades\Cache;

trait HasPlan
{
    public function hasPlanOld() {
        $shopify_fields = config('app-manager.field_names');
        return Cache::tags('app-manager')->rememberForever('app-manager.has_plan_response_'.$this->{$shopify_fields['name']} . '_' . $this->updated_at, function () use ($shopify_fields) {
            if ($this->{$shopify_fields['grandfathered']}) {
                return true;
            }
            if (!$this->{$shopify_fields['plan_id']}) {
                return false;
            }
            $remainingDays = $this->getRemainingDays();
            if ($remainingDays > 0) {
                return true;
            }
            $activeCharge = \AppManager::getCharge($this->{$shopify_fields['name']});
            return $activeCharge['active_charge'] && count($activeCharge['active_charge']) > 0;
        });
    }

    public function hasPlan() {
        $shopify_fields = config('app-manager.field_names');
        if (!$this->{$shopify_fields['plan_id']}) {
            return false;
        }
        return Cache::tags('app-manager')->rememberForever('app-manager.has_plan_response_'.$this->{$shopify_fields['name']} . '_' . $this->updated_at, function () use ($shopify_fields) {
            $response = \AppManager::hasPlan([
                'grandfathered' => $this->{$shopify_fields['grandfathered']},
                'shop_domain' => $this->{$shopify_fields['name']},
                'plan_id' => $this->{$shopify_fields['plan_id']},
                'trial_activated_at' => $this->{$shopify_fields['trial_activated_at']},
            ]);
            return $response['has_plan'] ?? false;
        });
    }

    public function planFeatures() {
        $shopify_fields = config('app-manager.field_names');
        return Cache::tags('app-manager')->rememberForever('app-manager.plan_feature_response_'.$this->{$shopify_fields['name']} . '_' . $this->updated_at, function () use ($shopify_fields) {
            $planId = $this->{$shopify_fields['plan_id']};

            if (!$planId) {
                return [];
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
        });
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
        return Cache::tags('app-manager')->rememberForever('app-manager.remaining_days_response_'.$this->{$shopify_fields['name']} . '_' . $this->updated_at, function () use ($shopify_fields) {

            $shop_domain = $this->{$shopify_fields['name']};

            $trial_activated_at = $this->{$shopify_fields['trial_activated_at']};
            $plan_id = $this->{$shopify_fields['plan_id']};
            return \AppManager::getRemainingDays($shop_domain, $trial_activated_at, $plan_id) ?? 0;
        });
    }

    public function getPlanData($planId = null) {
        $shopify_fields = config('app-manager.field_names');

        return Cache::tags('app-manager')->rememberForever('app-manager.plan_response_'.$this->{$shopify_fields['name']} . '_' . $this->updated_at, function () use ($shopify_fields, $planId) {

            if (!$planId) {
                $planId = $this->{$shopify_fields['plan_id']};
            }
            return \AppManager::getPlan($planId, $this->{$shopify_fields['name']});
        });
    }

    public function getChargeData() {
        $shopify_fields = config('app-manager.field_names');
        $shop_domain = $this->{$shopify_fields['name']};
        return Cache::tags('app-manager')->rememberForever('app-manager.charges_response_'.$this->{$shopify_fields['name']} . '_' . $this->updated_at, function () use ($shop_domain) {
            return \AppManager::getCharge($shop_domain);
        });
    }

    public function setDefaultPlan($plan_id = null) {
        $shopify_fields = config('app-manager.field_names');
        if (!$plan_id) {
            $plans = \AppManager::getPlans($this->{$shopify_fields['name']});
            $freePlanId = collect($plans)->where('price', 0)->where('public', true)->pluck('id')->first();
            if (!$freePlanId) {
                throw new MissingPlanException('Free Plan is not available');
            }
            $this->{$shopify_fields['plan_id']} = $freePlanId;
            $this->save();
        }
        else {
            $this->{$shopify_fields['plan_id']} = $plan_id;
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
