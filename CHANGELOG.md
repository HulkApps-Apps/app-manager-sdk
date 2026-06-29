# Changelog

All notable changes to `appmanager` will be documented in this file

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
