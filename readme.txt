=== Chat Quote for WooCommerce – Instant Chat & Order Quote Widget ===
Contributors: prokashsarker2026, freemius
Tags: customer support, whatsapp chat, whatsapp order, woocommerce, woocommerce quote
Requires at least: 6.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add "Order on WhatsApp" buttons and a built-in live chat widget to your WooCommerce store. Boost sales with instant customer communication.

== Description ==

**Chat Quote for WooCommerce** is a lightweight WooCommerce plugin for on-site chat and WhatsApp quotes. Customers can request quotes, ask pre-sale questions, and start orders via WhatsApp — while every Free feature keeps working without a license.

When a customer messages from a product page, the **product name, price, SKU, and link** can be attached automatically so you know what they want right away.

= Why choose Chat Quote for WooCommerce? =

* **Order on WhatsApp:** Add an "Order on WhatsApp" or "Get a Quote" button near Add to Cart.
* **Automatic Product Context:** Product name, price, SKU, and URL can be drafted into the WhatsApp message.
* **Built-in Live Chat Widget:** AJAX-powered floating chat; messages are stored in your WordPress database.
* **Same open experience on mobile:** Floating chat opens as a corner card on phones — matching desktop (not a different locked flow).
* **Instant email alerts:** Optional admin email when customers chat or request a quote — request-driven, no WP-Cron required.
* **Full Cart Support:** Share the **entire WooCommerce cart** via WhatsApp in one click.
* **Shop & Product CTAs:** Configurable shop/archive and product buttons with Free style presets.
* **Display rules (Free):** Show or hide by post type, page/category rules, and device (desktop/mobile).
* **Greetings (Free):** Greeting templates for a polished first impression.
* **Basic tracking (Free):** Google Analytics, Facebook Pixel, and webhook hooks for click events.
* **Analytics Dashboard:** Button clicks, top products, trends, and pending messages.
* **Customizable Buttons:** Colors, text, radius, icons for product, shop, floating, and cart.
* **Flexible Placement:** Before Add to Cart, after it, or in product meta.
* **WhatsApp Fallback Number:** Primary and backup numbers so messages still reach your team.
* **Auto-Redirect to WhatsApp:** Optionally open WhatsApp after an on-site chat submit.
* **Message Template Variables:** `{product_name}`, `{product_price}`, `{product_sku}`, `{product_qty}`, `{product_url}`.
* **Mobile-Friendly & Lightweight:** Assets load only where needed.
* **Translation Ready (10 Languages):** Bengali, Hindi, Spanish, French, German, Arabic, Portuguese, Turkish, Japanese, and Chinese.
* **WordPress Playground Ready:** Live Preview blueprint — no install required.

= Free vs Pro (optional upgrade) =

Every Free feature works without payment. Pro is an **optional separate package** (distributed via Freemius). Pro code is **not locked inside** the WordPress.org Free build (Guideline 5 — no trialware).

**Included in Free:** WhatsApp number & templates, Web WhatsApp, shortcodes, product/shop/cart/floating CTAs, Free style presets (chat Style 1–2, shop stack layouts), fixed position, display rules, greeting templates, basic GA/Pixel/webhooks, inbox, quotes, reports, and optional instant email notify.

**Available in Pro:** Multi-agent, business hours, offline/random numbers, time/scroll/click triggers, country & login rules, notification badge, custom image button, absolute position, shop side-by-side & custom builder, Google Ads conversion, advanced tracking variables, checkout WhatsApp, quote custom fields/attachments, Quotes → Order, PDF/print, and CSV export.

Compare inside the plugin: **Chat Quote → Go Pro**. Checkout (if you choose Pro) is handled by Freemius in the dashboard.

= Where do buttons appear? =

* Single Product Pages - "Chat on WhatsApp" button
* Shop / Archive Pages - Quote / Buy via WhatsApp on product cards
* Cart Page - "Send Cart to WhatsApp" after cart totals
* All Pages - Floating chat button (when enabled)

= Custom Quote Form Shortcode =

Place a standalone "Request a Quote" form on any page:
`[cqfw_quote_form]`

Optional product link for the Quotes dashboard:
`[cqfw_quote_form product_id="123"]`

Submissions appear under **Chat Quote → Quotes**.

= On-Site Chat Widget =

* Customers can enter **name**, **phone**, and **message**
* Product details can be attached from product pages
* After sending, continue on-site or switch to WhatsApp
* Messages use status tracking: Pending → Read → Replied
* Opens as a floating card on desktop and mobile

= Admin Dashboard Features =

* **Setup** - WhatsApp numbers, templates, and core options
* **Chat Bubble** - Settings (display, greetings, tracking) + Look & Style (appearance)
* **Buy Buttons** - Product, shop, cart, and floating CTA toggles
* **Inbox** - Search and manage customer chat (fullscreen mode available)
* **Quotes** - Quote request list and status
* **Reports** - Clicks, top products, trends
* **Go Pro** - Free vs Pro comparison (Free installs) or **Extra Pro tools** when licensed
* **Help** - Short setup guide
* **WP Dashboard Widget** - Quick click and unread snapshot

= Plugin Compatibility =

* Works with any WooCommerce-compatible theme
* Compatible with WordPress Multisite
* Clean uninstall - removes plugin settings and data on deletion
* Shared-hosting friendly: core alerts do not depend on WP-Cron

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
No. Floating chat and support messaging work without WooCommerce. Install and activate WooCommerce when you want product, shop, and cart WhatsApp buttons.

= What WhatsApp number format should I use? =
Use the full international number without spaces, dashes, or plus signs (e.g. `1234567890`). Non-digits are stripped automatically.

= Can I use this plugin without WhatsApp? =
Yes. The on-site chat widget works on its own. You can disable WhatsApp buttons and use website chat only.

= Does it support WhatsApp? =
Yes. The plugin uses `wa.me` deep links for desktop and mobile WhatsApp.

= Will the WhatsApp message include product details? =
Yes, when started from a product or cart context the template can include name, price, SKU, quantity, and URL.

= Can I customize the WhatsApp message? =
Yes. **Chat Quote → Setup → Message customers send on WhatsApp**. Variables: `{product_name}`, `{product_price}`, `{product_sku}`, `{product_qty}`, `{product_url}`.

= Where are chat messages stored? =
In a custom table (`wp_cqfw_chat_messages`) in your WordPress database. Manage them under **Chat Quote → Inbox**.

= Are messages stored? =
Yes, with status tracking. You can view, search, mark read/replied, and delete.

= Is Pro required? =
No. Free features never expire or get locked. Pro is an optional separate package if you need multi-agent, hours, advanced triggers, Ads conversion, checkout WhatsApp, or quote→order tools. See **Chat Quote → Go Pro**.

= Can I customize button colors and icons? =
Yes, under **Chat Bubble → Look & Style** and related Buy Button settings.

= How do I get email when a customer chats? =
Enable email notify under **Chat Bubble → Settings**. Alerts send on the next visitor request (no cron). You can set an hourly cap for shared hosting.

= How do I change the currency? =
The plugin uses your store currency (**WooCommerce → Settings → General → Currency options**).

= Is it performance friendly? =
Yes. CSS/JS load only where buttons or the widget are active. AJAX and caching are used for queries.

= Does the plugin work on mobile? =
Yes. The floating chat opens the same way on mobile as on desktop (corner floating card). Buttons are responsive.

= Does it work with any WooCommerce theme? =
Yes, with properly coded WooCommerce themes.

= Does it support WordPress Multisite? =
Yes, including per-site settings and clean network uninstall.

= Is there a live demo? =
Yes. Use **Live Preview** on the plugin page (WordPress Playground).

 

== Changelog ==

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

= 1.2.1 =
Admin setup UX polish (phone dial picker, toasts, checklist), live preview style sync, and soft WooCommerce dependency. Recommended update.

= 1.2.0 =
Free/Pro packaging (Guideline 5), Chat Bubble UX, mobile chat parity, instant email alerts, and Go Pro compare page. Recommended update.

= 1.1.1 =
Improved plugin description, SEO tags, and documentation. No breaking changes. Recommended update for all users.

= 1.0.0 =
Initial stable release of Chat Quote for WooCommerce.
