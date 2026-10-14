# Changelog

All notable changes to `appmanager` will be documented in this file

## 3.6.0

Security: the `/api/app-manager/*` routes now require authentication.
Previously every route except `users` and `store-charge` was open, so anyone who
knew a shop's domain could change its plan, cancel its Shopify charge or
overwrite the failsafe database.

- App Manager–only routes (`burst-cache`, `fail-safe-backup`,
  `fail-safe-backup-full`, `cancel-charge`, `plan/activate-global`,
  `plan/cancel-global`) now require the `token` header, like `users` and
  `store-charge`. App Manager already sends it, so nothing changes for it.
- Routes the billing page calls (`plans`, `plan-features`, `marketing-banners`,
  `plan/process/{plan_id}`, `active-without-plan`) now require a Shopify session
  token (`Authorization: Bearer …`), or the `token` header. The shop is taken
  from the session token and overrides any `shop` / `shop_domain` in the request.
- `plan/process/callback` stays open: Shopify redirects the merchant there.
- The `token` header is compared in constant time, and is always rejected when
  `APP_MANAGER_SECRET` is empty.
- New config: `shopify_api_key` and `shopify_api_secret`, which fall back to
  `shopify-app.*` and `shopify.*`. When no Shopify API secret is found at all,
  every billing-page call returns 401 and a warning is logged.

Upgrade steps:

1. Make sure the app's Shopify API key and secret are configured
   (`SHOPIFY_API_KEY` / `SHOPIFY_API_SECRET`, `config/shopify-app.php` or
   `config/shopify.php`), and `APP_MANAGER_SECRET` is set.
2. Upgrade `app-manager-web-components` to the release that sends the session
   token. Apps that call these routes from their own frontend (for example
   `app-manager-vue`) must send the session token in the `Authorization` header.

## 3.5.0

Support for Shopify's expiring offline access tokens (required for public apps
from 1 January 2027). Fully backward compatible: an app that changes nothing
keeps the exact previous behaviour.

- New `HulkApps\AppManager\Contracts\ShopifyTokenResolver` contract. The SDK now
  asks it for a shop's Admin API token instead of reading the token column
  directly. Opt in with the `shopify_token_resolver` config key; when unset, the
  default `ColumnTokenResolver` reads the configured column exactly as before.
- Every SDK call to Shopify (create, confirm and cancel recurring charges) now
  goes through `HulkApps\AppManager\Shopify\ShopifyApi`, which retries once with
  a replacement token after a 401 — or Shopify's post-deadline 403 for a
  non-expiring token. Never loops, and only replays with a different token.
- A cancellation Shopify did not accept is now reported instead of silently
  ignored: previously the merchant stayed billed while the app believed the
  charge was cancelled. Cancelling for a shop with no token (usually
  uninstalled — Shopify cancels those itself) is logged, not thrown.
- `cancel-charge` returns 404 for an unknown shop instead of a fatal error.
- New `shopify_timeout` config (default 20s) bounds each Shopify call, which
  previously had no timeout at all. `0` restores the old behaviour.
- Declared the HTTP client's properties, removing PHP 8.2+ dynamic-property
  deprecations on every request (a hard error from PHP 9).
- Still supports PHP 7.3 through 8.4.

Nothing else is required: apps that do not set `shopify_token_resolver` keep the
previous behaviour, with `shopify_timeout` (20s) the only change they will
notice.

## 3.4.0

- Capture the discount snapshot (value, type, duration, source) on the charge at
  creation and store it in the failsafe `charges` table, so the billing page can
  show the true effective price over time (matching Shopify's
  `durationLimitInIntervals`) instead of recomputing from current discount config.
- Embed the active charge's effective pricing (effective price, strike price,
  discount end date, remaining intervals) directly on the matching plan in the
  `plans`/`plan` response as `active_charge_pricing`, so it travels with the plan
  payload the frontend already consumes.
- Custom discounts are now one-time: added `discount_plan.used`; a consumed custom
  discount is no longer offered (`getPlans`/`getPlan`).
- The charge discount snapshot now distinguishes a custom discount from a plan's
  own discount: plans carry a `discount_is_custom` flag and the snapshot records
  `discount_source` as `custom`, `plan`, or `promotional`.

## 1.0.0

- initial release
