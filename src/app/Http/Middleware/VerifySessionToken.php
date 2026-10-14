<?php

namespace HulkApps\AppManager\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifySessionToken
{
    private const LEEWAY_SECONDS = 10;

    /**
     * Lets through App Manager (the `token` header) or an embedded admin page
     * (a Shopify session token). For a session token the shop is taken from the
     * token itself, never from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->hasValidApiToken($request)) {
            return $next($request);
        }

        $shop = $this->shopFromSessionToken((string) $request->bearerToken());

        if ($shop) {
            // get() reads the query string before the body, so both must hold the token's shop.
            $request->query->add(['shop' => $shop, 'shop_domain' => $shop]);
            $request->merge(['shop' => $shop, 'shop_domain' => $shop]);
            return $next($request);
        }

        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    private function hasValidApiToken(Request $request): bool
    {
        $secret = (string) config('app-manager.secret');
        $token = (string) $request->header('token');

        return $secret !== '' && $token !== '' && hash_equals($secret, $token);
    }

    private function shopFromSessionToken(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        if (($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $secrets = $this->apiSecrets();
        if (empty($secrets)) {
            Log::warning('App Manager: no Shopify API secret is configured, so session tokens cannot be verified. Set SHOPIFY_API_SECRET or app-manager.shopify_api_secret.');
            return null;
        }

        $signature = $this->base64UrlDecode($encodedSignature);
        $signed = false;
        foreach ($secrets as $secret) {
            if (hash_equals(hash_hmac('sha256', "$encodedHeader.$encodedPayload", $secret, true), $signature)) {
                $signed = true;
                break;
            }
        }
        if (!$signed) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload)) {
            return null;
        }

        $now = time();
        if (!is_numeric($payload['exp'] ?? null) || $payload['exp'] < $now - self::LEEWAY_SECONDS) {
            return null;
        }
        if (isset($payload['nbf']) && (!is_numeric($payload['nbf']) || $payload['nbf'] > $now + self::LEEWAY_SECONDS)) {
            return null;
        }

        $apiKeys = $this->apiKeys();
        if (!empty($apiKeys) && !in_array($payload['aud'] ?? null, $apiKeys, true)) {
            return null;
        }

        $shop = parse_url((string) ($payload['dest'] ?? ''), PHP_URL_HOST);
        $issuerShop = parse_url((string) ($payload['iss'] ?? ''), PHP_URL_HOST);
        if (!$shop || $shop !== $issuerShop || !preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/i', $shop)) {
            return null;
        }

        return strtolower($shop);
    }

    private function apiSecrets(): array
    {
        return $this->configured([
            config('app-manager.shopify_api_secret'),
            config('shopify-app.api_secret'),
            config('shopify.api_secret'),
            config('shopify-app.api_secret_old'),
        ]);
    }

    private function apiKeys(): array
    {
        return $this->configured([
            config('app-manager.shopify_api_key'),
            config('shopify-app.api_key'),
            config('shopify.api_key'),
        ]);
    }

    private function configured(array $values): array
    {
        return array_values(array_unique(array_filter($values, function ($value) {
            return is_string($value) && $value !== '' && $value !== 'not_defined';
        })));
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
