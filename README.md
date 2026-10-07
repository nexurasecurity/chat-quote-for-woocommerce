# Chat Quote for WooCommerce

<div align="center">
  <a href="https://wordpress.org/plugins/chat-quote-for-woocommerce/">
    <img src="https://img.shields.io/badge/WordPress.org-DOWNLOAD_FREE-0073aa?logo=wordpress&style=for-the-badge" alt="Download on WordPress.org">
  </a>
</div>

<div align="center">
  <img src="https://img.shields.io/badge/REQUIRES_WP-6.1+-0073aa?labelColor=555555&style=flat-square" alt="Requires WP 6.1+">
  <img src="https://img.shields.io/badge/REQUIRES_PHP-7.4+-0073aa?labelColor=555555&style=flat-square" alt="Requires PHP 7.4+">
  <img src="https://img.shields.io/badge/TESTED_UP_TO-7.1-73c713?labelColor=555555&style=flat-square" alt="Tested up to 7.1">
  <img src="https://img.shields.io/badge/LICENSE-GPLV2-0073aa?labelColor=555555&style=flat-square" alt="License GPLv2">
</div>
<div align="center">
  <a href="https://github.com/nexurasecurity/chat-quote-for-woocommerce/actions"><img src="https://img.shields.io/github/actions/workflow/status/nexurasecurity/chat-quote-for-woocommerce/php.yml?label=CI&logo=github&style=flat-square" alt="CI Status"></a>
  <a href="https://github.com/nexurasecurity/chat-quote-for-woocommerce/stargazers"><img src="https://img.shields.io/github/stars/nexurasecurity/chat-quote-for-woocommerce?style=flat-square&logo=github" alt="GitHub Stars"></a>
  <a href="https://github.com/nexurasecurity/chat-quote-for-woocommerce/network/members"><img src="https://img.shields.io/github/forks/nexurasecurity/chat-quote-for-woocommerce?style=flat-square&logo=github" alt="GitHub Forks"></a>
  <a href="https://github.com/nexurasecurity/chat-quote-for-woocommerce/issues"><img src="https://img.shields.io/github/issues/nexurasecurity/chat-quote-for-woocommerce?style=flat-square&color=73c713" alt="GitHub Issues"></a>
</div>

Turn customer conversations into WooCommerce sales with WhatsApp buttons, quote requests, and an on-site live chat widget.

Chat Quote for WooCommerce lets shoppers ask product questions, request a quote, share their cart, or contact your team on WhatsApp. Product and cart details can be added to message templates so your team has context before replying. The on-site inbox stores customer messages in WordPress and helps you track their status.

**Website:** [nexurasecurity.com/chat-quote](https://nexurasecurity.com/chat-quote/)  
**WordPress plugin:** [Download the free plugin](https://wordpress.org/plugins/chat-quote-for-woocommerce/)  
**Support:** [support@nexurasecurity.com](mailto:support@nexurasecurity.com)

## Highlights

- Floating, AJAX-powered on-site chat widget with an inbox in WordPress.
- WhatsApp buttons for product pages, shop/archive pages, and the cart.
- Pro product inquiry button and form with variable-product details and an inquiry management screen.
- Optional order and quote calls to action near WooCommerce product controls.
- Product and cart context in WhatsApp messages: product name, price, SKU, quantity, and URL.
- Standalone quote request form using `[cqfw_quote_form]`.
- Customizable button labels, colors, icons, styles, and placement.
- Visibility rules for post types, pages, categories, and devices.
- Greeting templates, message templates, and optional email alerts.
- Click analytics, reports, and basic Google Analytics, Facebook Pixel, and webhook integrations.
- Responsive layout and assets loaded only where relevant.
- Free features continue to work without a paid license. Pro is an optional, separately distributed package.

## Features

### WhatsApp buttons and cart sharing

Add configurable WhatsApp calls to action to product, shop/archive, and cart pages. Customers can send their entire cart to your business in one message. Product and cart message templates can include:

- `{product_name}`
- `{product_price}`
- `{product_sku}`
- `{product_qty}`
- `{product_url}`

Use a primary WhatsApp number and an optional fallback number. WhatsApp opens through `wa.me` links on desktop and mobile.

### On-site chat and inbox

The floating widget lets customers enter their name, phone number, and message. It can include product details when opened from a product page. Customers can continue on the site or switch to WhatsApp after sending. Messages are stored in the WordPress database and can be searched and managed in **Chat Quote → Inbox**, with statuses such as Pending, Read, and Replied.

The widget opens as a floating card on both desktop and mobile. It can also be used without WooCommerce for general support messaging.

### Quote requests

Add a quote form to any page with:

```text
[cqfw_quote_form]
```

To associate a form submission with a product, pass its ID:

```text
[cqfw_quote_form product_id="123"]
```

Submissions are available under **Chat Quote → Quotes**. Pro adds custom quote fields and file attachments, plus tools to convert a quote into a WooCommerce order and export or print a PDF.

### Product inquiries (Pro)

Add a product inquiry button and form to product pages. The selected placement can be configured globally or per product, with options to hide Add to Cart for inquiry-only products. For variable products, inquiries can include the selected variation, its attributes, price, and image. Pro also provides customer confirmation emails, admin replies from the inquiry screen, searchable and filterable inquiry management, and CSV export.

### Admin tools

The WordPress dashboard includes:

- **Setup:** WhatsApp numbers, message templates, and core options.
- **Chat Bubble:** Display rules, greetings, tracking, and appearance controls.
- **Buy Buttons:** Product, shop, cart, and floating calls to action.
- **Inbox:** Search and manage customer messages, including fullscreen mode.
- **Quotes:** Review quote requests and their status.
- **Inquiries (Pro):** Review product questions, reply to customers, filter inquiries, and export them to CSV.
- **Reports:** Click counts, popular products, and trends.
- **Go Pro / Extra Pro tools:** Compare Free and Pro or access licensed tools.
- **Help:** A short setup guide.
- **Dashboard widget:** A quick snapshot of clicks and unread messages.

The plugin's website also showcases its visual controls for widget settings and styling, WooCommerce buttons, the customer inbox, analytics, and Pro tools.

## Free and Pro

The Free plugin is not a time-limited trial; its features do not require a license. Pro is an optional separate package distributed through Freemius. The WordPress.org Free build does not lock Pro features behind a trial.

| Free | Pro |
| --- | --- |
| WhatsApp number, Web WhatsApp links, and message templates | Multiple agents and departments, including sales and support routing |
| Product, shop, cart, and floating chat calls to action | Business hours, schedules, offline handling, and number routing |
| Shortcodes and free style presets | Time, scroll, click, viewport, and exit-intent triggers |
| Fixed-position widget and display rules | Country and login-status rules, notification badge, and custom image button |
| Greeting templates | WhatsApp checkout and advanced conversion tracking, including Google Ads |
| Basic Google Analytics, Facebook Pixel, and webhook click tracking | Quote form custom fields and file attachments |
| Customer inbox, quote requests, reports, and optional email alerts | Convert quotes to WooCommerce orders; PDF/print and CSV export |
| | Product inquiry form, variable-product details, inquiry management, and CSV export |
| Product and shop button controls, including free shop layouts | Side-by-side shop buttons and custom layouts |

Check **Chat Quote → Go Pro** in WordPress for the current feature comparison. Pro checkout is handled by Freemius.

## Website pricing

The product website lists these Pro plans. Prices and plan terms can change; check the [pricing page](https://nexurasecurity.com/chat-quote/) before purchase.

| Plan | Listed price | Listed inclusions |
| --- | --- | --- |
| Monthly | $2.90/month | 1 site license, all Pro features, premium support, monthly updates |
| Annual | $23.88/year | 1 site license, all Pro features, priority support, one year of updates |
| Lifetime | $150 one-time | 1 site license, all Pro features, lifetime support and updates |

Payments are processed by [Freemius](https://freemius.com/). Review the checkout page for the current refund policy and terms.

## Installation

### Install from WordPress

1. In WordPress, go to **Plugins → Add New Plugin**.
2. Search for **Chat Quote for WooCommerce** and select **Install Now**.
3. Select **Activate**.
4. Open **Chat Quote → Setup** and enter your WhatsApp number in international format, using digits only (for example, `1234567890`).
5. Enable the buttons and widget you need under **Buy Buttons** and **Chat Bubble**.
6. Optionally open **Chat Bubble → Look & Style** to customize the appearance, then save.

### Manual installation

1. Download the plugin ZIP from [WordPress.org](https://wordpress.org/plugins/chat-quote-for-woocommerce/).
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**.
3. Upload and install the ZIP, then activate the plugin.
4. Configure it under **Chat Quote → Setup**.

## Requirements and compatibility

- WordPress 6.1 or later.
- PHP 7.4 or later.
- WooCommerce is needed for product, shop, and cart buttons. The floating chat and support messaging can work without WooCommerce.
- Compatible with properly coded WooCommerce themes and WordPress Multisite.
- Clean uninstall removes plugin settings and data when the plugin is deleted.
- Core email alerts are request-driven and do not require WP-Cron.
- Translation-ready, with included translations for Bengali, Hindi, Spanish, French, German, Arabic, Portuguese, Turkish, Japanese, and Chinese.
- A WordPress Playground live preview is available from the plugin page.

## Frequently asked questions

### Do I need WooCommerce?

No. The floating chat and support messaging can work without WooCommerce. WooCommerce is required for product, shop, and cart features.

### What format should I use for the WhatsApp number?

Use the full international number without spaces, dashes, or a plus sign. Non-digit characters are stripped automatically.

### Can I use the plugin without WhatsApp?

Yes. Disable the WhatsApp buttons and use the on-site chat widget by itself.

### Are customer messages saved?

Yes. Messages are stored in the WordPress database (in the `wp_cqfw_chat_messages` table, with the site's actual table prefix) and managed under **Chat Quote → Inbox**. You can search messages, update their status, and delete them.

### Can I customize the WhatsApp message?

Yes. Edit it under **Chat Quote → Setup → Message customers send on WhatsApp**. Product and cart context can populate the template variables listed above.

### How do I receive email alerts for new chats?

Enable email notifications under **Chat Bubble → Settings**. Alerts are sent on a subsequent visitor request without WP-Cron. An hourly cap is available for shared hosting.

### Which currency is used?

Product prices use the store currency configured under **WooCommerce → Settings → General → Currency options**.

### Does it work on mobile and with my theme?

Yes. The widget opens as a floating card on mobile and desktop, and buttons are responsive. The plugin works with properly coded WooCommerce-compatible themes.

### Can I use it on WordPress Multisite?

Yes. It supports per-site settings and network uninstall.

### Is there a demo?

Yes. Open the plugin page and use its **Live Preview** to try the WordPress Playground blueprint.

### What does Product Inquiry add?

Pro adds a product inquiry button and form, optional inquiry-only product settings, variable-product details, customer confirmation emails, admin replies, and an inquiry list with filters and CSV export.

## Screenshots

The product website provides these dashboard and feature screenshots:

1. [Widget settings](https://nexurasecurity.com/assets/cqfw_widget.jpg)
2. [Widget look and style](https://nexurasecurity.com/assets/cqfw_widget_look.jpg)
3. [WooCommerce buy buttons](https://nexurasecurity.com/assets/cqfw_buttons.jpg)
4. [Customer messages inbox](https://nexurasecurity.com/assets/cqfw_messages.jpg)
5. [Click analytics](https://nexurasecurity.com/assets/cqfw_analytics.jpg)
6. [Pro tools](https://nexurasecurity.com/assets/cqfw_pro.jpg)
7. [Multi-agent chat](https://nexurasecurity.com/assets/multi_agent_chat.png)

## Plugin information

- **Version:** 1.2.3
- **License:** GPL-2.0-or-later
- **Author:** Nexura Security
- **Website:** [nexurasecurity.com/chat-quote](https://nexurasecurity.com/chat-quote/)

## Changelog

### 1.2.3

- Added complete global country dial code list covering 245 countries and territories, including Cameroon (+237) and all international regions.
- Added interactive real-time search for the Country Dial Picker — search by country name, dial code, or ISO code with instant filtering and keyboard navigation (Enter/Esc).
- Improved smart phone number synchronization with automatic country detection from pasted international numbers and dial prefix deduplication.
- Improved Product Inquiry modal telephone field to support all 245 countries and regions.

See [CHANGELOG.md](CHANGELOG.md) for full release history.