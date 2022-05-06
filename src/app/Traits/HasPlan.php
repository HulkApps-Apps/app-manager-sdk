<?php

namespace HulkApps\AppManager\app\Traits;

use HulkApps\AppManager\Exception\MissingPlanException;
use Illuminate\Http\Request;

trait HasPlan
{
    public function hasPlan() {
        return $this->plan_id ?? false;
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
        $allFeatures = config('plan_features');

        foreach ($allFeatures as $index => $feature) {
            if (!isset($featuresByPlan[$feature['uuid']])) {
                unset($allFeatures[$index]);
                continue;
            }
            $allFeatures[$index]['value'] = $featuresByPlan[$feature['uuid']];
        }

        return array_values($allFeatures);
    }

    public function hasFeature($slug) {

        $response = $this->planFeatures();
        if (isset($response['error']) && $response['error']) {
            throw new \Exception($response);
        }

        return collect($response)->where('slug', $slug)->count() > 0;
    }

    public function getFeature($slug) {

        $response = $this->planFeatures();

        if (isset($response['error']) && $response['error']) {
            return $response;
        }

        $feature = collect($response)->where('slug', $slug)->first();

        if ($feature) {
            settype($feature['value'], $feature['value_type']);
            return $feature['value'];
        } else {
            return null;
        }
    }

    public function getRemainingDays() {
        $planId = $this->plan_id;
        $userId = $this->id;

        if (!$planId) {
            throw new MissingPlanException("Plan not found");
        }

        return \AppManager::getRemainingDays($planId, $userId);
    }
}
