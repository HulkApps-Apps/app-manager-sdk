<?php

namespace HulkApps\AppManager\Console;

use Carbon\Carbon;
use HulkApps\AppManager\Client\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePlans extends Command
{
    protected $signature = 'migrate:app-manager-plans';

    protected $description = 'Migrate App plans, features and charges to App manager';

    public $errors = [];

    public function handle()
    {
        $bearer_token = config('app-manager.bearer_token');
        $api_endpoint = config('app-manager.api');
        $api_token = config('app-manager.secret');
        $shopTableName = config('app-manager.shop_table_name', 'users');
        $shopify_fields = config('app-manager.field_names');
        $features = config('plan_features');

        $client = Client::withHeaders(['Authorization' => 'Bearer '.$bearer_token, 'token' => $api_token, 'Accept' => 'application/json'])->withoutVerifying()->baseUri($api_endpoint);

        $userData = DB::table($shopTableName)->pluck($shopify_fields['name'], 'id')->all();
        $plans = DB::table('plans')->get()->toArray();
        $charges = DB::table('charges')->get()->toArray();

        $plans = json_decode(json_encode($plans), true);
        $charges = json_decode(json_encode($charges), true);

        // fetch stored plans
        $response = $client->get("admin/plans?limit=100");
        if ($response->getStatusCode() != 200) {
            dd(json_encode($response->json()));
        }
        $data = $response->json()['data'];
        foreach ($data as $row) {
            $storedPlans[$row['name'] . '#' . $row['interval']['value'] . '#' . ($row['public'] ? 1 : 0)] = $row['id'];
        }

        $migratedPlans = [];
        foreach ($plans as $index => $plan) {

            $this->progressBar($index, count($plans), 'Plans');
            $preparedPlan = [
                'id' => $storedPlans[$plan['name'] . '#' . $plan['interval'] . '#' . (isset($plan['public']) ? $plan['public'] : 1)] ?? null,
                'type' => $plan['type'],
                'name' => $plan['name'],
                'price' => $plan['price'],
                'offer_text' => $plan['offer_text'] ?? null,
                'interval' => $plan['interval'] == 'EVERY_30_DAYS' ? ["label" => "Monthly", "value" => "EVERY_30_DAYS"] : ["label" => "Annual", "value" => "ANNUAL"],
                'trial_days' => $plan['trial_days'] ?? 0,
                'test' => $plan['test'] ?? 0,
                'public_plan' => isset($plan['public']) ? $plan['public'] == 1 : true,
                'shopify_plans' => isset($plan['shopify_plans']) ? $this->prepareShopifyPlanData($plan['shopify_plans']) : [],
                'store_base_plan' => $plan['store_base_plan'] ?? (isset($plan['shopify_plans']) ? 1 : 0),
                'is_custom' => $plan['is_custom'] ?? 0,
                'base_plan' => $plan['base_plan'] ?? null,
                'created_at' => $plan['created_at'] ?? now(),
                'updated_at' => $plan['updated_at'] ?? now(),
                'discount' => $plan['discount'] ?? null,
                'cycle_count' => $plan['cycle_count'] ?? null,
                'discount_type' => $plan['discount_type'] ?? null,
                'affiliate' => $plan['affiliate'] ?? null,
            ];

            try {
                $response = $client->post("admin/plans/modify", $preparedPlan);
                if (in_array($response->getStatusCode(), [200, 201])) {
                    $response = $response->json();
                    $migratedPlans[$plan['id']] = $response['plan']['id'] ?? 0;
                }
                else {
                    $this->handleError($response, 'plan', $plan, $preparedPlan);
                }
            }
            catch (\Exception $e) {
                $this->handleError($response, 'plan', $plan, $preparedPlan);
            }
            sleep(1);
        }

        foreach ($charges as $index => $charge) {

            $this->progressBar($index, count($charges), 'Charges');
            $shop_domain = $shopTableName == 'users' ? ($userData[$charge['user_id']] ?? null) : ($shopTableName == 'shops' ? ($userData[$charge['shop_id']] ?? null) : null);
            $preparedCharge = [
                'charge_id' => $charge['charge_id'],
                'test' => $charge['test'] ?? 0,
                'status' => $charge['status'] ?? null,
                'name' => $charge['name'] ?? null,
                'type' => $charge['type'],
                'price' => $charge['price'],
                'interval' => $charge['interval'] ?? null,
                'trial_days' => $charge['trial_days'] ?? null,
                'billing_on' => Carbon::parse($charge['billing_on'])->format('Y-m-d') ?? null,
                'trial_ends_on' => Carbon::parse($charge['trial_ends_on'])->format('Y-m-d') ?? null,
                'activated_on' => Carbon::parse($charge['activated_on'])->format('Y-m-d') ?? null,
                'cancelled_on' => Carbon::parse($charge['cancelled_on'])->format('Y-m-d') ?? null,
                'expires_on' => $charge['expires_on'] ?? null,
                'description' => $charge['description'] ?? null,
                'shop_domain' => $shop_domain,
                'created_at' => $charge['created_at'] ?? null,
                'updated_at' => $charge['updated_at'] ?? null,
                'plan_id' => $migratedPlans[$charge['plan_id']] ?? null,
            ];

            // fetch stored charge
            $storedCharge = \AppManager::getCharge($shop_domain);
            if ($storedCharge) {
                $preparedCharge['id'] = $storedCharge['id'];
            }

            try {
                $response = $client->post("store-charge", $preparedCharge);
                if ($response->getStatusCode() != 201) {
                    $this->handleError($response, 'charge', $charge, $preparedCharge);
                }
            }
            catch (\Exception $e) {
                $this->handleError($response, 'charge', $charge, $preparedCharge);
            }
            sleep(1);
        }

        foreach ($migratedPlans as $index => $migratedPlan) {

            $this->progressBar($index, count($migratedPlans), 'Plan Features');

            // Update plan id in users table
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('plans')->where('id', $index)->update(['id' => $migratedPlan]);
            DB::table('charges')->where('plan_id', $index)->update(['plan_id' => $migratedPlan]);
            DB::table('plan_feature')->where('plan_id', $index)->update(['plan_id' => $migratedPlan]);
            DB::table($shopTableName)->where('plan_id', $index)->update(['plan_id' => $migratedPlan]);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Prepare and Add plan features
            $featurePlans = DB::table('plan_feature')->where('plan_id', $migratedPlan)->get()->toArray();
            $featurePlans = json_decode(json_encode($featurePlans), true);
            $featurePlans = collect($featurePlans)->pluck('value', 'feature_id')->toArray();

            foreach ($features as $key => $feature) {
                if (isset($featurePlans[$feature['id']])) {
                    $features[$key]['selected'] = true;
                    $features[$key]['inputValue'] = $featurePlans[$feature['id']];
                }
                else {
                    $features[$key]['selected'] = false;
                    $features[$key]['inputValue'] = null;
                }
            }
            $data = [
                'features' => $features,
                'plan_id' => $migratedPlan
            ];
            try {
                $response = $client->post("admin/plans/configure", $data);
                if ($response->getStatusCode() != 200) {
                    $this->handleError($response, 'plan-configure', $migratedPlan, $features);
                }
            }
            catch (\Exception $e) {
                $this->handleError($response, 'plan-configure', $migratedPlan, $features);
            }
            sleep(1);
        }

        // log errors
        if ($this->errors) {
            $response = $client->post("store-migration-log", $this->errors);
            dump($this->errors);
        }
    }

    public function prepareShopifyPlanData($data) {
        $result = [];
        $shopif_plan = [
            "Basic"=> "basic",
            "Affiliate"=> "affiliate",
            "NPO Full"=> "npo_full",
            "Open Learning"=> "open_learning",
            "Partner Test"=> "partner_test",
            "Staff"=> "staff",
            "Shopify Alumni"=> "shopify_alumni",
            "NPO Lite"=> "npo_lite",
            "Trial"=> "trial",
            "Professional"=> "professional",
            "Staff Business"=> "staff_business",
            "Unlimited"=> "unlimited",
            "Shopify Plus"=> "shopify_plus",
            "Enterprise"=> "enterprise",
            "Custom"=> "custom",
        ];
        $data = explode(',', str_replace('"', '', str_replace('[', '', str_replace(']', '', $data))));

        foreach ($shopif_plan as $key => $plan) {
            if (in_array($plan, $data)) {
                $result[] = [
                    'label' => $key,
                    'value' => $plan
                ];
            }
        }
        return $result;
    }

    public function handleError($response, $type, $data, $preparedData) {
        $this->errors[] = [
            'response' => json_encode($response->json()),
            'status_code' => $response->getStatusCode(),
            'type' => $type,
            'data' => json_encode($data),
            'prepared_data' => json_encode($preparedData),
        ];
    }

    function progressBar($done, $total,$comment)
    {
        $percentage = floor(($done * 100) / $total);
        $left = 100 - $percentage;
        $write = sprintf("\033[0G\033[2K[%'={$percentage}s>%-{$left}s] - $percentage%% - $done/$total [$comment]", "", "");
        fwrite(STDERR, $write);
    }
}
