<?php

namespace HulkApps\AppManager\GraphQL;

use HulkApps\AppManager\Client\Client;
use HulkApps\AppManager\Exception\GraphQLException;
use HulkApps\AppManager\Exception\MissingShopException;
use HulkApps\AppManager\Shopify\ShopifyApi;

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

        $this->shopNameField = config('app-manager.field_names.name');

        $this->shopTokenField = config('app-manager.field_names.shopify_token');
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

    public function client($token = null) {

        $shop = $this->shop[$this->shopNameField] ?? null;

        // The row's own column is only the fallback, so direct callers still work.
        $token = $token ?: ($this->shop[$this->shopTokenField] ?? null);

        $apiVersion = $this->apiVersion;

        if (empty($shop) || empty($token)) {

            throw new GraphQLException("Missing shop name or token");
        }

        return Client::withHeaders(['x-shopify-access-token' => $token, 'Accept' => 'application/json'])
            ->timeout(ShopifyApi::timeout())
            ->baseUri("https://$shop/admin/api/$apiVersion/graphql.json");
    }

    public function send() {

        $shop = $this->shop[$this->shopNameField] ?? null;

        if (empty($shop)) {

            throw new GraphQLException("Missing shop name or token");
        }

        // Kept whole until after the retry: decoding here would lose the status.
        try {
            $response = app(ShopifyApi::class)->withToken($shop, function ($token) {

                return $this->client($token)->post('', array_filter([
                    'query' => $this->query,
                    'variables' => $this->params
                ]));
            }, $this->shop[$this->shopTokenField] ?? null)->json();
        } catch (MissingShopException $exception) {

            throw new GraphQLException("Missing shop name or token");
        }

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