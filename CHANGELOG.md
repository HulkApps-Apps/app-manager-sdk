# Changelog

All notable changes to `appmanager` will be documented in this file

## 3.6.3

Reverts the unreleased promotional-discount work that had been merged to
`master` but never tagged. This release is functionally identical to 3.6.2.

Promotional discounts (automatic, audience, upgrades-only and per-plan
targeting) are resolved in App Manager and injected into each plan's existing
`discount` / `discount_type` / `cycle_count` fields, which this SDK already
applies on the billing page and at charge. No SDK support is required, so the
targeting layer is removed from the SDK:

- Removed `getAutomaticDiscount`, the `discount_code` and `shop_installed_at`
  parameters on `getPlans`, the per-plan override and `upgrades_only` handling
  in the charge controller, and the discount-aware plans-cache changes.
- Coded promotional discounts continue to work through the existing
  `getPromotionalDiscount` path, and app-manager-injected per-plan discounts
  continue to apply unchanged.

No configuration or frontend changes are needed: update the package and clear
caches.

## 3.6.2

Fix: the fail-safe charges table now cancels only the shop's *active* charge
instead of every charge for the shop, so a replayed or overwritten cancel can
no longer flip an unrelated charge row to `cancelled`.

- `cancelCharge` and `cancelChargeHelper` add a `status in (active, ACTIVE)`
  filter before marking a fail-safe charge row `cancelled`; historical and
  already-cancelled rows are left untouched.

No configuration or frontend changes are needed: update the package and clear
caches.

## 3.6.1

Security: a merchant can no longer give their own shop a paid plan without
paying. 3.6.0 closed anonymous and cross-shop access; these two routes could
still be abused by a logged-in merchant on their own shop.

- `active-without-plan` ("choose later") follows the same rule as the billing
  page's choose-later link: it works only during onboarding (no plan, no active
  or cancelled charge, no trial started yet), and only for the shop's
  choose-later plan (`default_plan_id`). Anything else returns 422, so it can no
  longer be used to switch an existing shop to another plan without paying.
- `plan/process/callback` changes the plan only when Shopify reports the charge
  as `active`. A declined, expired or still-pending charge no longer switches
  the plan. The plan, discount, old plan and timestamp are now read from the
  `return_url` Shopify stored on the charge, not from the query string, so the
  callback can no longer be replayed with a different plan.
- Malformed input is refused before anything runs: an unknown or non-text
  `shop` or a non-numeric `charge_id` on the callback, and a missing or
  non-scalar `plan_id` / `shop_domain` on `active-without-plan`.

No configuration or frontend changes are needed: update the package and clear
caches.

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
- New config: `shopify_api_key` and `shopify_api_secret`, read from
  `SHOPIFY_API_KEY` / `SHOPIFY_API_SECRET` by default. Session tokens are
  verified with these two values only, and every misconfiguration names the
  setting to fix: an empty secret is reported through the app's exception
  handler (so error trackers alert), and a wrong secret or a wrong API key
  logs a warning.

Upgrade steps:

1. **Required:** add both keys to the app's `config/app-manager.php`, pointing
   at the env variables that hold its Shopify API key and secret, and make sure
   `APP_MANAGER_SECRET` is set:

   ```php
   'shopify_api_key' => env('SHOPIFY_API_KEY', ''),       // or the app's own name, e.g. SHOPIFY_PUBLIC_KEY
   'shopify_api_secret' => env('SHOPIFY_API_SECRET', ''), // or the app's own name, e.g. SHOPIFY_PRIVATE_KEY
   ```

   Nothing throws if they are missing or wrong: every billing-page request
   returns 401, and an `App Manager:` error (missing secret) or warning (wrong
   secret or key) names the setting to fix.
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
