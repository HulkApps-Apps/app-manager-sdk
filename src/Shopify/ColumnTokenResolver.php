<?php

namespace HulkApps\AppManager\Shopify;

use HulkApps\AppManager\Contracts\ShopifyTokenResolver;
use Illuminate\Support\Facades\DB;

/**
 * The default resolver: reads the token straight from the configured shop
 * table and column, which is exactly what the SDK has always done.
 *
 * Correct for non-expiring offline tokens. It cannot renew anything, so an app
 * that moves to expiring tokens must bind its own resolver instead.
 */
class ColumnTokenResolver implements ShopifyTokenResolver
{
    public function token(string $shopDomain): ?string
    {
        $token = DB::table(config('app-manager.shop_table_name', 'users'))
            ->where(config('app-manager.field_names.name', 'name'), $shopDomain)
            ->value(config('app-manager.field_names.shopify_token', 'shopify_token'));

        return ($token === null || $token === '') ? null : (string) $token;
    }

    public function refresh(string $shopDomain): ?string
    {
        // A plain column has no way to mint a new token.
        return null;
    }
}
