=== WaNotify - WhatsApp & SMS Automation for WooCommerce ===
Contributors: wanotify
Tags: woocommerce, whatsapp, cod verification, abandoned cart, order notifications, sms
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Official WooCommerce integration for WaNotify SaaS. Instant WhatsApp order notifications, automated Two-Way COD verification, and 30-minute abandoned cart recovery.

== Description ==

WaNotify connects your WooCommerce store to the official Meta WhatsApp Cloud API via the WaNotify automation platform.

= Key Features =
* **Instant WhatsApp Order Confirmations:** Automatically dispatch branded WhatsApp messages with live line items and tracking numbers upon checkout.
* **Two-Way COD Order Verification:** Send interactive WhatsApp buttons ("Confirm Order" & "Cancel Order"). Customer confirmations automatically change WooCommerce order status to "Processing". Customer cancellations update order to "Cancelled" and immediately restock inventory.
* **30-Minute Abandoned Cart Recovery:** Real-time phone number capture at checkout schedules automated WhatsApp reminders with 1-click checkout recovery links.
* **High-Performance Order Storage (HPOS) Ready:** 100% compatible with WooCommerce custom order tables and traditional post storage.
* **Non-Blocking Asynchronous Webhooks:** Webhook payloads are dispatched in the background (<15ms overhead) without slowing down customer checkout.

== Installation ==

1. Upload the `wanotify` folder to `/wp-content/plugins/` or upload `wanotify.zip` directly via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **WooCommerce > WaNotify**.
4. Enter your Store ID and Webhook Secret from your WaNotify Dashboard.
5. Click **Test Live Connection** to verify your setup, then click **Save Changes**.

== Frequently Asked Questions ==

= Does this plugin require an active WaNotify subscription? =
Yes. This plugin links your WooCommerce store to the WaNotify SaaS engine which manages Meta WhatsApp Cloud API credentials, message templates, and automated workflows.

= Is it compatible with HPOS? =
Yes. WaNotify fully supports High-Performance Order Storage (HPOS) and legacy post storage.

= How does COD verification work? =
When a customer places a Cash on Delivery order, WaNotify sends an interactive WhatsApp message. When the customer taps "Confirm Order", WaNotify securely updates the WooCommerce order to "Processing". If they tap "Cancel Order", the order is cancelled and inventory is restocked.

== Changelog ==

= 1.0.0 =
* Initial public release with HPOS support, Two-Way COD verification, and 30-min cart recovery beacon.
