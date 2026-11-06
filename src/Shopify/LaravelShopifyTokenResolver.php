<?php

namespace HulkApps\AppManager\Shopify;

use HulkApps\AppManager\Contracts\ShopifyTokenResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * For apps whose authentication is handled by kyon147/laravel-shopify. That
 * package owns the tokens and renews them; this hands the SDK what it holds.
 *
 * Point `app-manager.shopify_token_resolver` at this class. Apps with custom
 * authentication implement the contract themselves instead.
 */
class LaravelShopifyTokenResolver implements ShopifyTokenResolver
{
    /** Referenced as strings so the SDK never depends on the package. */
    public const SHOP_QUERY = 'Osiset\ShopifyApp\Contracts\Queries\Shop';

    public const SHOP_DOMAIN = 'Osiset\ShopifyApp\Objects\Values\ShopDomain';

    public const REFRESHER = 'Osiset\ShopifyApp\Services\OfflineAccessTokenRefresher';

    /** @var ColumnTokenResolver */
    protected $column;

    public function __construct(ColumnTokenResolver $column)
    {
        $this->column = $column;
    }

    /**
     * {@inheritdoc}
     */
    public function token(string $shopDomain): ?string
    {
        $shop = $this->shop($shopDomain);

        if ($shop === null) {
            return $this->column->token($shopDomain);
        }

        if ($this->canRefresh($shop)) {
            // The package refreshes only when it builds its own API client, and
            // the SDK never does — so it has to be asked.
            $this->attempt($shopDomain, function () use ($shop) {
                $shop->refreshOfflineAccessTokenIfNeeded();
            });
        }

        $token = $this->accessToken($shop);

        return $token === null ? $this->column->token($shopDomain) : $token;
    }

    /**
     * {@inheritdoc}
     */
    public function refresh(string $shopDomain): ?string
    {
        $shop = $this->shop($shopDomain);

        if ($shop === null || ! $this->canRefresh($shop)) {
            return null;
        }

        $rejected = $this->accessToken($shop);

        $refreshed = $this->attempt($shopDomain, function () use ($shop) {
            $days = config('app-manager.shopify_force_renewal_days', 36500);
            $days = $days === null ? 36500 : (int) $days;

            if ($days > 0) {
                // Shopify rejected the token, so the stored expiry is wrong. The
                // package has no force, so widen its window to make one due.
                app(self::REFRESHER)->refreshIfNeeded($shop, $days);

                return;
            }

            $shop->refreshOfflineAccessTokenIfNeeded();
        });

        if (! $refreshed) {
            return null;
        }

        $replacement = $this->accessToken($this->reread($shop));

        // ShopifyApi replays only on a different token.
        return ($replacement !== null && $replacement !== $rejected) ? $replacement : null;
    }

    /**
     * The package's own lookup, so each app's column naming stays its business.
     *
     * @return object|null
     */
    protected function shop(string $shopDomain)
    {
        if (! interface_exists(self::SHOP_QUERY) || ! class_exists(self::SHOP_DOMAIN)) {
            return null;
        }

        try {
            $domain = \call_user_func([self::SHOP_DOMAIN, 'fromNative'], $shopDomain);

            return app(self::SHOP_QUERY)->getByDomain($domain);
        } catch (Throwable $e) {
            $this->warn('shop lookup failed', $shopDomain, $e);

            return null;
        }
    }

    /**
     * False for a shop still on a non-expiring token, and for a package without
     * the feature.
     *
     * @param  object  $shop
     */
    protected function canRefresh($shop): bool
    {
        return method_exists($shop, 'hasExpiringOfflineAccess')
            && method_exists($shop, 'refreshOfflineAccessTokenIfNeeded')
            && class_exists(self::REFRESHER)
            && $shop->hasExpiringOfflineAccess();
    }

    /**
     * @param  object|null  $shop
     */
    protected function accessToken($shop): ?string
    {
        if ($shop === null || ! method_exists($shop, 'getAccessToken')) {
            return null;
        }

        try {
            $token = (string) $shop->getAccessToken()->toNative();
        } catch (Throwable $e) {
            return null;
        }

        return $token === '' ? null : $token;
    }

    /**
     * Never throw: a billing call is better served by the token already in hand.
     *
     * @return bool whether it ran without throwing
     */
    protected function attempt(string $shopDomain, callable $work): bool
    {
        try {
            $work();

            return true;
        } catch (Throwable $e) {
            $this->warn('offline access token refresh failed', $shopDomain, $e);

            return false;
        }
    }

    /**
     * @param  object  $shop
     * @return object
     */
    protected function reread($shop)
    {
        try {
            return method_exists($shop, 'refresh') ? $shop->refresh() : $shop;
        } catch (Throwable $e) {
            return $shop;
        }
    }

    protected function warn(string $what, string $shopDomain, Throwable $e): void
    {
        Log::warning("App Manager: {$what}", [
            'shop' => $shopDomain,
            'message' => $e->getMessage(),
        ]);
    }
}
