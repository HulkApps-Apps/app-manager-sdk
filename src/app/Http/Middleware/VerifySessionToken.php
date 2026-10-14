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

        $secret = (string) config('app-manager.shopify_api_secret');
        if ($secret === '') {
            // Reported, not just logged, so the app's error tracker alerts: every billing-page request fails until this is set.
            report(new \RuntimeException('App Manager: app-manager.shopify_api_secret is empty, so session tokens cannot be verified. Point it at the env variable holding your Shopify API secret.'));
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload)) {
            return null;
        }

        $apiKey = (string) config('app-manager.shopify_api_key');
        $expected = hash_hmac('sha256', "$encodedHeader.$encodedPayload", $secret, true);
        if (!hash_equals($expected, $this->base64UrlDecode($encodedSignature))) {
            if ($apiKey !== '' && ($payload['aud'] ?? null) === $apiKey) {
                Log::warning('App Manager: a session token for this app failed signature verification. Check that app-manager.shopify_api_secret holds this app\'s current Shopify API secret.');
            }
            return null;
        }

        $now = time();
        if (!is_numeric($payload['exp'] ?? null) || $payload['exp'] < $now - self::LEEWAY_SECONDS) {
            return null;
        }
        if (isset($payload['nbf']) && (!is_numeric($payload['nbf']) || $payload['nbf'] > $now + self::LEEWAY_SECONDS)) {
            return null;
        }

        if ($apiKey !== '' && ($payload['aud'] ?? null) !== $apiKey) {
            // Only a token signed with this app's secret gets here, so the configured key is wrong.
            Log::warning('App Manager: a session token signed with this app\'s secret has a different API key. Check that app-manager.shopify_api_key holds this app\'s Shopify API key.');
            return null;
        }

        $shop = parse_url((string) ($payload['dest'] ?? ''), PHP_URL_HOST);
        $issuerShop = parse_url((string) ($payload['iss'] ?? ''), PHP_URL_HOST);
        if (!$shop || $shop !== $issuerShop || !preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/i', $shop)) {
            return null;
        }

        return strtolower($shop);
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
