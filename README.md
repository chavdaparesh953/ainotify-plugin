# WaNotify WooCommerce Plugin (`wanotify`)

Official WordPress & WooCommerce integration plugin for **WaNotify** — WhatsApp & SMS Automation SaaS for E-commerce.

[![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0+-96588a.svg)](https://woocommerce.com/)
[![HPOS](https://img.shields.io/badge/HPOS-100%25%20Compatible-brightgreen.svg)](https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-blue.svg)](LICENSE)

---

## 🚀 Key Features

- **⚡ 1-Click Connection:** No complicated WooCommerce REST API keys needed. Connect via Store ID & Webhook Secret.
- **🔄 Two-Way COD Order Verification:** Sends WhatsApp message with interactive `[Confirm]` and `[Cancel]` buttons. 
  - Customer taps **Confirm** ➔ Order status automatically updates to **Processing** with internal audit note.
  - Customer taps **Cancel** ➔ Order status updates to **Cancelled**, internal note added, and WooCommerce stock is automatically restored.
- **🛒 30-Minute Abandoned Cart Recovery:** Real-time phone number capture beacon on checkout input blur. Syncs checkout cart and triggers high-converting WhatsApp recovery sequence.
- **📦 Real-Time Order & Tracking Updates:** Instant WhatsApp notifications when orders are placed, dispatched, and delivered.
- **🛡️ HPOS (High-Performance Order Storage) Compatible:** 100% compatible with WooCommerce custom order tables (`custom_order_tables`).
- **⚡ Asynchronous Non-Blocking Webhooks:** Webhook dispatches run asynchronously (`blocking => false`), maintaining lightning-fast merchant checkout speeds (<15ms).
- **🔒 HMAC-SHA256 Signed:** All outgoing and incoming payloads are verified using cryptographic signatures.

---

## 📂 Project Structure

```
wanotify/
├── wanotify.php                       # Main plugin entry & HPOS declaration
├── readme.txt                         # WordPress.org standard readme
├── build-zip.sh                       # Production zip packaging script
├── includes/
│   ├── class-wanotify-api-client.php   # HMAC signature generator & webhook dispatcher
│   ├── class-wanotify-order-handler.php# Order hooks & two-way REST action endpoints
│   ├── class-wanotify-cart-tracker.php # Real-time checkout phone capture beacon
│   └── class-wanotify-admin.php        # WooCommerce > WaNotify settings & live test
└── assets/
    ├── css/
    │   └── admin.css                  # Modern WooCommerce admin settings styling
    └── js/
        ├── admin.js                   # AJAX connection test & helper interactions
        └── cart-tracker.js            # Debounced phone capture on checkout blur
```

---

## 📦 How to Install

### Option A: Upload ZIP (Recommended)
1. Download `wanotify.zip` (run `./build-zip.sh` to generate the latest build).
2. In WordPress Admin, go to **Plugins > Add New > Upload Plugin**.
3. Choose `wanotify.zip` and click **Install Now**.
4. Click **Activate Plugin**.

### Option B: Manual Folder Upload
1. Copy or clone this repository into `/wp-content/plugins/wanotify`.
2. Activate **WaNotify** from **Plugins** menu.

---

## ⚙️ Configuration

1. In WordPress Admin, navigate to **WooCommerce > WaNotify**.
2. Enter your **Store ID** and **Webhook Secret** from your WaNotify SaaS Dashboard.
3. (Optional) Enter your **Inbound API Key** for direct REST write-back actions.
4. Click **Test Live Connection** to verify end-to-end communication.
5. Click **Save Changes**.

---

## 🛠️ REST API Endpoints

### Two-Way COD Order Action
```http
POST /wp-json/wanotify/v1/order-action
Content-Type: application/json
X-WaNotify-API-Key: wn_live_...
X-WC-Webhook-Signature: <base64_hmac_sha256>

{
  "order_id": 1042,
  "action": "CONFIRMED" // or "CANCELLED"
}
```

---

## 🏗️ Build Package

To build the distributable zip archive:
```bash
./build-zip.sh
```
This generates `wanotify.zip` ready for client stores and WordPress uploads.
