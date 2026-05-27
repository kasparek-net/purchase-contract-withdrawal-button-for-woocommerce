=== Purchase Contract Withdrawal Button for WooCommerce ===
Contributors: jakubkasparek
Tags: woocommerce, withdrawal, refund, gdpr, eu
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a withdrawal button to WooCommerce My Account orders. Two-step submission with nonce, configurable cooling-off, automated emails.

== Description ==

Purchase Contract Withdrawal Button for WooCommerce adds a clearly-labeled "Withdraw from purchase contract" button to each customer's order detail page in WooCommerce My Account. It implements the two-step submission process required by EU consumer law: the customer clicks the button, reviews their order details, optionally provides a refund bank account and reason, and then explicitly confirms.

After submission:

* The order is moved to a configurable status (default: On hold)
* A note is added to the order with the customer's reason and refund account
* The customer receives a confirmation email
* The store administrator receives a notification email with all submission details

Both emails are registered as standard WooCommerce email classes and can be customized in **WooCommerce → Settings → Emails**.

= Legal context =

This plugin is designed to help merchants comply with EU Directive 2023/2673, which requires online merchants to provide consumers with a direct online function to withdraw from a contract — the same way they could enter into it. In the Czech Republic, this obligation enters into force on **19 June 2026** under § 1830a of the Civil Code (the so-called "button amendment 2.0"). Similar transposition is required across all EU member states.

The plugin is not legal advice. Merchants remain responsible for ensuring their full implementation (including terms and conditions, refund processing, and goods return) complies with applicable law.

= Features =

* **Two-step submission** — button click → form with details → explicit confirmation
* **Guest shortcode** — optional `[pcwb_withdrawal_form]` for non-logged-in customers (order number + billing email lookup, rate-limited)
* **Configurable cooling-off period** — default 14 days, adjustable per store
* **Date of delivery meta box** — admin can record when the goods were received; the cooling-off period then runs from that date (legally correct under EU law)
* **Configurable eligible statuses** — choose which order statuses show the button
* **Configurable post-submission status** — typically On hold or Processing
* **WooCommerce email integration** — customer + admin emails as native WC_Email classes
* **Admin overview** — dedicated "Withdrawals" screen under WooCommerce: filter by pending/resolved, search, CSV export, bulk "Mark as resolved"
* **Order actions** — admins can submit a withdrawal on behalf of the customer (e.g. phone request) and mark requests resolved from the order edit screen
* **Translatable** — full text domain, .pot included, Czech and Slovak translations bundled
* **Theme-overridable templates** — copy `templates/withdrawal-form.php` or `templates/guest-lookup.php` into your theme to customize
* **HPOS-compatible** — works with WooCommerce's High-Performance Order Storage

= Filters and actions =

* `pcwb_eligible_statuses` — array of statuses where the button is shown
* `pcwb_period_days` — cooling-off period override
* `pcwb_period_reference_date` ($date, $order) — override the cooling-off reference date
* `pcwb_new_status` — order status applied after submission
* `pcwb_admin_recipient` — admin email recipient override
* `pcwb_after_submit` ($order, $reason, $account, $source) — fires after a successful submission (source = customer|guest|admin)
* `pcwb_after_resolve` ($order, $resolved_by_user_id) — fires when an admin marks a withdrawal resolved

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install via Plugins → Add New.
2. Activate the plugin through the Plugins menu.
3. Go to **WooCommerce → Withdrawal Button** to configure the cooling-off period and eligible statuses.
4. (Optional) Customize the customer and admin email templates in **WooCommerce → Settings → Emails**.

== Frequently Asked Questions ==

= Where does the button appear? =

In the customer's My Account → Orders → individual order detail page, below the order table. It only appears when the order matches an eligible status and is within the configured cooling-off period.

= Can the customer submit multiple withdrawals? =

No. Once a withdrawal is submitted, the button is replaced with a notice showing the submission date and a contact email.

= Does the plugin issue refunds automatically? =

No. The plugin records the customer's intent to withdraw — it does not move money. Refunds are processed manually (or via your payment gateway) after the goods are received. The plugin notifies the admin via email so the merchant can follow up.

= Can guest orders use the button? =

No. The button requires the customer to be logged in (their account must be linked to the order). This matches the typical "logged-in My Account" flow.

= How do I customize the email content? =

Go to **WooCommerce → Settings → Emails**, then select either "Withdrawal confirmation (customer)" or "Withdrawal notification (admin)". You can edit subject, heading, and additional content. For deeper customization, copy template files from `templates/emails/` into your theme.

= Is this plugin GDPR-compliant? =

The plugin only stores data the customer has explicitly submitted (reason, refund bank account) as order metadata, alongside the existing order record. It does not transmit data to third parties.

== Screenshots ==

1. Withdrawals admin overview — filter by pending/resolved, search, date range, bulk actions, CSV export, per-row Resolve.
2. Order edit screen with the "Withdrawal cooling-off" meta box — set the date of delivery, see the cooling-off deadline and submission details.
3. Order actions dropdown — submit a withdrawal on behalf of the customer, or mark an existing one as resolved.
4. Guest lookup form rendered by the [pcwb_withdrawal_form] shortcode on a public page — order number + billing email.
5. Guest withdrawal form after successful lookup — pre-filled order summary, optional refund account and reason, explicit confirmation.

== Changelog ==

= 1.2.3 =
* Removed: "Custom button CSS" textarea setting and the `pcwb_custom_css` option. WordPress.org guidelines do not allow plugins to accept arbitrary CSS input; the bundled stylesheet remains, and themes can override styles in their own files.
* Replaced inline arrow-function `sanitize_callback` for `pcwb_guest_enabled` with a named class method.

= 1.2.2 =
* Code quality: addressed Plugin Check warnings — annotated CSV-streaming filesystem calls, admin list-table filter parameters, and intentional WooCommerce email hooks with explanatory `phpcs:ignore` comments.
* Internal: renamed template-scoped `$completed` variable to `$pcwb_completed` in admin email templates.

= 1.2.1 =
* Added `Requires Plugins: woocommerce` header to declare WooCommerce as a required dependency (WP 6.5+).

= 1.2.0 =
* New: optional `[pcwb_withdrawal_form]` shortcode for non-logged-in customers (order number + email lookup with rate limiting, short-lived submission token).
* New: dedicated "Withdrawals" admin screen under WooCommerce — list, filter (pending/resolved/all), date range, search, bulk "Mark as resolved", and CSV export.
* New: "Withdrawal cooling-off" order meta box — enter the date the goods were delivered to make the cooling-off period start from the legally correct moment.
* New: order actions — "Submit withdrawal on behalf of customer" and "Mark withdrawal as resolved".
* New filter: `pcwb_period_reference_date` to override the reference date programmatically.
* New action: `pcwb_after_resolve` ($order, $resolved_by) fires when a withdrawal is marked resolved.
* Internal: `PCWB_Frontend::do_submit()` and `::resolve()` are now reusable across customer, guest and admin flows.

= 1.1.0 =
* New: configurable button position (after order table, before order table, top of view-order page, or orders list row action).
* New: Custom CSS field in settings — inject styles for the withdrawal button and form without touching theme files.
* New filter: `pcwb_button_positions` to register additional hooks.

= 1.0.1 =
* Renamed plugin and slug to comply with WP.org plugin naming guidelines.
* Fixed: replaced `esc_url_raw` with `esc_url` for displayed URL in plain text admin notification.
* Updated function/class prefix to `pcwb_` / `PCWB_` for collision safety.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.3 =
The "Custom button CSS" setting has been removed per WordPress.org guidelines. Override styles in your theme stylesheet instead.

= 1.2.2 =
Code quality improvements addressing Plugin Check feedback. No functional changes.

= 1.2.1 =
Declares WooCommerce as a required plugin dependency (WP 6.5+).

= 1.2.0 =
Adds a guest shortcode for non-logged-in customers, a dedicated Withdrawals admin screen with CSV export, a date-of-delivery meta box, and order actions for resolving and submitting on behalf.

= 1.1.0 =
Adds configurable button position and a Custom CSS field in settings.

= 1.0.1 =
Naming and security cleanup per WP.org review.

= 1.0.0 =
First release.
