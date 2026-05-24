=== EUcomply Withdrawal Button ===
Contributors: jakubkasparek
Tags: woocommerce, withdrawal, refund, gdpr, eu
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a withdrawal button to WooCommerce My Account orders. Two-step submission with nonce, configurable cooling-off, automated emails.

== Description ==

EUcomply Withdrawal Button adds a clearly-labeled "Withdraw from purchase contract" button to each customer's order detail page in WooCommerce My Account. It implements the two-step submission process required by EU consumer law: the customer clicks the button, reviews their order details, optionally provides a refund bank account and reason, and then explicitly confirms.

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
* **Configurable cooling-off period** — default 14 days, adjustable per store
* **Configurable eligible statuses** — choose which order statuses show the button
* **Configurable post-submission status** — typically On hold or Processing
* **WooCommerce email integration** — customer + admin emails as native WC_Email classes
* **Translatable** — full text domain, .pot included, Czech and Slovak translations bundled
* **Theme-overridable templates** — copy `templates/withdrawal-form.php` into your theme to customize
* **HPOS-compatible** — works with WooCommerce's High-Performance Order Storage

= Filters and actions =

* `ewb_eligible_statuses` — array of statuses where the button is shown
* `ewb_period_days` — cooling-off period override
* `ewb_new_status` — order status applied after submission
* `ewb_admin_recipient` — admin email recipient override
* `ewb_after_submit` ($order, $reason, $account) — fires after a successful submission

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

1. The "Withdraw from purchase contract" button on the order detail page.
2. The two-step submission form.
3. The settings page under WooCommerce → Withdrawal Button.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First release.
