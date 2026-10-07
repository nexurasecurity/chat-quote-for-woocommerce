=== Chat Quote for WooCommerce – Instant Chat & Order Quote Widget ===
Contributors: prokashsarker2026, freemius
Tags: whatsapp chat, product inquiry, request quote, live chat, woocommerce
Requires at least: 6.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WhatsApp live chat, product inquiry forms, and request-a-quote buttons for WooCommerce. Ask or order from product, shop, and cart pages.

== Description ==

**Chat Quote for WooCommerce** adds WhatsApp chat, product inquiry, and quote requests to WordPress stores. Customers can ask a question, request a quote, or start an order on WhatsApp from the product page — with the product name, price, SKU, quantity, and link attached.

Floating support chat works even when WooCommerce is not installed. Product, shop, and cart buttons unlock when WooCommerce is active. Every Free feature works without a license. Pro is an optional separate package (not locked inside the free plugin).

Useful for wholesale, B2B, custom and made-to-order products, and higher-priced items where shoppers want an answer before they buy.

= Product inquiry, quote request, and WhatsApp chat =

* **Order on WhatsApp (Free):** "Order on WhatsApp" or "Get a Quote" near Add to Cart, on shop cards, and on the cart.
* **Pre-filled WhatsApp message (Free):** Product name, price, SKU, quantity, and URL via `{product_name}`, `{product_price}`, `{product_sku}`, `{product_qty}`, `{product_url}`.
* **Request a Quote form (Free):** Shortcode `[cqfw_quote_form]` on any page. Submissions land in **Chat Quote → Quotes**.
* **Floating live chat (Free):** Name, phone, and message. Stored in your database. Continue on the site or switch to WhatsApp.
* **Send the whole cart (Free):** One click shares cart lines, prices, quantities, and SKUs on WhatsApp.
* **Product Inquiry button and form (Pro):** Ask-about-this-product button, popup form builder, and an Inquiries list in wp-admin.
* **Hide Add to Cart / inquiry-only (Pro):** Per product, keep the inquiry button and hide Add to Cart for catalog-style selling.
* **Variable products (Pro):** Selected variation, attributes (size, color), variation price, and image are saved with the inquiry.
* **Inquiry email (Pro):** Admin alert plus a customer confirmation. Reply from the inquiry screen; status moves to Replied.
* **Inquiry dashboard (Pro):** Search, status tabs, product / date / type filters, bulk actions, pagination, and CSV export.

= Why stores use it =

* Answer product questions before checkout, instead of losing the visitor.
* Take WhatsApp orders and quote requests from the product page.
* Keep chat, quotes, and (with Pro) product inquiries in one WordPress admin.
* Show or hide the chat by page, category, post type, and device.
* Get an email when someone chats or requests a quote — sent on the request, no WP-Cron required.
* Match the button to the theme: colors, text, radius, icons, and placement.

= How it works =

1. Install and activate **Chat Quote for WooCommerce**.
2. Open **Chat Quote → Setup** and save your WhatsApp number (country code + local number).
3. Turn on product, shop, cart, and floating buttons under **Buy Buttons** and **Chat Bubble**.
4. Customers message you from the product, shop, or cart — or from the floating chat on any page.
5. Read chats in **Inbox**, quote forms in **Quotes**, and (Pro) product questions in **Inquiries**.

= Where buttons appear =

* **Single product** — WhatsApp / quote button. Pro can also place a Product Inquiry button beside Add to Cart, after the price, above the cart form, after Add to Cart, or below the cart form.
* **Shop and category archives** — quote or WhatsApp button on each product card.
* **Cart** — "Send Cart to WhatsApp" after the totals.
* **Any page** — floating chat button when it is enabled.

= Free vs Pro =

Free features are complete without payment. Pro ships as a separate package (Freemius). It is not trialware inside the WordPress.org download.

**Free:** WhatsApp number and backup number, message templates, Web WhatsApp, `[cqfw_quote_form]`, product / shop / cart / floating buttons, chat styles 1–2, shop stack layouts, fixed position, display rules, greeting templates, Google Analytics, Facebook Pixel, webhooks, Inbox, Quotes, Reports, and optional instant email.

**Pro:** Product Inquiry button and form builder, inquiry-only (hide Add to Cart), variable-product details, inquiry list with filters and CSV, customer confirmation and admin reply email, multi-agent, business hours, offline and random numbers, time / scroll / click triggers, country and login rules, notification badge, custom image button, absolute position, shop side-by-side and custom layouts, Google Ads conversion, checkout WhatsApp, quote custom fields and attachments, Quotes → Order, PDF / print, and CSV export.

Compare in the plugin: **Chat Quote → Go Pro**.

= Request a Quote shortcode =

`[cqfw_quote_form]`

Tie the form to a product:

`[cqfw_quote_form product_id="123"]`

= On-site live chat =

* Fields: name, phone, and message.
* Product context can be attached from a product page.
* Status: Pending, Read, Replied.
* Same floating card on desktop and mobile (not a separate locked mobile layout).
* Optional redirect to WhatsApp after submit.

= Admin =

* **Setup** — WhatsApp numbers (country dial picker), templates, product button position.
* **Chat Bubble** — display rules, greetings, tracking, and Look & Style.
* **Buy Buttons** — product, shop, cart, and floating toggles.
* **Inbox** — search and fullscreen chat management.
* **Quotes** — quote requests and status.
* **Inquiries (Pro)** — product questions, filters, reply, CSV.
* **Reports** — clicks, top products, trends.
* **Go Pro** on free installs, or **Extra Pro tools** when licensed.
* **Dashboard widget** — clicks and unread snapshot.

= Works with =

* WooCommerce-compatible themes (shop buttons need WooCommerce).
* WordPress Multisite.
* Shared hosting: core email alerts do not depend on WP-Cron.
* Translations: Bengali, Hindi, Spanish, French, German, Arabic, Portuguese, Turkish, Japanese, and Chinese.
* Clean uninstall removes plugin data.
* WordPress Playground live preview on the plugin page.

== Installation ==

1. Go to **Plugins → Add New** in your WordPress admin.
2. Search for **"Chat Quote for WooCommerce"** and click **Install Now**.
3. Click **Activate**.
4. Open **Chat Quote → Setup**.
5. Enter your **WhatsApp phone number** (country code, e.g. digits only `1234567890`).
6. Enable product, shop, cart, and/or floating buttons under **Buy Buttons** and **Chat Bubble**.
7. Optional: open **Chat Bubble → Look & Style** to customize appearance, then save.

= Manual Installation =

1. Download the ZIP from WordPress.org.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Upload, install, activate, then configure under **Chat Quote → Setup**.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =
No. Floating live chat works without WooCommerce. Product, shop, cart, and product-inquiry buttons need WooCommerce.

= Can customers request a quote? =
Yes. Use the WhatsApp quote button, or place `[cqfw_quote_form]` on any page. Submissions are listed under **Chat Quote → Quotes**.

= What is the Product Inquiry button? =
Pro adds an "ask about this product" button and form on single products and the shop. You can place it beside Add to Cart, after the price, or around the cart form. Per product you can force it on, turn it off, or hide Add to Cart (inquiry-only).

= Does product inquiry support variable products? =
Yes, in Pro. The saved inquiry includes the selected variation, attributes such as size and color, the variation price, and the variation image.

= Where are product inquiries stored? =
Pro stores them in `wp_cqfw_inquiries`. Manage them under **Chat Quote → Inquiries** (search, status, product, date, type, bulk actions, CSV export).

= What WhatsApp number format should I use? =
Pick the country code in Setup, then enter the local number. The plugin saves digits only (example: `8801732593040`). A leading 0 is removed.

= Can I use this plugin without WhatsApp? =
Yes. The on-site chat widget works on its own. You can turn WhatsApp buttons off and keep website chat.

= Will the WhatsApp message include product details? =
Yes. From a product or cart, the template can include name, price, SKU, quantity, and URL.

= Can I customize the WhatsApp message? =
Yes. **Chat Quote → Setup**. Variables: `{product_name}`, `{product_price}`, `{product_sku}`, `{product_qty}`, `{product_url}`.

= Where are chat messages stored? =
In `wp_cqfw_chat_messages`. Open **Chat Quote → Inbox** to search, mark read or replied, and delete. Quote forms use `wp_cqfw_quotes`.

= Is Pro required for WhatsApp buttons and live chat? =
No. Those Free features stay unlocked. Pro is optional for the product inquiry form, hide-Add-to-Cart mode, multi-agent, business hours, checkout WhatsApp, and quote-to-order tools. See **Chat Quote → Go Pro**.

= Can I customize button colors and icons? =
Yes, under **Chat Bubble → Look & Style** and **Buy Buttons**.

= How do I get email when a customer chats or sends an inquiry? =
Chat and quote alerts: **Chat Bubble → Settings**. They send with the visitor request (no cron), with an optional hourly cap. Pro inquiry emails go to the admin and can confirm the customer; you can also reply from the inquiry screen.

= How do I change the currency? =
The plugin uses **WooCommerce → Settings → General → Currency options**.

= Does the plugin work on mobile? =
Yes. The floating chat opens as the same corner card on phones and desktops.

= Does it work with any WooCommerce theme? =
Yes, with themes that use standard WooCommerce hooks for product, shop, and cart.

= Does it support WordPress Multisite? =
Yes, including per-site settings and a clean network uninstall.

= Is there a live demo? =
Yes. Use **Live Preview** on the plugin page (WordPress Playground).

 

== Changelog ==

= 1.2.3 =
* Added: Complete global country dial code list (245 countries and territories) including Cameroon (+237) and all international regions
* Added: Interactive real-time search for Country Dial Picker — search by country name, dial code, or ISO code with instant filtering and keyboard navigation (Enter/Esc)
* Improved: Smart phone number synchronization — automatic country detection from pasted international numbers (+...) and dial prefix deduplication
* Improved: Product Inquiry modal telephone field updated to support all 245 countries and regions

= 1.2.2 =
* Added: Variable product auto-detection for Product Inquiries — captures selected variation ID, attributes (e.g. Size, Color), variation price, and gallery image
* Added: Automated customer confirmation email on inquiry submission with full product & message summary
* Added: Direct email reply to customer from WordPress Admin Single Inquiry View with auto-status update to "Replied"
* Added: Single Product Button Position selector with multiple placement options (Beside Add to Cart / Quantity, After Price / Above Short Description, Above Add to Cart Form, After Add to Cart Button, Below Cart Form)
* Added: Per-product inquiry metabox controls (Force Enable, Disable, Custom Position, Hide Add to Cart / Inquiry-Only mode)
* Added: Inquiries List Table with Bulk Actions, Status filter tabs, Date/Product/Status/Type filters, Search bar, and Pagination
* Added: 1-click Export Inquiries to CSV with UTF-8 BOM encoding support
* Improved: Clean, professional UI labels across all admin settings without informal tags
* Fixed: Add to cart replacement button visibility and single-product inline flex layout alignment

= 1.2.1 =
* Improved: Admin setup UX — country dial picker for WhatsApp numbers, floating save toasts, checklist progress animation
* Improved: Live preview matches selected launcher style; sticky save bar
* Improved: Support chat works without WooCommerce (shop features unlock when WC is active)
* Fixed: Freemius pricing AJAX UTF-8 BOM / encoding issues

= 1.2.0 =
* Added: Organized Free global widget controls vs Pro-only panels (single-source settings)
* Added: Go Pro admin page with Free vs Pro matrix (WordPress.org-safe upsell)
* Added: Shop CTA style presets; Pro side-by-side / custom layouts ship only in the premium package
* Added: Display rules, greetings, and basic GA / Pixel / webhook tracking (Free)
* Added: Instant admin email notify (request-driven, optional hourly cap, no WP-Cron)
* Added: Pro package hooks for triggers, multi-agent, hours, Ads, country/login rules, checkout WhatsApp
* Improved: Chat Bubble Settings + Look & Style tabs (merged styles UX)
* Improved: Inbox fullscreen mode for message management
* Improved: Mobile floating chat opens like desktop (floating card + backdrop)
* Improved: Freemius free/premium separation (`is__premium_only`, `@fs_premium_only /includes/pro/`)
* Improved: Admin navigation labels (Setup, Chat Bubble, Buy Buttons, Go Pro / Extra Pro tools)
* Fixed: Guideline 5 — shop side-by-side no longer locked inside the Free build (stripped via Freemius)
* Fixed: Output escaping for shop CTA buttons and settings fields (`wp_kses` / `esc_attr`)
* Fixed: Free settings save no longer wiping Pro options
* Fixed: Hosting-safe paths (upload validation, email queue, prepared SQL ignores)
* Fixed: Readme table name and FAQ wording
* Updated: Tested up to WordPress 7.1

= 1.1.1 =
* Improved: SEO-optimized tags for better plugin discoverability
* Improved: User-friendly readme with detailed feature descriptions
* Improved: Better installation instructions with step-by-step guide
* Improved: Comprehensive FAQ section covering all common questions
* Updated: Tested up to WordPress 7.0.2
* Updated: Requires at least WordPress 6.1

= 1.0.0 =
* Initial release
* Added WooCommerce chat and WhatsApp quote system
* Added floating live chat widget with AJAX messaging
* Added WhatsApp buttons for product, shop, and cart pages
* Added admin message management dashboard with status tracking
* Added analytics dashboard with click tracking and 7-day trends
* Added full button customization (colors, icons, placement)
* Added customizable WhatsApp message template with product variables
* Added WordPress dashboard widget for quick stats overview
* Added responsive mobile-friendly design
* Added clean uninstall with multisite support

== Upgrade Notice ==

= 1.2.3 =
Full global country dial codes (245 countries/territories) including Cameroon (+237), real-time searchable country picker, and smart phone prefix deduplication. Recommended update for all users.

= 1.2.2 =
Enhanced Product Inquiries with variable product detection, customer confirmation emails, direct admin-to-customer email replies, flexible button positions, and advanced filtering. Recommended update.

= 1.2.1 =
Admin setup UX polish (phone dial picker, toasts, checklist), live preview style sync, and soft WooCommerce dependency. Recommended update.

= 1.2.0 =
Free/Pro packaging (Guideline 5), Chat Bubble UX, mobile chat parity, instant email alerts, and Go Pro compare page. Recommended update.

= 1.1.1 =
Improved plugin description, SEO tags, and documentation. No breaking changes. Recommended update for all users.

= 1.0.0 =
Initial stable release of Chat Quote for WooCommerce.
