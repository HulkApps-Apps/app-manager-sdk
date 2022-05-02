<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait HasPlan
{
    public function hasPlan() {
        if ($this->plan_id) {
            return true;
        }
        return false;
    }

    public function planFeatures() {
        $planId = $this->plan_id;

        $Request = new Request();
//        $Request->request->add(['plan_id' => 1]);
        $Request->request->add(['plan_id' => $planId]);
        $planData = \AppManager::getPlan($Request->request);

        if (!$planData) {
            return [];
        }

        if (count($planData['features']) <= 0) {
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

    public function hasFeature($slug, $value = true) {
        if (empty($slug)) {
            return response()->json(['message' => 'Slug is required'], 422);
        }
        $features = $this->planFeatures();
        return collect($features)->where('slug', $slug)->where('value', $value)->count() === 0 ? false : true;
    }

    public function getFeature($slug) {
        if (empty($slug)) {
            return response()->json(['message' => 'Slug is required'], 422);
        }
        $features = $this->planFeatures();
        return collect($features)->where('slug', $slug)->toArray();
    }
}
