<img src=".wordpress-org/banner-1544x500.png" alt="Purchase Contract Withdrawal Button for WooCommerce" width="100%">

# Purchase Contract Withdrawal Button for WooCommerce

A WooCommerce plugin that adds a customer-facing "Withdraw from purchase contract" button to the My Account order detail page, with a two-step submission flow, configurable cooling-off period, and automated email confirmation.

Designed to help merchants comply with **EU Directive 2023/2673** — which requires online merchants to provide consumers with a direct online function to withdraw from a contract. In the Czech Republic this is enforced from **19 June 2026** under § 1830a of the Civil Code ("button amendment 2.0"). Equivalent transposition is required across all EU member states.

## Features

- Textual button ("Withdraw from purchase contract") on the order detail page in My Account
- Two-step submission: button → review form → explicit confirmation
- Optional `[pcwb_withdrawal_form]` shortcode for non-logged-in customers (order number + billing email lookup with rate limiting)
- Configurable cooling-off period (default 14 days)
- Date-of-delivery meta box — record when goods were received so the cooling-off period starts from the legally correct moment
- Dedicated "Withdrawals" admin screen (filters, search, CSV export, bulk resolve)
- Order actions: "Submit withdrawal on behalf of customer" + "Mark withdrawal as resolved"
- Configurable eligible order statuses (default: `completed`)
- Configurable post-submission status (default: `on-hold`)
- Customer + admin emails as native `WC_Email` classes — customizable in WooCommerce → Settings → Emails
- Theme-overridable templates in `/templates/`
- Fully translatable (English source, Czech + Slovak bundled)
- HPOS-compatible

## Installation

1. Download the latest release ZIP.
2. Upload via WordPress → Plugins → Add New → Upload Plugin.
3. Activate.
4. Configure at **WooCommerce → Withdrawal Button**.

## Filters and actions

| Hook | Type | Purpose |
|------|------|---------|
| `pcwb_period_days` | filter | Override cooling-off period |
| `pcwb_period_reference_date` | filter | Override the date from which the cooling-off period is counted: `($date, $order)` |
| `pcwb_eligible_statuses` | filter | Override list of eligible order statuses |
| `pcwb_new_status` | filter | Order status applied after submission |
| `pcwb_admin_recipient` | filter | Admin email recipient |
| `pcwb_after_submit` | action | Fires after a successful withdrawal: `($order, $reason, $account, $source)` |
| `pcwb_after_resolve` | action | Fires when an admin marks a withdrawal resolved: `($order, $resolved_by_user_id)` |

## Screenshots

### Admin overview — Withdrawals list

<img src=".wordpress-org/screenshot-1.png" alt="Withdrawals admin overview with filters, CSV export, per-row resolve" width="100%">

### Order edit — cooling-off meta box

<img src=".wordpress-org/screenshot-2.png" alt="Order edit screen with the Withdrawal cooling-off meta box" width="100%">

### Order actions — resolve and submit on behalf

<img src=".wordpress-org/screenshot-3.png" alt="Order actions dropdown showing Mark withdrawal as resolved" width="100%">

### Guest lookup form

<img src=".wordpress-org/screenshot-4.png" alt="Guest lookup form rendered by the [pcwb_withdrawal_form] shortcode" width="100%">

### Guest withdrawal form

<img src=".wordpress-org/screenshot-5.png" alt="Guest withdrawal form after lookup with order summary and confirmation" width="100%">

## Disclaimer

This plugin is not legal advice. Merchants remain responsible for ensuring their full implementation (terms and conditions, refund processing, goods return) complies with applicable law.

## License

GPLv2 or later. See [LICENSE](LICENSE).
