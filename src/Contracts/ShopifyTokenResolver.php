<?php

namespace HulkApps\AppManager\Contracts;

/**
 * Supplies the Shopify access token the SDK bills with, so the app — not the
 * SDK — decides how tokens are stored and renewed. Bind an implementation via
 * `app-manager.shopify_token_resolver`.
 *
 * Apps on non-expiring tokens need nothing: the default ColumnTokenResolver
 * reads the same column the SDK always read. Apps on expiring offline tokens
 * (required for public apps from 1 Jan 2027) point it at whatever owns their
 * tokens.
 *
 * Called from web requests and queued work alike, so both methods must be safe
 * to call concurrently for one shop.
 */
interface ShopifyTokenResolver
{
    /**
     * A token valid right now, or null. An expiring-token app renews here when
     * the stored one is at or near expiry.
     *
     * @param  string  $shopDomain  the shop's myshopify.com domain
     * @return string|null
     */
    public function token(string $shopDomain): ?string;

    /**
     * Shopify rejected what token() returned: give a replacement, or null.
     *
     * Called at most once per request, after a 401 or the post-deadline 403. The
     * SDK replays only when this returns a token different from the rejected one.
     *
     * @param  string  $shopDomain  the shop's myshopify.com domain
     * @return string|null
     */
    public function refresh(string $shopDomain): ?string;
}
