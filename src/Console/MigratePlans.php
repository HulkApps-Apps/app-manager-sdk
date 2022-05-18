<?php

namespace HulkApps\AppManager\Console;

use Carbon\Carbon;
use HulkApps\AppManager\Client\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePlans extends Command
{
    protected $signature = 'migrate:plans';

    protected $description = 'Migrate App plans, features and charges to App manager';

    public function handle()
    {
        $features = [
            [
                "id" => 1,
                "uuid" => "035e5e78-d6ad-11ec-9d64-0242ac120002",
                "name" => "Transaction fee",
                "slug" => "transaction_fee",
                "type" => "double",
                "format" => "percentage",
                "description" => null,
                "display_order" => 2,
                "created_at" => "2022-03-08 11:48:56",
                "updated_at" => "2022-03-08 11:48:56"
            ],
            [
                "id" => 2,
                "uuid" => "035e60ee-d6ad-11ec-9d64-0242ac120002",
                "name" => "Subscriptions",
                "slug" => "subscriptions",
                "type" => "integer",
                "format" => "count",
                "description" => null,
                "display_order" => 3,
                "created_at" => "2022-03-08 11:48:56",
                "updated_at" => "2022-03-08 11:48:56"
            ],
            [
                "id" => 3,
                "uuid" => "035e621a-d6ad-11ec-9d64-0242ac120002",
                "name" => "Subscription Management",
                "slug" => "subscription_management",
                "type" => "boolean",
                "format" => null,
                "description" => null,
                "display_order" => 4,
                "created_at" => "2022-03-08 11:48:56",
                "updated_at" => "2022-03-08 11:48:56"
            ],
            [
                "id" => 4,
                "uuid" => "035e6328-d6ad-11ec-9d64-0242ac120002",
                "name" => "Email notification",
                "slug" => "email_notification",
                "type" => "boolean",
                "format" => null,
                "description" => null,
                "display_order" => 5,
                "created_at" => "2022-03-08 11:48:56",
                "updated_at" => "2022-03-08 11:48:56"
            ],
            [
                "id" => 5,
                "uuid" => "035e6440-d6ad-11ec-9d64-0242ac120002",
                "name" => "Dashboard Analytics",
                "slug" => "dashboard_analytics",
                "type" => "boolean",
                "format" => null,
                "description" => null,
                "display_order" => 6,
                "created_at" => "2022-03-08 11:48:56",
                "updated_at" => "2022-03-08 11:48:56"
            ],
            [
                "id" => 6,
                "uuid" => "035e6558-d6ad-11ec-9d64-0242ac120002",
                "name" => "Advance Reporting",
                "slug" => "advance_reporting",
                "type" => "boolean",
                "format" => null,
                "description" => null,
                "display_order" => 7,
                "created_at" => "2022-03-08 11:48:56",
                "updated_at" => "2022-03-08 11:48:56"
            ],
            [
                "id" => 7,
                "uuid" => "035e688c-d6ad-11ec-9d64-0242ac120002",
                "name" => "Retention Engine (Coming soon)",
                "slug" => "retention_engine",
                "type" => "boolean",
                "format" => null,
                "description" => null,
                "display_order" => 8,
                "created_at" => "2022-03-08 11:48:56",
                "updated_at" => "2022-03-08 11:48:56"
            ],
            [
                "id" => 8,
                "uuid" => "035e699a-d6ad-11ec-9d64-0242ac120002",
                "name" => "Gifting (Coming soon)",
                "slug" => "gifting",
                "type" => "boolean",
                "format" => null,
                "description" => null,
                "display_order" => 9,
                "created_at" => "2022-03-08 11:48:56",
                "updated_at" => "2022-03-08 11:48:56"
            ],
            [
                "id" => 9,
                "uuid" => "035e6a9e-d6ad-11ec-9d64-0242ac120002",
                "name" => "API (Coming soon)",
                "slug" => "api",
                "type" => "boolean",
                "format" => null,
                "description" => null,
                "display_order" => 10,
                "created_at" => "2022-03-08 11:48:56",
                "updated_at" => "2022-03-08 11:48:56"
            ],
            [
                "id" => 10,
                "uuid" => "035e6bac-d6ad-11ec-9d64-0242ac120002",
                "name" => "24x7 Support",
                "slug" => "24h_support",
                "type" => "boolean",
                "format" => null,
                "description" => null,
                "display_order" => 11,
                "created_at" => "2022-03-08 11:48:56",
                "updated_at" => "2022-03-08 11:48:56"
            ]
        ];

        $token = '';
        $api_endpoint = 'https://app-manager.localhost/api/';
        $api_token = '';
        $client = Client::withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])->withoutVerifying()->baseUri($api_endpoint);
        $appSlug = 'subscription-plus';
        $appId = 1;
        $tableName = config('app-manager.shop_table_name', 'users');
        $shopify_fields = config('app-manager.field_names');
        $users = DB::table($tableName)->get()->toArray();
        $plans = DB::table($shopify_fields['plan_table_name'])->get()->toArray();
        $charges = DB::table($shopify_fields['charge_table_name'])->get()->toArray();
        $plans = json_decode(json_encode($plans), true);
        $charges = json_decode(json_encode($charges), true);
        $users = json_decode(json_encode($users), true);

        $userData = collect($users)->pluck($shopify_fields['name'],'id')->toArray();

        $migratedPlans = [];
        foreach ($plans as $plan) {
            $tempPlan = [
                'type' => $plan['type'],
                'name' => $plan['name'],
                'price' => $plan['price'],
                'offer_text' => $plan['offer_text'],
                'interval' => $plan['interval'] == 'EVERY_30_DAYS' ? ["label" => "Monthly", "value" => "EVERY_30_DAYS"] : ["label" => "Annual", "value" => "ANNUAL"],
                'shopify_plans' => $this->prepareShopifyPlanData($plan['shopify_plans']),
                'trial_days' => $plan['trial_days'],
                'test' => $plan['test'] ?? 0,
                'public' => $plan['public'] ?? 1,
                'is_custom' => $plan['is_custom'] ?? 0,
                'base_plan' => $plan['base_plan'] ?? null,
                'store_base_plan' => $plan['store_base_plan'] ?? null,
                'created_at' => $plan['created_at'] ?? now(),
                'updated_at' => $plan['updated_at'] ?? now(),
                'discount' => $plan['discount'] ?? null,
                'cycle_count' => $plan['cycle_count'] ?? null,
                'discount_type' => $plan['discount_type'] ?? null,
                'affiliate' => $plan['affiliate'] ?? null,
                'app_id' => $appId,
            ];
            $res = $client->post("apps/$appSlug/plans/modify", $tempPlan)->json();
            $migratedPlans[$plan['id']] = $res['plan']['id'] ?? 0;

			$planFeatureIds = DB::table('plan_features')->where('plan_id', $plan['id'])->update(['plan_id' => $migratedPlans[$plan['id']]]);
        }

        $client = Client::withHeaders(['Authorization' => 'Bearer '.$token, 'token' => $api_token, 'Accept' => 'application/json'])->withoutVerifying()->baseUri($api_endpoint);
        foreach ($charges as $charge) {
            $tempCharge = [
                'charge_id' => $charge['charge_id'],
                'test' => $charge['test'],
                'status' => $charge['status'] ?? null,
                'name' => $charge['name'],
                'type' => $charge['type'],
                'price' => $charge['price'],
                'interval' => $charge['interval'] ?? null,
                'trial_days' => $charge['trial_days'] ?? null,
                'billing_on' => $charge['billing_on'] ?? null,
                'trial_ends_on' => Carbon::parse($charge['trial_ends_on'])->format('Y-m-d H:i:s') ?? null,
                'activated_on' => Carbon::parse($charge['activated_on'])->format('Y-m-d H:i:s') ?? null,
                'cancelled_on' => Carbon::parse($charge['cancelled_on'])->format('Y-m-d H:i:s') ?? null,
                'expires_on' => $charge['expires_on'] ?? null,
                'description' => $charge['description'] ?? null,
                'shop_domain' => $userData[$charge['user_id']] ?? null,
                'created_at' => $charge['created_at'] ?? null,
                'updated_at' => $charge['updated_at'] ?? null,
                'plan_id' => 11,
                'app_id' => $appId
            ];
            $res = $client->post("store-charge", $tempCharge)->json();

        }

        // Update plan id in users table
        foreach ($migratedPlans as $index => $migratedPlan) {
            DB::table($tableName)->where('plan_id', $index)->update(['plan_id' => $migratedPlan]);
        }

    }

    public function prepareShopifyPlanData($data) {
        $result = [];
        $shopify_plan = [
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

        foreach ($shopify_plan as $key => $plan) {
            if (in_array($plan, $data)) {
                $result[] = [
                    'label' => $key,
                    'value' => $plan
                ];
            }
        }
        return $result;
    }
}
