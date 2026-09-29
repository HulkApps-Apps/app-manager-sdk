<?php

namespace HulkApps\AppManager\Shopify;

use HulkApps\AppManager\Client\Client;
use HulkApps\AppManager\Client\ClientResponse;
use HulkApps\AppManager\Contracts\ShopifyTokenResolver;
use HulkApps\AppManager\Exception\MissingShopException;

/**
 * The single path for every SDK call to a shop's Admin API: it asks the app's
 * resolver for a token, and replays once with a replacement if Shopify rejects
 * it. With the default resolver there is no replacement, so nothing changes.
 */
class ShopifyApi
{
    /** Matched on the message because a missing scope is also a 403. */
    const NON_EXPIRING_REJECTED = 'Non-expiring access tokens are no longer accepted';

    /**
     * @var ShopifyTokenResolver
     */
    protected $tokens;

    public function __construct(ShopifyTokenResolver $tokens)
    {
        $this->tokens = $tokens;
    }

    /**
     * @param  string  $method  get, post, put or delete
     * @param  string  $path  relative to /admin/api/{version}/
     * @return ClientResponse
     *
     * @throws MissingShopException when the shop has no token at all
     */
    public function rest($shopDomain, $method, $path, $data = null)
    {
        $url = sprintf(
            'https://%s/admin/api/%s/%s',
            $shopDomain,
            config('app-manager.shopify_api_version'),
            ltrim($path, '/')
        );

        return $this->withToken($shopDomain, function ($token) use ($method, $url, $data) {
            $request = Client::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Accept' => 'application/json',
            ])->timeout(static::timeout());

            return $data === null
                ? $request->{$method}($url)
                : $request->{$method}($url, $data);
        });
    }

    /**
     * Exactly one replay, and only with a token that differs from the rejected
     * one: if a fresh token is rejected too, the token is not what is wrong.
     *
     * @param  callable  $send  receives a token, returns a ClientResponse
     * @param  string|null  $fallbackToken  used only if the resolver has none
     * @return ClientResponse
     *
     * @throws MissingShopException when no token is available at all
     */
    public function withToken($shopDomain, callable $send, $fallbackToken = null)
    {
        $token = $this->tokens->token($shopDomain);

        if (empty($token)) {
            $token = $fallbackToken;
        }

        if (empty($token)) {
            throw new MissingShopException("No Shopify access token available for {$shopDomain}");
        }

        $response = $send($token);

        if (! static::tokenRejected($response)) {
            return $response;
        }

        $replacement = $this->tokens->refresh($shopDomain);

        if (empty($replacement) || $replacement === $token) {
            return $response;
        }

        return $send($replacement);
    }

    /** Seconds per call; an explicit 0 keeps the old wait-forever behaviour. */
    public static function timeout()
    {
        $seconds = config('app-manager.shopify_timeout');

        return $seconds === null ? 20 : (int) $seconds;
    }

    /** @return bool whether Shopify refused this response because of the token */
    public static function tokenRejected(ClientResponse $response)
    {
        if ($response->status() === 401) {
            return true;
        }

        return $response->status() === 403
            && strpos($response->body(), self::NON_EXPIRING_REJECTED) !== false;
    }
}
