<?php

namespace HulkApps\AppManager;

use HulkApps\AppManager\Client\Client;

class GraphQL
{

    public function __construct($shop, $token)
    {
        $apiVersion = config('app_manager.shopify_api_version');
        $this->client = Client::baseUri("https://$shop/admin/api/$apiVersion/graphql.json")->withHeader(['x-shopify-access-token' => $token, 'Accept' => 'application/json']);
    }

    private function doRequestGraphQL(string $query, array $payload = [])
    {
        $response = $this->client->post('', [
            'query' => $query,
            'variables' => $payload
        ]);

        dd($response);
        if ($response['errors'] !== false) {
            \Log::info($response);
            $message = is_array($response['errors'])
                ? $response['errors'][0]['message'] : $response['errors'];

            // Request error somewhere, throw the exception
            throw new Exception($message);
        }

        return $response['body']['data'];
    }
}