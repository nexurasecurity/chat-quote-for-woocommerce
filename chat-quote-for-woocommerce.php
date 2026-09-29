<?php

/**
 * Plugin Name: Chat Quote for WooCommerce – Instant Chat & Order Quote Widget
 * Plugin URI: https://wordpress.org/plugins/chat-quote-for-woocommerce/
 * Description: WhatsApp chat and quote buttons for WordPress — floating support works without WooCommerce; shop buttons unlock when WooCommerce is active.
 * Version: 1.2.1
 * Author: Prokash Sarker
 * Author URI: https://profiles.wordpress.org/prokashsarker2026/
 * Text Domain: chat-quote-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Chat Quote
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( 'cqfw_fs' ) ) {
    cqfw_fs()->set_basename( false, __FILE__ );
} else {
    if ( !function_exists( 'cqfw_fs' ) ) {
        // Create a helper function for easy SDK access.
        function cqfw_fs() {
            global $cqfw_fs;
            if ( !isset( $cqfw_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
                $cqfw_fs = fs_dynamic_init( array(
                    'id'               => '39988',
                    'slug'             => 'chat-quote-for-woocommerce',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_254baf0a7321658b5fb9e7314047d',
                    'is_premium'       => false,
                    'premium_suffix'   => 'premium',
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'is_org_compliant' => true,
                    'menu'             => array(
                        'slug'    => 'cqfw-settings',
                        'contact' => true,
                        'support' => false,
                        'account' => true,
                        'pricing' => true,
                    ),
                    'is_live'          => true,
                ) );
            }
            return $cqfw_fs;
        }

        // Init Freemius.
        cqfw_fs();
        // Signal that SDK was initiated.
        do_action( 'cqfw_fs_loaded' );
        // Register Freemius uninstall cleanup hook.
        cqfw_fs()->add_action( 'after_uninstall', 'cqfw_fs_uninstall_cleanup' );
        // Keep Freemius pricing/account pages usable (min height for React mount).
        add_action( 'admin_head', 'cqfw_fs_admin_sdk_page_styles' );
    }
}
/**
 * Minimal CSS for Freemius SDK admin pages so pricing React app has room to render.
 *
 * @return void
 */
function cqfw_fs_admin_sdk_page_styles() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page check.
    $page = ( isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '' );
    if ( !$page || !preg_match( '/^cqfw-settings-(pricing|account|contact|affiliation|addons)$/', $page ) ) {
        return;
    }
    echo '<style id="cqfw-fs-sdk-page">
		#fs_pricing.wrap,#fs_pricing_wrapper{min-height:520px;width:100%;max-width:100%;box-sizing:border-box;}
		#fs_pricing.wrap{margin:12px 20px 24px 0;}
	</style>';
}

/**
 * Freemius uninstall cleanup callback.
 */
function cqfw_fs_uninstall_cleanup() {
    delete_option( 'cqfw_settings' );
    delete_option( 'cqfw_version' );
    delete_option( 'wcq_settings' );
    delete_option( 'wcq_version' );
    delete_option( 'cqfw_custom_form_fields' );
    delete_option( 'cqfw_business_hours_schedule' );
    delete_option( 'cqfw_whatsapp_agents' );
    delete_option( 'cqfw_pending_email_queue' );
    delete_option( 'cqfw_widget_controls' );
    if ( is_multisite() ) {
        $cqfw_sites = get_sites( array(
            'network_id' => get_current_network_id(),
        ) );
        foreach ( $cqfw_sites as $cqfw_site ) {
            switch_to_blog( $cqfw_site->blog_id );
            delete_option( 'cqfw_settings' );
            delete_option( 'cqfw_version' );
            delete_option( 'wcq_settings' );
            delete_option( 'wcq_version' );
            delete_option( 'cqfw_custom_form_fields' );
            delete_option( 'cqfw_business_hours_schedule' );
            delete_option( 'cqfw_whatsapp_agents' );
            delete_option( 'cqfw_pending_email_queue' );
            delete_option( 'cqfw_widget_controls' );
            restore_current_blog();
        }
    }
}

/**
 * Whether the current install can run Pro features (valid license / trial).
 * Runtime gate for the premium package only. Free WordPress.org builds have
 * Pro code stripped by Freemius — do not use this to lock code that ships in free.
 *
 * @return bool
 */
function cqfw_can_use_pro() {
    return function_exists( 'cqfw_fs' ) && cqfw_fs()->can_use_premium_code();
}

/**
 * Whether this build includes premium-only code (premium ZIP from Freemius).
 * Freemius strips blocks wrapped with is__premium_only() from the free ZIP.
 *
 * @return bool
 */
function cqfw_is_premium_build() {
    return function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only();
}

/**
 * Whether WooCommerce is active.
 *
 * @return bool
 */
function cqfw_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Admin capability: WooCommerce shops use manage_woocommerce; without WC use manage_options
 * so floating chat / support settings stay available.
 *
 * @return string
 */
function cqfw_get_admin_capability() {
    return ( cqfw_woocommerce_active() ? 'manage_woocommerce' : 'manage_options' );
}

define( 'CQFW_VERSION', '1.2.1' );
define( 'CQFW_FILE', __FILE__ );
define( 'CQFW_BASENAME', plugin_basename( __FILE__ ) );
define( 'CQFW_PATH', plugin_dir_path( __FILE__ ) );
define( 'CQFW_URL', plugin_dir_url( __FILE__ ) );
require_once CQFW_PATH . 'includes/class-loader.php';
require_once CQFW_PATH . 'includes/class-settings.php';
require_once CQFW_PATH . 'includes/class-analytics.php';
require_once CQFW_PATH . 'includes/class-whatsapp.php';
require_once CQFW_PATH . 'includes/class-cart.php';
require_once CQFW_PATH . 'includes/class-dashboard.php';
require_once CQFW_PATH . 'includes/class-quote-form.php';
require_once CQFW_PATH . 'includes/class-quote-manager.php';
require_once CQFW_PATH . 'includes/class-chat-styles.php';
require_once CQFW_PATH . 'includes/class-shop-button-styles.php';
require_once CQFW_PATH . 'includes/class-widget-controls.php';
require_once CQFW_PATH . 'includes/class-email-notify.php';
require_once CQFW_PATH . 'includes/class-upgrade.php';
require_once CQFW_PATH . 'includes/class-installer.php';
// Load Pro modules only in the premium build (folder also listed in @fs_premium_only).
if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) {
    if ( file_exists( CQFW_PATH . 'includes/pro/class-pro-loader.php' ) ) {
        require_once CQFW_PATH . 'includes/pro/class-pro-loader.php';
    }
}
register_activation_hook( __FILE__, array('CQFW_Installer', 'activate') );
/**
 * Load the plugin after WordPress is ready.
 */
function cqfw_bootstrap_plugin() {
    $loader = new CQFW_Loader();
    $loader->register_hooks();
    // Run DB/schema migration when plugin version increases.
    $installed_version = get_option( 'cqfw_version', '0' );
    if ( version_compare( (string) $installed_version, CQFW_VERSION, '<' ) ) {
        CQFW_Installer::activate();
    }
}

add_action( 'plugins_loaded', 'cqfw_bootstrap_plugin' );
/**
 * Soft notice when WooCommerce is missing — core support chat still works.
 */
function cqfw_admin_notice_missing_woocommerce() {
    if ( !current_user_can( 'activate_plugins' ) || cqfw_woocommerce_active() ) {
        return;
    }
    // Only on Chat Quote screens (avoid nagging the whole admin).
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page = ( isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '' );
    if ( !$page || 0 !== strpos( $page, 'cqfw-' ) ) {
        return;
    }
    $install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' );
    echo '<div class="notice notice-info is-dismissible"><p>';
    echo esc_html__( 'Floating chat and support messaging work without WooCommerce. Install WooCommerce to enable product, shop, and cart WhatsApp buttons.', 'chat-quote-for-woocommerce' );
    echo ' <a href="' . esc_url( $install_url ) . '">' . esc_html__( 'Install WooCommerce', 'chat-quote-for-woocommerce' ) . '</a>';
    echo '</p></div>';
}

add_action( 'admin_notices', 'cqfw_admin_notice_missing_woocommerce' );