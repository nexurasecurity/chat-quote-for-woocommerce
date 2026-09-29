# Chat Quote for WooCommerce

Turn customer conversations into WooCommerce sales with WhatsApp buttons, quote requests, and an on-site live chat widget.

Chat Quote for WooCommerce lets shoppers ask product questions, request a quote, share their cart, or contact your team on WhatsApp. Product and cart details can be added to message templates so your team has context before replying. The on-site inbox stores customer messages in WordPress and helps you track their status.

**Website:** [nexurasecurity.com/chat-quote](https://nexurasecurity.com/chat-quote/)  
**WordPress plugin:** [Download the free plugin](https://wordpress.org/plugins/chat-quote-for-woocommerce/)  
**Support:** [support@nexurasecurity.com](mailto:support@nexurasecurity.com)

## Highlights

- Floating, AJAX-powered on-site chat widget with an inbox in WordPress.
- WhatsApp buttons for product pages, shop/archive pages, and the cart.
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

### Admin tools

The WordPress dashboard includes:

- **Setup:** WhatsApp numbers, message templates, and core options.
- **Chat Bubble:** Display rules, greetings, tracking, and appearance controls.
- **Buy Buttons:** Product, shop, cart, and floating calls to action.
- **Inbox:** Search and manage customer messages, including fullscreen mode.
- **Quotes:** Review quote requests and their status.
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

- **Version:** 1.2.1
- **License:** GPL-2.0-or-later
- **Author:** Nexura Security
- **Website:** [nexurasecurity.com/chat-quote](https://nexurasecurity.com/chat-quote/)

## Changelog

### 1.2.1

- Improved setup with a country dial picker, save notifications, and checklist progress.
- Improved live preview style synchronization and sticky save controls.
- Enabled support chat without WooCommerce; WooCommerce features appear when WooCommerce is active.
- Fixed Freemius pricing AJAX UTF-8 BOM and encoding issues.

### 1.2.0

- Organized Free widget controls and Pro-only panels.
- Added the Go Pro Free-vs-Pro comparison page and free shop CTA style presets.
- Added Free display rules, greetings, and basic Google Analytics, Pixel, and webhook tracking.
- Added optional instant admin email alerts without WP-Cron.
- Improved chat bubble settings, inbox fullscreen mode, and mobile chat behavior.
- Separated premium code into the Freemius Pro package and improved settings handling, escaping, and hosting compatibility.

### 1.1.1

- Improved plugin description, tags, installation guide, and FAQ.
- Updated tested WordPress version to 7.0.2.

### 1.0.0

- Initial release with WooCommerce WhatsApp buttons, floating live chat, inbox, analytics, button customization, message templates, and Multisite uninstall support.