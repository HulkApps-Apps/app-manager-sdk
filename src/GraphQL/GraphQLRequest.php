<?php

namespace HulkApps\AppManager\GraphQL;

use HulkApps\AppManager\Client\Client;
use HulkApps\AppManager\Exception\GraphQLException;

class GraphQLRequest
{
    private $shop;

    private $apiVersion;

    private $shopNameField;

    private $shopTokenField;

    private $client;

    private $params;

    public function __construct() {

        $this->apiVersion = config('app-manager.shopify_api_version');

        $this->shopNameField = config('app-manager.store_field_name');

        $this->shopTokenField = config('app-manager.store_token_field_name');
    }

    static function new(...$args)
    {
        return new self(...$args);
    }

    public function shop($shop) {

        return tap($this, function ($request) use ($shop) {
            return $this->shop = $shop;
        });
    }

    public function withAPIVersion($apiVersion) {

        return tap($this, function ($request) use ($apiVersion) {
            return $this->apiVersion = $apiVersion;
        });
    }

    public function withParams($params) {

        return tap($this, function ($request) use ($params) {
            return $this->params = $params;
        });
    }

    public function query($query) {

        return tap($this, function ($request) use ($query) {
            return $this->query = $query;
        });
    }

    public function client() {

        $shop = $this->shop[$this->shopNameField] ?? null;

        $token = $this->shop[$this->shopTokenField] ?? null;

        $apiVersion = $this->apiVersion;

        if (empty($shop) || empty($token)) {

            throw new GraphQLException("Missing shop name or token");
        }

        return Client::withHeaders(['x-shopify-access-token' => $token, 'Accept' => 'application/json'])->baseUri("https://$shop/admin/api/$apiVersion/graphql.json");
    }

    public function send() {

        $response = $this->client()->post('', array_filter([
            'query' => $this->query,
            'variables' => $this->params
        ]))->json();

        if (!empty($response['errors']) && $response['errors'] !== false) {

            if (is_array($response['errors'])) {
                $errors = head($response['errors']);

                if (is_string($errors)) {
                    $message = $errors;
                } else $message = head($errors);
            } else $message = $response['errors'];

            // Request error somewhere, throw the exception
            throw new GraphQLException($message);
        }

        return $response['data'];
    }
}