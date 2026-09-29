<?php
/**
 * Settings and admin pages.
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_Settings {

	const OPTION_NAME = 'cqfw_settings';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu_pages' ) );
		add_action( 'admin_menu', array( $this, 'reorder_admin_submenu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_admin_assets' ) );
		add_action( 'admin_post_cqfw_save_general', array( $this, 'handle_save_general' ) );
		add_action( 'admin_post_cqfw_save_widget', array( $this, 'handle_save_widget' ) );
		add_action( 'admin_post_cqfw_save_buttons', array( $this, 'handle_save_buttons' ) );
		add_action( 'admin_post_cqfw_save_pro', array( $this, 'handle_save_pro' ) );
	}

	/**
	 * Whether current admin screen is a Freemius SDK page (pricing / account / contact / …).
	 * App-shell CSS/JS must not load there — they break the Freemius React pricing UI (blank page).
	 *
	 * @param string $hook_suffix Optional admin hook.
	 * @return bool
	 */
	public static function is_freemius_sdk_page( $hook_suffix = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page detection.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page && preg_match( '/^cqfw-settings-(pricing|account|contact|affiliation|addons)$/', $page ) ) {
			return true;
		}
		if ( $hook_suffix && preg_match( '/cqfw-settings-(pricing|account|contact|affiliation|addons)/', $hook_suffix ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Load admin assets on Chat Quote screens only (never Freemius SDK pages).
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public function maybe_enqueue_admin_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'cqfw-' ) && false === strpos( $hook_suffix, 'cqfw_' ) ) {
			return;
		}

		if ( self::is_freemius_sdk_page( $hook_suffix ) ) {
			return;
		}

		$css_ver = file_exists( CQFW_PATH . 'assets/css/admin-settings.css' ) ? (string) filemtime( CQFW_PATH . 'assets/css/admin-settings.css' ) : CQFW_VERSION;
		$js_ver  = file_exists( CQFW_PATH . 'assets/js/admin-settings.js' ) ? (string) filemtime( CQFW_PATH . 'assets/js/admin-settings.js' ) : CQFW_VERSION;

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'cqfw-admin-settings-style',
			CQFW_URL . 'assets/css/admin-settings.css',
			array(),
			$css_ver
		);
		wp_enqueue_style(
			'cqfw-admin-styles-css',
			CQFW_URL . 'assets/css/admin-styles.css',
			array( 'wp-color-picker' ),
			file_exists( CQFW_PATH . 'assets/css/admin-styles.css' ) ? (string) filemtime( CQFW_PATH . 'assets/css/admin-styles.css' ) : CQFW_VERSION
		);

		// Same frontend CTA CSS so admin shop-style previews match the storefront.
		wp_enqueue_style(
			'cqfw-chat-widget-preview',
			CQFW_URL . 'assets/css/chat-widget.css',
			array( 'cqfw-admin-styles-css' ),
			file_exists( CQFW_PATH . 'assets/css/chat-widget.css' ) ? (string) filemtime( CQFW_PATH . 'assets/css/chat-widget.css' ) : CQFW_VERSION
		);

		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script(
			'cqfw-admin-settings',
			CQFW_URL . 'assets/js/admin-settings.js',
			array( 'wp-color-picker', 'jquery' ),
			$js_ver,
			true
		);
		wp_enqueue_script(
			'cqfw-admin-styles-js',
			CQFW_URL . 'assets/js/admin-styles.js',
			array( 'jquery', 'wp-color-picker', 'cqfw-admin-settings' ),
			file_exists( CQFW_PATH . 'assets/js/admin-styles.js' ) ? (string) filemtime( CQFW_PATH . 'assets/js/admin-styles.js' ) : CQFW_VERSION,
			true
		);

		$upgrade_url = function_exists( 'cqfw_fs' ) ? cqfw_fs()->get_upgrade_url() : 'https://wpchatquote.com/pro';

		wp_localize_script(
			'cqfw-admin-styles-js',
			'cqfwStylesConfig',
			array(
				'isPro'       => function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro(),
				'upgradeUrl'  => $upgrade_url,
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'cqfw_nonce' ),
				'i18n'        => array(
					'proRequired'      => __( 'Pro Feature', 'chat-quote-for-woocommerce' ),
					'proModalTitle'    => __( 'Unlock All 10+ Premium Chat Styles', 'chat-quote-for-woocommerce' ),
					'proModalDesc'     => __( 'This style is exclusively available in the Pro version of Chat Quote for WooCommerce. Upgrade now to unlock all 10+ modern widget & button styles, custom image uploads, agent working hours, live chat and much more!', 'chat-quote-for-woocommerce' ),
					'upgradeButton'    => __( 'Upgrade to PRO', 'chat-quote-for-woocommerce' ),
					'close'            => __( 'Close', 'chat-quote-for-woocommerce' ),
					'mediaTitle'       => __( 'Choose Custom Chat Button Image', 'chat-quote-for-woocommerce' ),
					'mediaButton'      => __( 'Use This Image', 'chat-quote-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_default_settings() {
		return array(
			'whatsapp_number'            => '',
			'whatsapp_fallback_number'   => '',
			'enable_product_button'      => 1,
			'enable_shop_button'         => 1,
			'enable_cart_button'         => 1,
			'enable_floating_button'     => 1,
			'enable_chat_widget'         => 1,
			'floating_button_text'       => __( 'Chat with us', 'chat-quote-for-woocommerce' ),
			'chat_popup_title'           => __( 'Chat with us', 'chat-quote-for-woocommerce' ),
			'require_phone_number'       => 0,
			'auto_redirect_whatsapp'     => 0,
			'button_position'            => 'after_add_to_cart',
			'button_background_color'    => '#0f766e',
			'button_text_color'          => '#ffffff',
			'button_border_color'        => '#0f766e',
			'button_hover_color'         => '#134e4a',
			'button_border_radius'       => '999px',
			'button_icon_url'            => '',
			'max_emails_per_hour'        => 100,
			'email_notify_enabled'       => 1,
			'email_notify_address'       => '',
			'floating_button_icon_url'   => '',
			'shop_button_icon_url'       => '',
			'widget_avatar_url'          => '',
			'button_icon_size'           => '18px',
			'shop_button_style'          => 'stack_outline',
			'shop_button_text'           => '',
			'shop_custom_layout'         => 'stack',
			'shop_custom_wa_variant'     => 'outline',
			'shop_custom_atc_bg'         => '#3b82f6',
			'shop_custom_wa_bg'          => '#ffffff',
			'shop_custom_wa_text'        => '#0f766e',
			'shop_custom_wa_border'      => '#0f766e',
			'shop_custom_radius'         => '999px',
			'shop_custom_label'          => '',
			'default_message_template'   => "Hello,\n\nI am interested in:\n\nProduct: {product_name}\nPrice: {product_price}\nQuantity: {product_qty}\n\nPlease provide a quote.",
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		return wp_parse_args( get_option( self::OPTION_NAME, array() ), self::get_default_settings() );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = '' ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Build inline CSS variables for button styling.
	 *
	 * @return string
	 */
	public static function get_button_style_attr() {
		$styles = array();

		if ( class_exists( 'CQFW_Chat_Styles' ) ) {
			$chat_styles = CQFW_Chat_Styles::get_saved_settings();
			if ( ! empty( $chat_styles['background_color'] ) ) {
				$styles[] = '--cqfw-button-bg:' . $chat_styles['background_color'];
			}
			if ( ! empty( $chat_styles['text_color'] ) ) {
				$styles[] = '--cqfw-button-text:' . $chat_styles['text_color'];
			}
			if ( ! empty( $chat_styles['icon_size'] ) ) {
				$styles[] = '--cqfw-button-icon-size:' . $chat_styles['icon_size'];
			}
		}

		return implode( ';', array_map( 'esc_attr', $styles ) );
	}

	/**
	 * Build inline CSS for WooCommerce buttons.
	 *
	 * @return string
	 */
	public static function get_button_inline_style() {
		$settings = self::get_settings();

		$background = ! empty( $settings['button_background_color'] ) ? $settings['button_background_color'] : '#0f766e';
		$text       = ! empty( $settings['button_text_color'] ) ? $settings['button_text_color'] : '#ffffff';
		$border     = ! empty( $settings['button_border_color'] ) ? $settings['button_border_color'] : $background;
		$radius     = ! empty( $settings['button_border_radius'] ) ? $settings['button_border_radius'] : '999px';

		return sprintf(
			'background-color:%1$s !important;color:%2$s !important;border:1px solid %3$s !important;border-radius:%4$s !important;',
			esc_attr( $background ),
			esc_attr( $text ),
			esc_attr( $border ),
			esc_attr( $radius )
		);
	}

	/**
	 * Get the button icon URL.
	 *
	 * @param string $context Button context.
	 * @return string
	 */
	public static function get_button_icon_url( $context = 'default' ) {
		$settings = self::get_settings();

		if ( 'floating' === $context && ! empty( $settings['floating_button_icon_url'] ) ) {
			return esc_url( $settings['floating_button_icon_url'] );
		}

		if ( 'shop' === $context && ! empty( $settings['shop_button_icon_url'] ) ) {
			return esc_url( $settings['shop_button_icon_url'] );
		}

		return ! empty( $settings['button_icon_url'] ) ? esc_url( $settings['button_icon_url'] ) : '';
	}

	/**
	 * Register settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'cqfw_settings_group',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_default_settings(),
			)
		);

		add_settings_section(
			'cqfw_general_section',
			__( 'General Settings', 'chat-quote-for-woocommerce' ),
			array( $this, 'render_general_section' ),
			'cqfw-settings'
		);

		add_settings_section(
			'cqfw_widget_section',
			__( 'Chat Widget', 'chat-quote-for-woocommerce' ),
			array( $this, 'render_widget_section' ),
			'cqfw-settings'
		);

		add_settings_section(
			'cqfw_buttons_section',
			__( 'WhatsApp Buttons', 'chat-quote-for-woocommerce' ),
			array( $this, 'render_buttons_section' ),
			'cqfw-settings'
		);

		add_settings_section(
			'cqfw_styles_section',
			__( 'Chat Styles', 'chat-quote-for-woocommerce' ),
			array( $this, 'render_styles_section' ),
			'cqfw-settings'
		);

		add_settings_section(
			'cqfw_about_section',
			__( 'About', 'chat-quote-for-woocommerce' ),
			array( $this, 'render_about_section' ),
			'cqfw-settings'
		);

		$this->register_field( 'whatsapp_number', 'cqfw_general_section', __( 'Your WhatsApp Number', 'chat-quote-for-woocommerce' ), array( $this, 'render_phone_field' ) );
		$this->register_field( 'whatsapp_fallback_number', 'cqfw_general_section', __( 'Backup WhatsApp Number', 'chat-quote-for-woocommerce' ), array( $this, 'render_phone_field' ) );
		$this->register_field( 'default_message_template', 'cqfw_general_section', __( 'Message customers send on WhatsApp', 'chat-quote-for-woocommerce' ), array( $this, 'render_template_field' ) );

		$this->register_field( 'enable_chat_widget', 'cqfw_widget_section', __( 'Show floating chat on website', 'chat-quote-for-woocommerce' ), array( $this, 'render_checkbox_field' ) );
		$this->register_field( 'enable_floating_button', 'cqfw_widget_section', __( 'Show the green chat bubble button', 'chat-quote-for-woocommerce' ), array( $this, 'render_checkbox_field' ) );
		$this->register_field( 'floating_button_text', 'cqfw_widget_section', __( 'Bubble button label', 'chat-quote-for-woocommerce' ), array( $this, 'render_text_field' ) );
		$this->register_field( 'chat_popup_title', 'cqfw_widget_section', __( 'Chat window title', 'chat-quote-for-woocommerce' ), array( $this, 'render_text_field' ) );
		$this->register_field( 'widget_avatar_url', 'cqfw_widget_section', __( 'Agent / store photo', 'chat-quote-for-woocommerce' ), array( $this, 'render_image_field' ) );
		$this->register_field( 'require_phone_number', 'cqfw_widget_section', __( 'Ask for phone number before chat', 'chat-quote-for-woocommerce' ), array( $this, 'render_checkbox_field' ) );
		$this->register_field( 'auto_redirect_whatsapp', 'cqfw_widget_section', __( 'Open WhatsApp automatically after message', 'chat-quote-for-woocommerce' ), array( $this, 'render_checkbox_field' ) );
		$this->register_field( 'email_notify_enabled', 'cqfw_widget_section', __( 'Email me when a customer chats', 'chat-quote-for-woocommerce' ), array( $this, 'render_checkbox_field' ) );
		$this->register_field( 'email_notify_address', 'cqfw_widget_section', __( 'Notification email (optional)', 'chat-quote-for-woocommerce' ), array( $this, 'render_notify_email_field' ) );
		$this->register_field( 'max_emails_per_hour', 'cqfw_widget_section', __( 'Max notification emails / hour', 'chat-quote-for-woocommerce' ), array( $this, 'render_email_limit_field' ) );

		$this->register_field( 'enable_product_button', 'cqfw_buttons_section', __( 'Show button on product page', 'chat-quote-for-woocommerce' ), array( $this, 'render_checkbox_field' ) );
		$this->register_field( 'enable_shop_button', 'cqfw_buttons_section', __( 'Show button on shop / category pages', 'chat-quote-for-woocommerce' ), array( $this, 'render_checkbox_field' ) );
		$this->register_field( 'enable_cart_button', 'cqfw_buttons_section', __( 'Show button on cart page', 'chat-quote-for-woocommerce' ), array( $this, 'render_checkbox_field' ) );
		$this->register_field( 'button_position', 'cqfw_buttons_section', __( 'Where to show Buy button on product page', 'chat-quote-for-woocommerce' ), array( $this, 'render_button_position_field' ) );

		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && cqfw_can_use_pro() ) {
			add_settings_section(
				'cqfw_pro_section',
				__( 'Pro Configuration', 'chat-quote-for-woocommerce' ),
				array( $this, 'render_pro_section' ),
				'cqfw-settings'
			);

			register_setting( 'cqfw_settings_group', 'cqfw_custom_form_fields', array( 'sanitize_callback' => array( $this, 'sanitize_json_to_array' ) ) );
			register_setting( 'cqfw_settings_group', 'cqfw_whatsapp_agents', array( 'sanitize_callback' => array( $this, 'sanitize_json_to_array' ) ) );
			register_setting( 'cqfw_settings_group', 'cqfw_business_hours_schedule', array( 'sanitize_callback' => array( $this, 'sanitize_json_to_array' ) ) );

			add_settings_field(
				'cqfw_custom_form_fields',
				__( 'Custom Form Fields', 'chat-quote-for-woocommerce' ),
				array( $this, 'render_pro_json_field' ),
				'cqfw-settings',
				'cqfw_pro_section',
				array( 
					'key' => 'cqfw_custom_form_fields',
					'desc' => ''
				)
			);

			add_settings_field(
				'cqfw_whatsapp_agents',
				__( 'WhatsApp Agents', 'chat-quote-for-woocommerce' ),
				array( $this, 'render_pro_json_field' ),
				'cqfw-settings',
				'cqfw_pro_section',
				array( 
					'key' => 'cqfw_whatsapp_agents',
					'desc' => ''
				)
			);

			add_settings_field(
				'cqfw_business_hours_schedule',
				__( 'Business Hours', 'chat-quote-for-woocommerce' ),
				array( $this, 'render_pro_json_field' ),
				'cqfw-settings',
				'cqfw_pro_section',
				array( 
					'key' => 'cqfw_business_hours_schedule',
					'desc' => ''
				)
			);
		}
	}

	/**
	 * Register a field.
	 *
	 * @param string   $key       Setting key.
	 * @param string   $section   Section id.
	 * @param string   $label     Field label.
	 * @param callable $callback  Render callback.
	 * @return void
	 */
	private function register_field( $key, $section, $label, $callback ) {
		add_settings_field(
			$key,
			$label,
			$callback,
			'cqfw-settings',
			$section,
			array(
				'label_for' => $key,
				'key'       => $key,
			)
		);
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	/**
	 * Full admin navigation map — clear names so store owners understand each page.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_admin_pages() {
		$wc_active = function_exists( 'cqfw_woocommerce_active' ) && cqfw_woocommerce_active();

		$pages = array(
			'cqfw-settings'  => array(
				'menu'  => __( '1. WhatsApp Number', 'chat-quote-for-woocommerce' ),
				'title' => __( 'WhatsApp Number', 'chat-quote-for-woocommerce' ),
				'nav'   => __( 'Number', 'chat-quote-for-woocommerce' ),
				'desc'  => __( 'Phone number that receives customer messages.', 'chat-quote-for-woocommerce' ),
				'icon'  => 'dashicons-phone',
				'group' => 'configure',
			),
			'cqfw-widget'    => array(
				'menu'  => __( '2. Chat Bubble', 'chat-quote-for-woocommerce' ),
				'title' => __( 'Chat Bubble', 'chat-quote-for-woocommerce' ),
				'nav'   => __( 'Chat Bubble', 'chat-quote-for-woocommerce' ),
				'desc'  => __( 'Settings and optional look for the floating chat.', 'chat-quote-for-woocommerce' ),
				'icon'  => 'dashicons-format-chat',
				'group' => 'configure',
			),
		);

		// Buy / Quote shop buttons only make sense with WooCommerce.
		if ( $wc_active ) {
			$pages['cqfw-buttons'] = array(
				'menu'  => __( '3. Buy Buttons', 'chat-quote-for-woocommerce' ),
				'title' => __( 'Buy Buttons', 'chat-quote-for-woocommerce' ),
				'nav'   => __( 'Buy Buttons', 'chat-quote-for-woocommerce' ),
				'desc'  => __( 'WhatsApp buttons on product, shop, and cart.', 'chat-quote-for-woocommerce' ),
				'icon'  => 'dashicons-cart',
				'group' => 'configure',
			);
		}

		$pages += array(
			'cqfw-messages'  => array(
				'menu'  => __( 'Inbox', 'chat-quote-for-woocommerce' ),
				'title' => __( 'Inbox', 'chat-quote-for-woocommerce' ),
				'nav'   => __( 'Inbox', 'chat-quote-for-woocommerce' ),
				'desc'  => __( 'Messages from the on-site chat bubble.', 'chat-quote-for-woocommerce' ),
				'icon'  => 'dashicons-email-alt',
				'group' => 'manage',
			),
			'cqfw-quotes'    => array(
				'menu'  => __( 'Quotes', 'chat-quote-for-woocommerce' ),
				'title' => __( 'Quotes', 'chat-quote-for-woocommerce' ),
				'nav'   => __( 'Quotes', 'chat-quote-for-woocommerce' ),
				'desc'  => __( 'Quote form requests from customers.', 'chat-quote-for-woocommerce' ),
				'icon'  => 'dashicons-media-text',
				'group' => 'manage',
			),
			'cqfw-analytics' => array(
				'menu'  => __( 'Reports', 'chat-quote-for-woocommerce' ),
				'title' => __( 'Reports', 'chat-quote-for-woocommerce' ),
				'nav'   => __( 'Reports', 'chat-quote-for-woocommerce' ),
				'desc'  => __( 'WhatsApp click counts and popular products.', 'chat-quote-for-woocommerce' ),
				'icon'  => 'dashicons-chart-area',
				'group' => 'manage',
			),
			'cqfw-about'     => array(
				'menu'  => __( 'How to use', 'chat-quote-for-woocommerce' ),
				'title' => __( 'How to use', 'chat-quote-for-woocommerce' ),
				'nav'   => __( 'How to use', 'chat-quote-for-woocommerce' ),
				'desc'  => __( 'Short guide — number, chat, and optional shop buttons.', 'chat-quote-for-woocommerce' ),
				'icon'  => 'dashicons-editor-help',
				'group' => 'more',
			),
		);

		// Business model: Free users see Go Pro; licensed users see Pro settings.
		if ( class_exists( 'CQFW_Upgrade' ) && CQFW_Upgrade::is_pro() ) {
			$pages['cqfw-pro'] = array(
				'menu'  => __( 'Extra Pro tools', 'chat-quote-for-woocommerce' ),
				'title' => __( 'Extra Pro tools', 'chat-quote-for-woocommerce' ),
				'nav'   => __( 'Pro tools', 'chat-quote-for-woocommerce' ),
				'desc'  => __( 'Optional: agents, hours, checkout WhatsApp.', 'chat-quote-for-woocommerce' ),
				'icon'  => 'dashicons-star-filled',
				'group' => 'more',
			);
		} else {
			$pages['cqfw-go-pro'] = array(
				'menu'  => __( 'Go Pro', 'chat-quote-for-woocommerce' ),
				'title' => __( 'Go Pro', 'chat-quote-for-woocommerce' ),
				'nav'   => __( 'Go Pro', 'chat-quote-for-woocommerce' ),
				'desc'  => __( 'Optional upgrade — Free keeps working.', 'chat-quote-for-woocommerce' ),
				'icon'  => 'dashicons-star-filled',
				'group' => 'more',
			);
		}

		return $pages;
	}

	/**
	 * Register admin menus with clear, user-friendly labels.
	 *
	 * @return void
	 */
	public function register_menu_pages() {
		$pages = self::get_admin_pages();

		add_menu_page(
			__( 'Chat Quote', 'chat-quote-for-woocommerce' ),
			__( 'Chat Quote', 'chat-quote-for-woocommerce' ),
			cqfw_get_admin_capability(),
			'cqfw-settings',
			array( $this, 'render_general_page' ),
			'dashicons-format-chat',
			56
		);

		// Sidebar order follows get_admin_pages() so owners see a clear flow.
		$menu_order = array( 'cqfw-settings', 'cqfw-widget', 'cqfw-buttons', 'cqfw-messages', 'cqfw-quotes', 'cqfw-analytics', 'cqfw-go-pro', 'cqfw-pro', 'cqfw-about' );
		$callbacks  = array(
			'cqfw-settings' => array( $this, 'render_general_page' ),
			'cqfw-widget'   => array( $this, 'render_widget_page' ),
			'cqfw-buttons'  => array( $this, 'render_buttons_page' ),
			'cqfw-about'    => array( $this, 'render_about_page' ),
			'cqfw-pro'      => array( $this, 'render_pro_page' ),
			'cqfw-go-pro'   => array( $this, 'render_go_pro_page' ),
		);

		foreach ( $menu_order as $slug ) {
			if ( empty( $pages[ $slug ] ) || empty( $callbacks[ $slug ] ) ) {
				continue;
			}
			add_submenu_page(
				'cqfw-settings',
				$pages[ $slug ]['title'],
				$pages[ $slug ]['menu'],
				cqfw_get_admin_capability(),
				$slug,
				$callbacks[ $slug ]
			);
		}

		if ( function_exists( 'cqfw_woocommerce_active' ) && cqfw_woocommerce_active() ) {
			add_submenu_page(
				'woocommerce',
				__( 'Chat Quote', 'chat-quote-for-woocommerce' ),
				__( 'Chat Quote', 'chat-quote-for-woocommerce' ),
				cqfw_get_admin_capability(),
				'cqfw-settings',
				array( $this, 'render_general_page' )
			);
		}
	}

	/**
	 * Keep Chat Quote sidebar items in a clear top-to-bottom flow.
	 *
	 * @return void
	 */
	public function reorder_admin_submenu() {
		global $submenu;
		if ( empty( $submenu['cqfw-settings'] ) || ! is_array( $submenu['cqfw-settings'] ) ) {
			return;
		}

		$desired = array(
			'cqfw-settings',
			'cqfw-widget',
			'cqfw-buttons',
			'cqfw-messages',
			'cqfw-quotes',
			'cqfw-analytics',
			'cqfw-go-pro',
			'cqfw-pro',
			'cqfw-about',
		);

		$by_slug = array();
		foreach ( $submenu['cqfw-settings'] as $item ) {
			if ( empty( $item[2] ) ) {
				continue;
			}
			$by_slug[ $item[2] ] = $item;
		}

		$ordered = array();
		foreach ( $desired as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$ordered[] = $by_slug[ $slug ];
				unset( $by_slug[ $slug ] );
			}
		}
		foreach ( $by_slug as $leftover ) {
			$ordered[] = $leftover;
		}

		$submenu['cqfw-settings'] = $ordered;
	}

	/**
	 * Setup checklist for average store owners (status + next link).
	 *
	 * @param string $current Current page slug.
	 * @return void
	 */
	/**
	 * Setup checklist — Help page only (setup pages use the Setup nav tabs).
	 *
	 * @param string $current Current page slug.
	 * @return void
	 */
	public static function render_easy_checklist( $current = 'cqfw-settings' ) {
		// Only show the full guide on Help to avoid duplicating Setup nav.
		if ( 'cqfw-about' !== $current ) {
			return;
		}

		$settings   = self::get_settings();
		$wc_active  = function_exists( 'cqfw_woocommerce_active' ) && cqfw_woocommerce_active();
		$has_number = ! empty( $settings['whatsapp_number'] ) || ! empty( $settings['whatsapp_fallback_number'] );
		$has_chat   = ! empty( $settings['enable_chat_widget'] ) && ! empty( $settings['enable_floating_button'] );
		$has_btn    = ! empty( $settings['enable_product_button'] ) || ! empty( $settings['enable_shop_button'] ) || ! empty( $settings['enable_cart_button'] );

		$steps = array(
			array(
				'id'    => 'cqfw-settings',
				'done'  => $has_number,
				'short' => __( 'Number', 'chat-quote-for-woocommerce' ),
				'title' => __( 'WhatsApp number', 'chat-quote-for-woocommerce' ),
				'help'  => __( 'Pick country + local digits', 'chat-quote-for-woocommerce' ),
				'url'   => admin_url( 'admin.php?page=cqfw-settings' ),
			),
			array(
				'id'    => 'cqfw-widget',
				'done'  => $has_chat,
				'short' => __( 'Chat', 'chat-quote-for-woocommerce' ),
				'title' => __( 'Chat bubble', 'chat-quote-for-woocommerce' ),
				'help'  => __( 'Floating chat on site', 'chat-quote-for-woocommerce' ),
				'url'   => admin_url( 'admin.php?page=cqfw-widget' ),
			),
		);

		if ( $wc_active ) {
			$steps[] = array(
				'id'    => 'cqfw-buttons',
				'done'  => $has_btn,
				'short' => __( 'Buttons', 'chat-quote-for-woocommerce' ),
				'title' => __( 'Buy buttons', 'chat-quote-for-woocommerce' ),
				'help'  => __( 'Product / shop / cart', 'chat-quote-for-woocommerce' ),
				'url'   => admin_url( 'admin.php?page=cqfw-buttons' ),
			);
		}

		$step_total = count( $steps );
		$done_count = 0;
		foreach ( $steps as $s ) {
			if ( ! empty( $s['done'] ) ) {
				$done_count++;
			}
		}

		$next = null;
		foreach ( $steps as $step ) {
			if ( empty( $step['done'] ) ) {
				$next = $step;
				break;
			}
		}
		?>
		<div class="cqfw-easy-guide is-full" role="region" aria-label="<?php esc_attr_e( 'Setup progress', 'chat-quote-for-woocommerce' ); ?>" data-cqfw-progress="<?php echo esc_attr( (string) $done_count ); ?>" data-cqfw-total="<?php echo esc_attr( (string) $step_total ); ?>">
			<div class="cqfw-easy-guide__head">
				<strong><?php esc_html_e( 'Setup checklist', 'chat-quote-for-woocommerce' ); ?></strong>
				<span class="cqfw-easy-guide__progress"><?php echo esc_html( (string) $done_count . '/' . (string) $step_total ); ?></span>
				<span class="cqfw-easy-guide__hint">
					<?php
					echo esc_html(
						$wc_active
							? __( 'Do these 3 steps in order.', 'chat-quote-for-woocommerce' )
							: __( 'Do these 2 steps — support chat works without WooCommerce.', 'chat-quote-for-woocommerce' )
					);
					?>
				</span>
			</div>
			<div class="cqfw-easy-guide__bar" aria-hidden="true">
				<span class="cqfw-easy-guide__bar-fill" style="--cqfw-progress: <?php echo esc_attr( (string) ( $step_total ? round( ( $done_count / $step_total ) * 100 ) : 0 ) ); ?>%;"></span>
			</div>
			<ol class="cqfw-easy-guide__list">
				<?php foreach ( $steps as $i => $step ) : ?>
					<?php
					$cls = 'cqfw-easy-guide__item';
					if ( ! empty( $step['done'] ) ) {
						$cls .= ' is-done';
					} elseif ( $next && $next['id'] === $step['id'] ) {
						$cls .= ' is-current';
					}
					?>
					<li class="<?php echo esc_attr( $cls ); ?>" style="--cqfw-step-i: <?php echo esc_attr( (string) $i ); ?>;">
						<a class="cqfw-easy-guide__pill" href="<?php echo esc_url( $step['url'] ); ?>">
							<span class="cqfw-easy-guide__num" aria-hidden="true"><?php echo ! empty( $step['done'] ) ? esc_html( '✓' ) : esc_html( (string) ( $i + 1 ) ); ?></span>
							<span class="cqfw-easy-guide__label"><?php echo esc_html( $step['title'] ); ?></span>
						</a>
						<small class="cqfw-easy-guide__help"><?php echo esc_html( $step['help'] ); ?></small>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php if ( $next ) : ?>
				<a class="cqfw-easy-guide__next-link" href="<?php echo esc_url( $next['url'] ); ?>">
					<?php
					/* translators: %s: next step short name */
					echo esc_html( sprintf( __( 'Next: %s →', 'chat-quote-for-woocommerce' ), $next['short'] ) );
					?>
				</a>
			<?php elseif ( $done_count === $step_total ) : ?>
				<span class="cqfw-easy-guide__ready"><?php esc_html_e( 'Ready — test on your site', 'chat-quote-for-woocommerce' ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Next-step footer after save on setup pages.
	 *
	 * @param string $next_slug Next page slug.
	 * @param string $label     Button label.
	 * @return void
	 */
	public static function render_next_step( $next_slug, $label ) {
		?>
		<div class="cqfw-next-step">
			<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $next_slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
		</div>
		<?php
	}

	/**
	 * Shared page header + full plugin navigation (app-shell UX).
	 *
	 * @param string $active_page Current page slug.
	 * @return void
	 */
	public static function render_page_header( $active_page = '' ) {
		$pages  = self::get_admin_pages();
		$active = isset( $pages[ $active_page ] ) ? $pages[ $active_page ] : null;

		$groups = array(
			'configure' => __( 'Setup', 'chat-quote-for-woocommerce' ),
			'manage'    => __( 'Messages', 'chat-quote-for-woocommerce' ),
			'more'      => __( 'More', 'chat-quote-for-woocommerce' ),
		);
		?>
		<div class="cqfw-app wrap">
			<?php /* First heading in .wrap — WP relocates admin notices after this (not into the teal brand header). */ ?>
			<h1 class="cqfw-app__screen-title screen-reader-text"><?php esc_html_e( 'Chat Quote', 'chat-quote-for-woocommerce' ); ?></h1>
			<div class="cqfw-admin-notices" id="cqfw-admin-notices" aria-live="polite"></div>

			<div class="cqfw-app__shell">
				<header class="cqfw-app__top">
					<div class="cqfw-app__brand">
						<span class="cqfw-app__logo" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg>
						</span>
						<div class="cqfw-app__brand-text">
							<p class="cqfw-app__title"><?php esc_html_e( 'Chat Quote', 'chat-quote-for-woocommerce' ); ?></p>
							<span><?php echo $active ? esc_html( $active['title'] ) : esc_html__( 'WhatsApp chat & support for your site', 'chat-quote-for-woocommerce' ); ?></span>
						</div>
					</div>
				</header>

				<nav class="cqfw-app__nav" aria-label="<?php esc_attr_e( 'Chat Quote pages', 'chat-quote-for-woocommerce' ); ?>">
					<?php foreach ( $groups as $group_key => $group_label ) : ?>
						<?php
						$group_pages = array_filter(
							$pages,
							static function ( $p ) use ( $group_key ) {
								return isset( $p['group'] ) && $p['group'] === $group_key;
							}
						);
						if ( empty( $group_pages ) ) {
							continue;
						}
						?>
						<div class="cqfw-app__nav-group" data-group="<?php echo esc_attr( $group_key ); ?>">
							<span class="cqfw-app__nav-label"><?php echo esc_html( $group_label ); ?></span>
							<div class="cqfw-app__nav-pills" role="list">
								<?php foreach ( $group_pages as $slug => $page ) : ?>
									<a role="listitem"
									   href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"
									   class="cqfw-app__nav-pill <?php echo $active_page === $slug ? 'is-active' : ''; ?>"
									   title="<?php echo esc_attr( $page['desc'] ); ?>">
										<span class="dashicons <?php echo esc_attr( $page['icon'] ); ?>"></span>
										<span><?php echo esc_html( $page['nav'] ); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</nav>

				<main class="cqfw-app__main">
		<?php
	}

	/**
	 * Close app shell opened by render_page_header().
	 *
	 * @return void
	 */
	public static function render_page_footer() {
		echo '</main></div></div>';
	}

	/**
	 * Render General Settings page.
	 */
	public function render_general_page() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) { return; }
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$updated = isset( $_GET['updated'] ) && '1' === $_GET['updated'];
		self::render_page_header( 'cqfw-settings' );
		if ( $updated ) {
			echo '<div class="cqfw-toast is-success" data-cqfw-toast="1" role="status"><p>' . esc_html__( 'Saved. Continue to Chat Bubble when ready.', 'chat-quote-for-woocommerce' ) . '</p></div>';
		}
		?>
		<section class="cqfw-panel">
			<header class="cqfw-panel__head">
				<h2><?php esc_html_e( 'Your WhatsApp number', 'chat-quote-for-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'Customers message this number when they tap Buy / Chat. Example: 01732593040', 'chat-quote-for-woocommerce' ); ?></p>
			</header>
			<form class="cqfw-panel__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cqfw_save_general_action', 'cqfw_general_nonce' ); ?>
				<input type="hidden" name="action" value="cqfw_save_general" />
				<div class="cqfw-fields">
					<table class="form-table" role="presentation"><tbody>
					<?php
					global $wp_settings_fields;
					if ( isset( $wp_settings_fields['cqfw-settings']['cqfw_general_section'] ) ) {
						do_settings_fields( 'cqfw-settings', 'cqfw_general_section' );
					}
					?>
					</tbody></table>
				</div>
				<footer class="cqfw-panel__foot cqfw-panel__foot--sticky">
					<button type="submit" class="button button-primary cqfw-btn-save"><?php esc_html_e( 'Save & continue to Chat Bubble', 'chat-quote-for-woocommerce' ); ?></button>
				</footer>
			</form>
		</section>
		<?php
		self::render_page_footer();
	}

	/**
	 * Live preview stage for Chat Bubble admin (CQFW-owned markup — not third-party copy).
	 *
	 * @return void
	 */
	public static function render_live_preview_stage() {
		$settings   = self::get_settings();
		$styles     = class_exists( 'CQFW_Chat_Styles' ) ? CQFW_Chat_Styles::get_saved_settings() : array();
		$title      = ! empty( $settings['chat_popup_title'] ) ? $settings['chat_popup_title'] : __( 'Chat with us', 'chat-quote-for-woocommerce' );
		$label      = ! empty( $settings['floating_button_text'] ) ? $settings['floating_button_text'] : __( 'Chat with us', 'chat-quote-for-woocommerce' );
		$h_side     = ( isset( $styles['pos_h_side'] ) && 'left' === $styles['pos_h_side'] ) ? 'left' : 'right';
		$v_side     = ( isset( $styles['pos_v_side'] ) && 'top' === $styles['pos_v_side'] ) ? 'top' : 'bottom';
		$enabled    = ! empty( $settings['enable_floating_button'] ) && ! empty( $settings['enable_chat_widget'] );
		$style_id   = ! empty( $styles['active_style'] ) ? $styles['active_style'] : 'style_1';
		$style_slug = 'cqfw-lp--' . sanitize_html_class( str_replace( '_', '-', $style_id ) );
		$icon_only  = in_array( $style_id, array( 'style_2', 'style_3', 'style_3_extend', 'style_7', 'style_7_extend' ), true );
		$show_label = in_array( $style_id, array( 'style_1', 'style_4', 'style_8' ), true );
		$bg         = ! empty( $styles['background_color'] ) ? $styles['background_color'] : ( 'style_1' === $style_id ? '#ffffff' : '#25d366' );
		$text       = ! empty( $styles['text_color'] ) ? $styles['text_color'] : ( 'style_1' === $style_id ? '#1e293b' : '#ffffff' );
		$icon_c     = ! empty( $styles['icon_color'] ) ? $styles['icon_color'] : ( 'style_1' === $style_id ? '#25d366' : '#ffffff' );
		?>
		<aside class="cqfw-live-preview" id="cqfw-live-preview" aria-label="<?php esc_attr_e( 'Live chat preview', 'chat-quote-for-woocommerce' ); ?>">
			<header class="cqfw-live-preview__head">
				<div>
					<strong><?php esc_html_e( 'Live preview', 'chat-quote-for-woocommerce' ); ?></strong>
					<span><?php esc_html_e( 'Updates as you edit — Save to publish.', 'chat-quote-for-woocommerce' ); ?></span>
				</div>
				<div class="cqfw-live-preview__devices" role="group" aria-label="<?php esc_attr_e( 'Preview device', 'chat-quote-for-woocommerce' ); ?>">
					<button type="button" class="cqfw-live-preview__device is-active" data-cqfw-device="desktop"><?php esc_html_e( 'Desktop', 'chat-quote-for-woocommerce' ); ?></button>
					<button type="button" class="cqfw-live-preview__device" data-cqfw-device="mobile"><?php esc_html_e( 'Mobile', 'chat-quote-for-woocommerce' ); ?></button>
				</div>
			</header>
			<div class="cqfw-live-preview__stage is-desktop" data-cqfw-stage>
				<div class="cqfw-live-preview__chrome" aria-hidden="true">
					<span></span><span></span><span></span>
				</div>
				<div
					class="cqfw-live-preview__frame <?php echo $enabled ? '' : 'is-off'; ?>"
					data-cqfw-frame
					data-h="<?php echo esc_attr( $h_side ); ?>"
					data-v="<?php echo esc_attr( $v_side ); ?>"
					data-style="<?php echo esc_attr( $style_id ); ?>"
					style="--cqfw-lp-bg: <?php echo esc_attr( $bg ); ?>; --cqfw-lp-fg: <?php echo esc_attr( $text ); ?>; --cqfw-lp-icon: <?php echo esc_attr( $icon_c ); ?>;"
				>
					<div class="cqfw-lp-panel" data-cqfw-lp-panel aria-hidden="true">
						<div class="cqfw-lp-panel__head">
							<span class="cqfw-lp-panel__avatar" aria-hidden="true">WA</span>
							<div>
								<strong data-cqfw-lp-title><?php echo esc_html( $title ); ?></strong>
								<small><?php esc_html_e( 'Typically replies instantly', 'chat-quote-for-woocommerce' ); ?></small>
							</div>
						</div>
						<div class="cqfw-lp-panel__body">
							<div class="cqfw-lp-bubble"><?php esc_html_e( 'Hi! How can we help you today?', 'chat-quote-for-woocommerce' ); ?></div>
						</div>
						<div class="cqfw-lp-panel__foot">
							<span><?php esc_html_e( 'Type a message…', 'chat-quote-for-woocommerce' ); ?></span>
						</div>
					</div>
					<button type="button" class="cqfw-lp-fab <?php echo esc_attr( $style_slug ); ?><?php echo $icon_only ? ' is-icon-only' : ''; ?>" data-cqfw-lp-fab aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle preview chat', 'chat-quote-for-woocommerce' ); ?>">
						<svg class="cqfw-lp-fab__icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg>
						<span class="cqfw-lp-fab__label" data-cqfw-lp-label <?php echo $show_label ? '' : 'hidden'; ?>><?php echo esc_html( $label ); ?></span>
						<span class="cqfw-lp-fab__badge" data-cqfw-lp-badge <?php echo ( 'style_3_extend' === $style_id ) ? '' : 'hidden'; ?>>1</span>
					</button>
					<p class="cqfw-live-preview__off" data-cqfw-lp-off <?php echo $enabled ? 'hidden' : ''; ?>><?php esc_html_e( 'Chat bubble is turned off in settings.', 'chat-quote-for-woocommerce' ); ?></p>
				</div>
			</div>
		</aside>
		<?php
	}

	/**
	 * Render Chat Widget page (Settings + Look tabs).
	 */
	public function render_widget_page() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) { return; }
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$updated = isset( $_GET['updated'] ) && '1' === $_GET['updated'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab     = ( isset( $_GET['tab'] ) && 'look' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ) ? 'look' : 'settings';

		self::render_page_header( 'cqfw-widget' );
		if ( $updated ) {
			if ( 'look' === $tab ) {
				echo '<div class="cqfw-toast is-success" data-cqfw-toast="1" role="status"><p>' . esc_html__( 'Look & style saved.', 'chat-quote-for-woocommerce' ) . '</p></div>';
			} elseif ( function_exists( 'cqfw_woocommerce_active' ) && cqfw_woocommerce_active() ) {
				echo '<div class="cqfw-toast is-success" data-cqfw-toast="1" role="status"><p>' . esc_html__( 'Chat settings saved. You can customize Look next, or continue to Buy Buttons.', 'chat-quote-for-woocommerce' ) . '</p></div>';
			} else {
				echo '<div class="cqfw-toast is-success" data-cqfw-toast="1" role="status"><p>' . esc_html__( 'Chat settings saved. Floating support chat is ready — test on your site.', 'chat-quote-for-woocommerce' ) . '</p></div>';
			}
		}

		$settings_url = admin_url( 'admin.php?page=cqfw-widget' );
		$look_url     = admin_url( 'admin.php?page=cqfw-widget&tab=look' );
		$wc_active    = function_exists( 'cqfw_woocommerce_active' ) && cqfw_woocommerce_active();
		?>
		<nav class="cqfw-subtabs" aria-label="<?php esc_attr_e( 'Chat Bubble sections', 'chat-quote-for-woocommerce' ); ?>">
			<a class="cqfw-subtab <?php echo 'settings' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $settings_url ); ?>">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Settings', 'chat-quote-for-woocommerce' ); ?>
			</a>
			<a class="cqfw-subtab <?php echo 'look' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $look_url ); ?>">
				<span class="dashicons dashicons-admin-appearance"></span>
				<?php esc_html_e( 'Look & Style', 'chat-quote-for-woocommerce' ); ?>
				<span class="cqfw-subtab__hint"><?php esc_html_e( 'optional', 'chat-quote-for-woocommerce' ); ?></span>
			</a>
		</nav>

		<div class="cqfw-widget-workspace">
			<div class="cqfw-widget-workspace__main">
		<?php if ( 'look' === $tab ) : ?>
		<section class="cqfw-panel">
			<header class="cqfw-panel__head">
				<h2><?php esc_html_e( 'Look & Style', 'chat-quote-for-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'Optional — pick a bubble style, popup look, colors, and corner position.', 'chat-quote-for-woocommerce' ); ?></p>
			</header>
			<form class="cqfw-panel__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cqfw_save_chat_styles_action', 'cqfw_styles_nonce' ); ?>
				<input type="hidden" name="action" value="cqfw_save_chat_styles" />
				<div class="cqfw-panel__body">
					<?php
					if ( class_exists( 'CQFW_Chat_Styles' ) ) {
						CQFW_Chat_Styles::render_styles_tab_content();
					}
					?>
				</div>
				<footer class="cqfw-panel__foot cqfw-panel__foot--sticky">
					<button type="submit" class="button button-primary cqfw-btn-save"><?php esc_html_e( 'Save Look & Style', 'chat-quote-for-woocommerce' ); ?></button>
					<?php if ( $wc_active ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=cqfw-buttons' ) ); ?>"><?php esc_html_e( 'Continue to Buy Buttons →', 'chat-quote-for-woocommerce' ); ?></a>
					<?php else : ?>
						<a class="button button-secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview site chat →', 'chat-quote-for-woocommerce' ); ?></a>
					<?php endif; ?>
				</footer>
			</form>
		</section>
		<?php else : ?>
		<section class="cqfw-panel">
			<header class="cqfw-panel__head">
				<h2><?php esc_html_e( 'Floating chat bubble', 'chat-quote-for-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'Turn the green corner chat on, set title and greetings. Style it under Look & Style.', 'chat-quote-for-woocommerce' ); ?></p>
			</header>
			<form class="cqfw-panel__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cqfw_save_widget_action', 'cqfw_widget_nonce' ); ?>
				<input type="hidden" name="action" value="cqfw_save_widget" />
				<div class="cqfw-fields">
					<table class="form-table" role="presentation"><tbody>
					<?php
					global $wp_settings_fields;
					if ( isset( $wp_settings_fields['cqfw-settings']['cqfw_widget_section'] ) ) {
						do_settings_fields( 'cqfw-settings', 'cqfw_widget_section' );
					}
					?>
					</tbody></table>
				</div>
				<?php
				if ( class_exists( 'CQFW_Widget_Controls' ) ) {
					CQFW_Widget_Controls::render_admin_panels( 'global' );
				}
				?>
				<footer class="cqfw-panel__foot cqfw-panel__foot--sticky">
					<button type="submit" class="button button-primary cqfw-btn-save"><?php esc_html_e( 'Save settings', 'chat-quote-for-woocommerce' ); ?></button>
					<a class="button button-secondary" href="<?php echo esc_url( $look_url ); ?>"><?php esc_html_e( 'Customize Look →', 'chat-quote-for-woocommerce' ); ?></a>
					<?php if ( $wc_active ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=cqfw-buttons' ) ); ?>"><?php esc_html_e( 'Skip to Buy Buttons →', 'chat-quote-for-woocommerce' ); ?></a>
					<?php endif; ?>
				</footer>
			</form>
		</section>
		<?php endif; ?>
			</div>
			<?php self::render_live_preview_stage(); ?>
		</div>
		<?php
		self::render_page_footer();
	}

	/**
	 * Render WhatsApp Buttons page.
	 */
	public function render_buttons_page() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) { return; }
		if ( ! function_exists( 'cqfw_woocommerce_active' ) || ! cqfw_woocommerce_active() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=cqfw-widget' ) );
			exit;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$updated = isset( $_GET['updated'] ) && '1' === $_GET['updated'];
		self::render_page_header( 'cqfw-buttons' );
		if ( $updated ) {
			echo '<div class="cqfw-toast is-success" data-cqfw-toast="1" role="status"><p>' . esc_html__( 'Saved. Setup is ready — test on your shop.', 'chat-quote-for-woocommerce' ) . '</p></div>';
		}
		?>
		<section class="cqfw-panel">
			<header class="cqfw-panel__head">
				<h2><?php esc_html_e( 'Buy / Quote buttons', 'chat-quote-for-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'Show WhatsApp on product, shop, and cart so shoppers can message you with item details.', 'chat-quote-for-woocommerce' ); ?></p>
			</header>
			<form class="cqfw-panel__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cqfw_save_buttons_action', 'cqfw_buttons_nonce' ); ?>
				<input type="hidden" name="action" value="cqfw_save_buttons" />
				<div class="cqfw-fields">
					<table class="form-table" role="presentation"><tbody>
					<?php
					global $wp_settings_fields;
					if ( isset( $wp_settings_fields['cqfw-settings']['cqfw_buttons_section'] ) ) {
						do_settings_fields( 'cqfw-settings', 'cqfw_buttons_section' );
					}
					?>
					</tbody></table>
				</div>
				<?php
				if ( class_exists( 'CQFW_Shop_Button_Styles' ) ) {
					CQFW_Shop_Button_Styles::render_admin_picker();
				}
				?>
				<footer class="cqfw-panel__foot">
					<button type="submit" class="button button-primary cqfw-btn-save"><?php esc_html_e( 'Save buttons', 'chat-quote-for-woocommerce' ); ?></button>
				</footer>
			</form>
		</section>
		<?php
		self::render_page_footer();
	}

	/**
	 * Handle save for General Settings.
	 */
	public function handle_save_general() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( 'cqfw_save_general_action', 'cqfw_general_nonce' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$input    = isset( $_POST[ self::OPTION_NAME ] ) ? wp_unslash( $_POST[ self::OPTION_NAME ] ) : array();
		$current  = self::get_settings();
		$defaults = self::get_default_settings();

		$current['whatsapp_number']          = isset( $input['whatsapp_number'] ) ? preg_replace( '/\D+/', '', (string) $input['whatsapp_number'] ) : '';
		$current['whatsapp_fallback_number'] = isset( $input['whatsapp_fallback_number'] ) ? preg_replace( '/\D+/', '', (string) $input['whatsapp_fallback_number'] ) : '';
		$current['default_message_template'] = isset( $input['default_message_template'] ) ? sanitize_textarea_field( $input['default_message_template'] ) : $defaults['default_message_template'];

		update_option( self::OPTION_NAME, $current );
		wp_safe_redirect( add_query_arg( array( 'page' => 'cqfw-widget', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle save for Chat Widget Settings.
	 */
	public function handle_save_widget() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( 'cqfw_save_widget_action', 'cqfw_widget_nonce' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$input   = isset( $_POST[ self::OPTION_NAME ] ) ? wp_unslash( $_POST[ self::OPTION_NAME ] ) : array();
		$current = self::get_settings();
		$defaults = self::get_default_settings();

		$current['enable_chat_widget']     = ! empty( $input['enable_chat_widget'] ) ? 1 : 0;
		$current['enable_floating_button'] = ! empty( $input['enable_floating_button'] ) ? 1 : 0;
		$current['floating_button_text']   = isset( $input['floating_button_text'] ) ? sanitize_text_field( $input['floating_button_text'] ) : $defaults['floating_button_text'];
		$current['require_phone_number']   = ! empty( $input['require_phone_number'] ) ? 1 : 0;
		$current['auto_redirect_whatsapp'] = ! empty( $input['auto_redirect_whatsapp'] ) ? 1 : 0;
		$current['email_notify_enabled']   = ! empty( $input['email_notify_enabled'] ) ? 1 : 0;
		$current['email_notify_address']   = isset( $input['email_notify_address'] ) ? sanitize_email( $input['email_notify_address'] ) : '';
		$limit = isset( $input['max_emails_per_hour'] ) ? absint( $input['max_emails_per_hour'] ) : $defaults['max_emails_per_hour'];
		$current['max_emails_per_hour']    = min( 500, max( 0, $limit ) );
		$current['chat_popup_title']       = isset( $input['chat_popup_title'] ) ? sanitize_text_field( $input['chat_popup_title'] ) : $defaults['chat_popup_title'];
		$current['widget_avatar_url']      = isset( $input['widget_avatar_url'] ) ? esc_url_raw( $input['widget_avatar_url'] ) : '';

		update_option( self::OPTION_NAME, $current );

		if ( isset( $_POST['cqfw_widget_controls'] ) && class_exists( 'CQFW_Widget_Controls' ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw = wp_unslash( $_POST['cqfw_widget_controls'] );
			if ( ! is_array( $raw ) ) {
				$raw = array();
			}
			$allow_pro = ! empty( $_POST['cqfw_controls_include_pro'] )
				&& function_exists( 'cqfw_fs' )
				&& cqfw_fs()->is__premium_only()
				&& CQFW_Widget_Controls::can_use_pro_controls();

			// Only normalize checkboxes that belong to this form (avoid wiping the other scope).
			$bool_keys = $allow_pro
				? CQFW_Widget_Controls::pro_checkbox_keys()
				: CQFW_Widget_Controls::free_checkbox_keys();
			foreach ( $bool_keys as $bk ) {
				if ( ! isset( $raw[ $bk ] ) ) {
					$raw[ $bk ] = 0;
				}
			}

			$controls = CQFW_Widget_Controls::sanitize( $raw, $allow_pro );
			update_option( CQFW_Widget_Controls::OPTION, $controls );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'cqfw-widget', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle save for WhatsApp Buttons Settings.
	 */
	public function handle_save_buttons() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( 'cqfw_save_buttons_action', 'cqfw_buttons_nonce' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$input   = isset( $_POST[ self::OPTION_NAME ] ) ? wp_unslash( $_POST[ self::OPTION_NAME ] ) : array();
		$current = self::get_settings();
		$defaults = self::get_default_settings();

		$current['enable_product_button']  = ! empty( $input['enable_product_button'] ) ? 1 : 0;
		$current['enable_shop_button']     = ! empty( $input['enable_shop_button'] ) ? 1 : 0;
		$current['enable_cart_button']     = ! empty( $input['enable_cart_button'] ) ? 1 : 0;
		$allowed = array( 'before_add_to_cart', 'after_add_to_cart', 'product_meta' );
		$pos     = isset( $input['button_position'] ) ? sanitize_text_field( $input['button_position'] ) : $defaults['button_position'];
		$current['button_position'] = in_array( $pos, $allowed, true ) ? $pos : $defaults['button_position'];
		$current['button_background_color']= isset( $input['button_background_color'] ) ? sanitize_hex_color( $input['button_background_color'] ) : $defaults['button_background_color'];
		$current['button_text_color']      = isset( $input['button_text_color'] ) ? sanitize_hex_color( $input['button_text_color'] ) : $defaults['button_text_color'];
		$current['button_border_color']    = isset( $input['button_border_color'] ) ? sanitize_hex_color( $input['button_border_color'] ) : $defaults['button_border_color'];
		$current['button_hover_color']     = isset( $input['button_hover_color'] ) ? sanitize_hex_color( $input['button_hover_color'] ) : $defaults['button_hover_color'];
		$current['button_border_radius']   = isset( $input['button_border_radius'] ) ? sanitize_text_field( $input['button_border_radius'] ) : $defaults['button_border_radius'];
		$current['button_icon_url']        = isset( $input['button_icon_url'] ) ? esc_url_raw( $input['button_icon_url'] ) : '';
		$current['floating_button_icon_url']= isset( $input['floating_button_icon_url'] ) ? esc_url_raw( $input['floating_button_icon_url'] ) : '';
		$current['shop_button_icon_url']   = isset( $input['shop_button_icon_url'] ) ? esc_url_raw( $input['shop_button_icon_url'] ) : '';
		$current['button_icon_size']       = isset( $input['button_icon_size'] ) ? sanitize_text_field( $input['button_icon_size'] ) : $defaults['button_icon_size'];

		$allowed_styles = array( 'stack_outline', 'stack_outline_blue' );
		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro() ) {
			$allowed_styles[] = 'side_by_side';
			$allowed_styles[] = 'custom';
		}
		$style_id = isset( $input['shop_button_style'] ) ? sanitize_key( $input['shop_button_style'] ) : $defaults['shop_button_style'];
		$current['shop_button_style'] = in_array( $style_id, $allowed_styles, true ) ? $style_id : $defaults['shop_button_style'];
		$current['shop_button_text']  = isset( $input['shop_button_text'] ) ? sanitize_text_field( $input['shop_button_text'] ) : '';

		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro() ) {
			$layout = isset( $input['shop_custom_layout'] ) ? sanitize_key( $input['shop_custom_layout'] ) : 'stack';
			$current['shop_custom_layout'] = in_array( $layout, array( 'stack', 'row' ), true ) ? $layout : 'stack';
			$variant = isset( $input['shop_custom_wa_variant'] ) ? sanitize_key( $input['shop_custom_wa_variant'] ) : 'outline';
			$current['shop_custom_wa_variant'] = in_array( $variant, array( 'solid', 'outline' ), true ) ? $variant : 'outline';
			$current['shop_custom_atc_bg']     = isset( $input['shop_custom_atc_bg'] ) ? sanitize_hex_color( $input['shop_custom_atc_bg'] ) : $defaults['shop_custom_atc_bg'];
			$current['shop_custom_wa_bg']      = isset( $input['shop_custom_wa_bg'] ) ? sanitize_hex_color( $input['shop_custom_wa_bg'] ) : $defaults['shop_custom_wa_bg'];
			$current['shop_custom_wa_text']    = isset( $input['shop_custom_wa_text'] ) ? sanitize_hex_color( $input['shop_custom_wa_text'] ) : $defaults['shop_custom_wa_text'];
			$current['shop_custom_wa_border']  = isset( $input['shop_custom_wa_border'] ) ? sanitize_hex_color( $input['shop_custom_wa_border'] ) : $defaults['shop_custom_wa_border'];
			$current['shop_custom_radius']     = isset( $input['shop_custom_radius'] ) ? sanitize_text_field( $input['shop_custom_radius'] ) : $defaults['shop_custom_radius'];
			$current['shop_custom_label']      = isset( $input['shop_custom_label'] ) ? sanitize_text_field( $input['shop_custom_label'] ) : '';
		}

		update_option( self::OPTION_NAME, $current );
		wp_safe_redirect( add_query_arg( array( 'page' => 'cqfw-buttons', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * OLD render_settings_page kept for backward compat - redirects to general.
	 */
	public function render_settings_page() {
		$this->render_general_page();
	}

	/*** Sanitize settings.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( $input ) {
		$defaults = self::get_default_settings();
		$sanitized = $defaults;
		$input     = is_array( $input ) ? wp_unslash( $input ) : array();

		$sanitized['whatsapp_number']          = isset( $input['whatsapp_number'] ) ? preg_replace( '/\D+/', '', (string) $input['whatsapp_number'] ) : '';
		$sanitized['whatsapp_fallback_number'] = isset( $input['whatsapp_fallback_number'] ) ? preg_replace( '/\D+/', '', (string) $input['whatsapp_fallback_number'] ) : '';
		$sanitized['floating_button_text']     = isset( $input['floating_button_text'] ) ? sanitize_text_field( $input['floating_button_text'] ) : $defaults['floating_button_text'];
		$sanitized['chat_popup_title']         = isset( $input['chat_popup_title'] ) ? sanitize_text_field( $input['chat_popup_title'] ) : $defaults['chat_popup_title'];
		$sanitized['default_message_template'] = isset( $input['default_message_template'] ) ? sanitize_textarea_field( $input['default_message_template'] ) : $defaults['default_message_template'];
		$sanitized['button_background_color']   = isset( $input['button_background_color'] ) ? sanitize_hex_color( $input['button_background_color'] ) : $defaults['button_background_color'];
		$sanitized['button_text_color']         = isset( $input['button_text_color'] ) ? sanitize_hex_color( $input['button_text_color'] ) : $defaults['button_text_color'];
		$sanitized['button_border_color']       = isset( $input['button_border_color'] ) ? sanitize_hex_color( $input['button_border_color'] ) : $defaults['button_border_color'];
		$sanitized['button_hover_color']        = isset( $input['button_hover_color'] ) ? sanitize_hex_color( $input['button_hover_color'] ) : $defaults['button_hover_color'];
		$sanitized['button_border_radius']      = isset( $input['button_border_radius'] ) ? sanitize_text_field( $input['button_border_radius'] ) : $defaults['button_border_radius'];
		$sanitized['button_icon_url']           = isset( $input['button_icon_url'] ) ? esc_url_raw( $input['button_icon_url'] ) : '';
		$sanitized['floating_button_icon_url']  = isset( $input['floating_button_icon_url'] ) ? esc_url_raw( $input['floating_button_icon_url'] ) : '';
		$sanitized['shop_button_icon_url']      = isset( $input['shop_button_icon_url'] ) ? esc_url_raw( $input['shop_button_icon_url'] ) : '';
		$sanitized['widget_avatar_url']         = isset( $input['widget_avatar_url'] ) ? esc_url_raw( $input['widget_avatar_url'] ) : '';
		$sanitized['button_icon_size']          = isset( $input['button_icon_size'] ) ? sanitize_text_field( $input['button_icon_size'] ) : $defaults['button_icon_size'];
		$sanitized['enable_product_button']  = ! empty( $input['enable_product_button'] ) ? 1 : 0;
		$sanitized['enable_shop_button']     = ! empty( $input['enable_shop_button'] ) ? 1 : 0;
		$sanitized['enable_cart_button']     = ! empty( $input['enable_cart_button'] ) ? 1 : 0;
		$sanitized['enable_floating_button'] = ! empty( $input['enable_floating_button'] ) ? 1 : 0;
		$sanitized['enable_chat_widget']     = ! empty( $input['enable_chat_widget'] ) ? 1 : 0;
		$sanitized['require_phone_number']   = ! empty( $input['require_phone_number'] ) ? 1 : 0;
		$sanitized['auto_redirect_whatsapp']  = ! empty( $input['auto_redirect_whatsapp'] ) ? 1 : 0;
		$sanitized['email_notify_enabled']    = ! empty( $input['email_notify_enabled'] ) ? 1 : 0;
		$sanitized['email_notify_address']    = isset( $input['email_notify_address'] ) ? sanitize_email( $input['email_notify_address'] ) : '';
		$limit = isset( $input['max_emails_per_hour'] ) ? absint( $input['max_emails_per_hour'] ) : $defaults['max_emails_per_hour'];
		$sanitized['max_emails_per_hour']     = min( 500, max( 0, $limit ) );

		$allowed_styles = array( 'stack_outline', 'stack_outline_blue' );
		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro() ) {
			$allowed_styles[] = 'side_by_side';
			$allowed_styles[] = 'custom';
		}
		$style_id = isset( $input['shop_button_style'] ) ? sanitize_key( $input['shop_button_style'] ) : $defaults['shop_button_style'];
		$sanitized['shop_button_style'] = in_array( $style_id, $allowed_styles, true ) ? $style_id : $defaults['shop_button_style'];
		$sanitized['shop_button_text']  = isset( $input['shop_button_text'] ) ? sanitize_text_field( $input['shop_button_text'] ) : '';
		$layout = isset( $input['shop_custom_layout'] ) ? sanitize_key( $input['shop_custom_layout'] ) : 'stack';
		$sanitized['shop_custom_layout'] = in_array( $layout, array( 'stack', 'row' ), true ) ? $layout : 'stack';
		$variant = isset( $input['shop_custom_wa_variant'] ) ? sanitize_key( $input['shop_custom_wa_variant'] ) : 'outline';
		$sanitized['shop_custom_wa_variant'] = in_array( $variant, array( 'solid', 'outline' ), true ) ? $variant : 'outline';
		$sanitized['shop_custom_atc_bg']     = isset( $input['shop_custom_atc_bg'] ) ? sanitize_hex_color( $input['shop_custom_atc_bg'] ) : $defaults['shop_custom_atc_bg'];
		$sanitized['shop_custom_wa_bg']      = isset( $input['shop_custom_wa_bg'] ) ? sanitize_hex_color( $input['shop_custom_wa_bg'] ) : $defaults['shop_custom_wa_bg'];
		$sanitized['shop_custom_wa_text']    = isset( $input['shop_custom_wa_text'] ) ? sanitize_hex_color( $input['shop_custom_wa_text'] ) : $defaults['shop_custom_wa_text'];
		$sanitized['shop_custom_wa_border']  = isset( $input['shop_custom_wa_border'] ) ? sanitize_hex_color( $input['shop_custom_wa_border'] ) : $defaults['shop_custom_wa_border'];
		$sanitized['shop_custom_radius']     = isset( $input['shop_custom_radius'] ) ? sanitize_text_field( $input['shop_custom_radius'] ) : $defaults['shop_custom_radius'];
		$sanitized['shop_custom_label']      = isset( $input['shop_custom_label'] ) ? sanitize_text_field( $input['shop_custom_label'] ) : '';

		$allowed_positions = array( 'before_add_to_cart', 'after_add_to_cart', 'product_meta' );
		$position          = isset( $input['button_position'] ) ? sanitize_text_field( $input['button_position'] ) : $defaults['button_position'];
		$sanitized['button_position'] = in_array( $position, $allowed_positions, true ) ? $position : $defaults['button_position'];
return $sanitized;
	}

	/**
	 * Sanitize JSON string into an array before saving.
	 *
	 * @param string|array $input Raw JSON string or array.
	 * @return array
	 */
	public function sanitize_json_to_array( $input ) {
		if ( is_array( $input ) ) {
			return $input;
		}
		
		$decoded = json_decode( wp_unslash( $input ), true );
		return ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) ? $decoded : array();
	}

	/**
	 * General section helper.
	 *
	 * @return void
	 */
	public function render_general_section() {
		// Intentionally empty — panel header already explains this page.
	}

	/**
	 * Widget section helper.
	 *
	 * @return void
	 */
	public function render_widget_section() {
		// Intentionally empty — panel header already explains this page.
	}

	/**
	 * Render buttons section.
	 *
	 * @return void
	 */
	public function render_buttons_section() {
		// Intentionally empty — panel header already explains this page.
	}

	/**
	 * Render chat styles section inside settings.
	 *
	 * @return void
	 */
	public function render_styles_section() {
		if ( class_exists( 'CQFW_Chat_Styles' ) ) {
			CQFW_Chat_Styles::render_styles_tab_content();
		}
	}

	/**
	 * Render about section.
	 *
	 * @return void
	 */
	public function render_about_section() {
		$wc_active = function_exists( 'cqfw_woocommerce_active' ) && cqfw_woocommerce_active();
		?>
		<div class="cqfw-about-content">
			<?php self::render_easy_checklist( 'cqfw-about' ); ?>

			<div class="cqfw-help-cards">
				<a class="cqfw-help-card" href="<?php echo esc_url( admin_url( 'admin.php?page=cqfw-settings' ) ); ?>">
					<span class="dashicons dashicons-phone"></span>
					<strong><?php esc_html_e( '1. Number', 'chat-quote-for-woocommerce' ); ?></strong>
					<small><?php esc_html_e( 'Where messages go', 'chat-quote-for-woocommerce' ); ?></small>
				</a>
				<a class="cqfw-help-card" href="<?php echo esc_url( admin_url( 'admin.php?page=cqfw-widget' ) ); ?>">
					<span class="dashicons dashicons-format-chat"></span>
					<strong><?php esc_html_e( '2. Chat Bubble', 'chat-quote-for-woocommerce' ); ?></strong>
					<small><?php esc_html_e( 'Floating chat on site', 'chat-quote-for-woocommerce' ); ?></small>
				</a>
				<?php if ( $wc_active ) : ?>
				<a class="cqfw-help-card" href="<?php echo esc_url( admin_url( 'admin.php?page=cqfw-buttons' ) ); ?>">
					<span class="dashicons dashicons-cart"></span>
					<strong><?php esc_html_e( '3. Buy Buttons', 'chat-quote-for-woocommerce' ); ?></strong>
					<small><?php esc_html_e( 'On product / shop / cart', 'chat-quote-for-woocommerce' ); ?></small>
				</a>
				<?php endif; ?>
				<a class="cqfw-help-card" href="<?php echo esc_url( admin_url( 'admin.php?page=cqfw-messages' ) ); ?>">
					<span class="dashicons dashicons-email-alt"></span>
					<strong><?php esc_html_e( 'Inbox', 'chat-quote-for-woocommerce' ); ?></strong>
					<small><?php esc_html_e( 'Read site chat messages', 'chat-quote-for-woocommerce' ); ?></small>
				</a>
			</div>

			<details class="cqfw-help-faq" open>
				<summary><?php esc_html_e( 'Common questions', 'chat-quote-for-woocommerce' ); ?></summary>
				<ul>
					<li><strong><?php esc_html_e( 'Number format?', 'chat-quote-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Digits only — example: 01732593040', 'chat-quote-for-woocommerce' ); ?></li>
					<?php if ( $wc_active ) : ?>
					<li><strong><?php esc_html_e( 'Nothing shows on shop?', 'chat-quote-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Open Buy Buttons and turn on product/shop. Then Save.', 'chat-quote-for-woocommerce' ); ?></li>
					<?php else : ?>
					<li><strong><?php esc_html_e( 'Need WooCommerce?', 'chat-quote-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Not for support chat. Install WooCommerce when you want product, shop, and cart WhatsApp buttons.', 'chat-quote-for-woocommerce' ); ?></li>
					<?php endif; ?>
					<li><strong><?php esc_html_e( 'Where are chat messages?', 'chat-quote-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Chat Quote → Inbox', 'chat-quote-for-woocommerce' ); ?></li>
					<li><strong><?php esc_html_e( 'Need Pro?', 'chat-quote-for-woocommerce' ); ?></strong> <?php esc_html_e( 'No for basics. Pro is optional (agents, hours, checkout WhatsApp switch).', 'chat-quote-for-woocommerce' ); ?></li>
				</ul>
			</details>
		</div>
		<?php
	}

	/**
	 * Pro section helper.
	 *
	 * @return void
	 */
	public function render_pro_section() {
		echo '<p class="cqfw-section-help">' . esc_html__( 'Add chat agents, set when you are open, and extra fields for the quote form.', 'chat-quote-for-woocommerce' ) . '</p>';
	}

	/**
	 * Render a visual UI for Pro JSON options.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_pro_json_field( $args ) {
		$key = $args['key'];
		$val = get_option( $key, null );

		// Provide helpful defaults if not yet customized
		if ( null === $val || ( is_array( $val ) && empty( $val ) ) ) {
			if ( 'cqfw_custom_form_fields' === $key && class_exists( 'CQFW_Pro_Fields' ) ) {
				$val = CQFW_Pro_Fields::get_configured_fields();
			} elseif ( 'cqfw_whatsapp_agents' === $key && class_exists( 'CQFW_Pro_Business_Hours' ) ) {
				$val = CQFW_Pro_Business_Hours::get_agents();
			} elseif ( 'cqfw_business_hours_schedule' === $key ) {
				$val = array(
					'enabled'   => false,
					'monday'    => array( 'active' => true, 'start' => '09:00', 'end' => '18:00' ),
					'tuesday'   => array( 'active' => true, 'start' => '09:00', 'end' => '18:00' ),
					'wednesday' => array( 'active' => true, 'start' => '09:00', 'end' => '18:00' ),
					'thursday'  => array( 'active' => true, 'start' => '09:00', 'end' => '18:00' ),
					'friday'    => array( 'active' => true, 'start' => '09:00', 'end' => '18:00' ),
					'saturday'  => array( 'active' => false, 'start' => '09:00', 'end' => '18:00' ),
					'sunday'    => array( 'active' => false, 'start' => '09:00', 'end' => '18:00' ),
				);
			}
		}

		if ( is_string( $val ) ) {
			$val_decoded = json_decode( wp_unslash( $val ), true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$val = $val_decoded;
			}
		}

		if ( ! is_array( $val ) ) {
			$val = array();
		}

		// Hidden textarea to store JSON for saving
		$json_str = wp_json_encode( $val, JSON_UNESCAPED_UNICODE );
		echo '<textarea id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" style="display:none;">' . esc_textarea( $json_str ) . '</textarea>';

		if ( 'cqfw_custom_form_fields' === $key ) {
			$this->render_custom_fields_ui( $val, $key );
		} elseif ( 'cqfw_whatsapp_agents' === $key ) {
			$this->render_agents_ui( $val, $key );
		} elseif ( 'cqfw_business_hours_schedule' === $key ) {
			$this->render_business_hours_ui( $val, $key );
		}
	}

	/**
	 * Render UI for Custom Form Fields.
	 *
	 * @param array  $fields Current fields.
	 * @param string $key    Option key.
	 */
	private function render_custom_fields_ui( $fields, $key ) {
		?>
		<div class="cqfw-pro-ui-builder" id="cqfw-builder-<?php echo esc_attr( $key ); ?>" data-key="<?php echo esc_attr( $key ); ?>">
			<div class="cqfw-pro-rows">
				<?php if ( ! empty( $fields ) && is_array( $fields ) ) : foreach ( $fields as $i => $field ) : ?>
				<div class="cqfw-pro-row" style="display:flex;gap:10px;align-items:center;margin-bottom:10px;background:#f8fafc;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;flex-wrap:wrap;">
					<div>
						<label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;"><?php esc_html_e( 'Field ID', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" data-col="id" value="<?php echo esc_attr( $field['id'] ?? '' ); ?>" placeholder="company_name" style="width:130px;height:34px;" />
					</div>
					<div>
						<label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;"><?php esc_html_e( 'Label', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" data-col="label" value="<?php echo esc_attr( $field['label'] ?? '' ); ?>" placeholder="Company Name" style="width:140px;height:34px;" />
					</div>
					<div>
						<label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;"><?php esc_html_e( 'Type', 'chat-quote-for-woocommerce' ); ?></label>
						<select data-col="type" style="width:100px;height:34px;">
							<?php foreach ( array( 'text', 'email', 'tel', 'number', 'url', 'file' ) as $t ) : ?>
								<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $field['type'] ?? 'text', $t ); ?>><?php echo esc_html( $t ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;"><?php esc_html_e( 'Placeholder', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" data-col="placeholder" value="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" placeholder="Your Company" style="width:140px;height:34px;" />
					</div>
					<div style="padding-top:16px;">
						<label style="cursor:pointer;font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px;">
							<input type="checkbox" data-col="required" <?php checked( ! empty( $field['required'] ) ); ?> />
							<?php esc_html_e( 'Required', 'chat-quote-for-woocommerce' ); ?>
						</label>
					</div>
					<div style="padding-top:16px;">
						<button type="button" class="button cqfw-remove-pro-row" style="color:#dc2626;border-color:#fca5a5;background:#fef2f2;">&times; <?php esc_html_e( 'Remove', 'chat-quote-for-woocommerce' ); ?></button>
					</div>
				</div>
				<?php endforeach; endif; ?>
			</div>
			<button type="button" class="button button-secondary cqfw-add-pro-row" style="font-weight:600;">+ <?php esc_html_e( 'Add Field', 'chat-quote-for-woocommerce' ); ?></button>
			<p class="description" style="margin-top:8px;"><?php esc_html_e( 'These extra fields will appear on the Quote Request form below the standard fields.', 'chat-quote-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render UI for WhatsApp Agents.
	 *
	 * @param array  $agents Current agents.
	 * @param string $key    Option key.
	 */
	private function render_agents_ui( $agents, $key ) {
		?>
		<div class="cqfw-pro-ui-builder" id="cqfw-builder-<?php echo esc_attr( $key ); ?>" data-key="<?php echo esc_attr( $key ); ?>">
			<div class="cqfw-pro-rows">
				<?php if ( ! empty( $agents ) && is_array( $agents ) ) : foreach ( $agents as $agent ) :
					$avatar_url = ! empty( $agent['avatar'] ) ? $agent['avatar'] : '';
				?>
				<div class="cqfw-pro-row" style="display:flex;gap:12px;align-items:center;margin-bottom:12px;background:#f8fafc;padding:12px 16px;border:1px solid #e2e8f0;border-radius:8px;flex-wrap:wrap;">
					<div class="cqfw-agent-avatar-col" style="display:flex;flex-direction:column;gap:3px;">
						<label style="display:block;font-size:11px;font-weight:600;color:#64748b;"><?php esc_html_e( 'Avatar Photo', 'chat-quote-for-woocommerce' ); ?></label>
						<div style="display:flex;align-items:center;gap:6px;">
							<div class="cqfw-agent-avatar-preview" style="width:42px;height:42px;border-radius:50%;overflow:hidden;background:#e2e8f0;border:1px solid #cbd5e1;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
								<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;<?php echo empty( $avatar_url ) ? 'display:none;' : ''; ?>" />
								<span class="cqfw-avatar-placeholder" style="font-size:20px;line-height:1;color:#94a3b8;<?php echo ! empty( $avatar_url ) ? 'display:none;' : ''; ?>">👤</span>
							</div>
							<input type="hidden" data-col="avatar" value="<?php echo esc_attr( $avatar_url ); ?>" />
							<button type="button" class="button button-small cqfw-upload-agent-avatar" style="font-size:11px;height:28px;line-height:26px;"><?php esc_html_e( 'Upload', 'chat-quote-for-woocommerce' ); ?></button>
							<button type="button" class="button button-small cqfw-remove-agent-avatar" title="<?php esc_attr_e( 'Remove photo', 'chat-quote-for-woocommerce' ); ?>" style="font-size:12px;height:28px;line-height:24px;padding:0 8px;color:#ef4444;border-color:#fca5a5;background:#fef2f2;<?php echo empty( $avatar_url ) ? 'display:none;' : ''; ?>">&times;</button>
						</div>
					</div>
					<div>
						<label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;"><?php esc_html_e( 'Agent Name', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" data-col="name" value="<?php echo esc_attr( $agent['name'] ?? '' ); ?>" placeholder="e.g. Deo" style="width:130px;height:34px;" />
					</div>
					<div>
						<label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;"><?php esc_html_e( 'WhatsApp Number', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" data-col="phone" value="<?php echo esc_attr( $agent['phone'] ?? '' ); ?>" placeholder="+8801XXXXXXXXX" style="width:150px;height:34px;" />
					</div>
					<div>
						<label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;"><?php esc_html_e( 'Department / Subtitle', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" data-col="department" value="<?php echo esc_attr( $agent['department'] ?? '' ); ?>" placeholder="e.g. Tech Support" style="width:140px;height:34px;" />
					</div>
					<div>
						<label style="display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;"><?php esc_html_e( 'Working Hours (Online)', 'chat-quote-for-woocommerce' ); ?></label>
						<div style="display:flex;align-items:center;gap:4px;">
							<input type="time" data-col="start_time" value="<?php echo esc_attr( $agent['start_time'] ?? '' ); ?>" title="<?php esc_attr_e( 'Start Time (leave blank for 24/7)', 'chat-quote-for-woocommerce' ); ?>" style="width:105px;height:34px;padding:2px 6px;" />
							<span style="color:#94a3b8;font-size:12px;">-</span>
							<input type="time" data-col="end_time" value="<?php echo esc_attr( $agent['end_time'] ?? '' ); ?>" title="<?php esc_attr_e( 'End Time (leave blank for 24/7)', 'chat-quote-for-woocommerce' ); ?>" style="width:105px;height:34px;padding:2px 6px;" />
						</div>
					</div>
					<div style="padding-top:16px;">
						<button type="button" class="button cqfw-remove-pro-row" style="color:#dc2626;border-color:#fca5a5;background:#fef2f2;">&times; <?php esc_html_e( 'Remove', 'chat-quote-for-woocommerce' ); ?></button>
					</div>
				</div>
				<?php endforeach; endif; ?>
			</div>
			<button type="button" class="button button-secondary cqfw-add-pro-row" style="font-weight:600;">+ <?php esc_html_e( 'Add Agent', 'chat-quote-for-woocommerce' ); ?></button>
			<p class="description" style="margin-top:8px;"><?php esc_html_e( 'Configure agents with photo, name, and working hours. Online agents are active and clickable; offline agents cannot be clicked. Leave hours blank for 24/7 availability.', 'chat-quote-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render UI for Business Hours.
	 *
	 * @param array  $schedule Current schedule.
	 * @param string $key      Option key.
	 */
	private function render_business_hours_ui( $schedule, $key ) {
		$days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$labels = array(
			'monday'    => __( 'Monday', 'chat-quote-for-woocommerce' ),
			'tuesday'   => __( 'Tuesday', 'chat-quote-for-woocommerce' ),
			'wednesday' => __( 'Wednesday', 'chat-quote-for-woocommerce' ),
			'thursday'  => __( 'Thursday', 'chat-quote-for-woocommerce' ),
			'friday'    => __( 'Friday', 'chat-quote-for-woocommerce' ),
			'saturday'  => __( 'Saturday', 'chat-quote-for-woocommerce' ),
			'sunday'    => __( 'Sunday', 'chat-quote-for-woocommerce' ),
		);
		$enabled = ! empty( $schedule['enabled'] );
		?>
		<div class="cqfw-pro-ui-builder cqfw-biz-hours" id="cqfw-builder-<?php echo esc_attr( $key ); ?>" data-key="<?php echo esc_attr( $key ); ?>">
			<label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-weight:600;cursor:pointer;">
				<input type="checkbox" id="cqfw_biz_enabled" data-biz="enabled" <?php checked( $enabled ); ?> />
				<?php esc_html_e( 'Enable Business Hours', 'chat-quote-for-woocommerce' ); ?>
			</label>
			<table style="border-collapse:collapse;width:100%;max-width:540px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
				<thead>
					<tr style="background:#f1f5f9;border-bottom:1px solid #e2e8f0;">
						<th style="text-align:left;padding:8px 12px;width:120px;font-size:12px;color:#475569;"><?php esc_html_e( 'Day', 'chat-quote-for-woocommerce' ); ?></th>
						<th style="text-align:center;padding:8px 12px;width:90px;font-size:12px;color:#475569;"><?php esc_html_e( 'Status', 'chat-quote-for-woocommerce' ); ?></th>
						<th style="text-align:left;padding:8px 12px;font-size:12px;color:#475569;"><?php esc_html_e( 'Open Time', 'chat-quote-for-woocommerce' ); ?></th>
						<th style="text-align:left;padding:8px 12px;font-size:12px;color:#475569;"><?php esc_html_e( 'Close Time', 'chat-quote-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $days as $day ) :
					$day_data = isset( $schedule[ $day ] ) ? $schedule[ $day ] : array();
					$active   = ! empty( $day_data['active'] );
					$start    = isset( $day_data['start'] ) ? $day_data['start'] : '09:00';
					$end      = isset( $day_data['end'] )   ? $day_data['end']   : '18:00';
				?>
				<tr style="border-bottom:1px solid #f1f5f9;background:<?php echo $active ? '#f0fdf4' : '#fafafa'; ?>;" class="cqfw-biz-row" data-day="<?php echo esc_attr( $day ); ?>">
					<td style="padding:8px 12px;font-weight:600;font-size:13px;"><?php echo esc_html( $labels[ $day ] ); ?></td>
					<td style="text-align:center;padding:8px 12px;">
						<label style="cursor:pointer;display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:<?php echo $active ? '#16a34a' : '#64748b'; ?>;" class="cqfw-biz-status-label">
							<input type="checkbox" data-biz-col="active" data-day="<?php echo esc_attr( $day ); ?>" <?php checked( $active ); ?> />
							<span><?php echo $active ? esc_html__( 'Open', 'chat-quote-for-woocommerce' ) : esc_html__( 'Closed', 'chat-quote-for-woocommerce' ); ?></span>
						</label>
					</td>
					<td style="padding:8px 12px;">
						<input type="time" data-biz-col="start" data-day="<?php echo esc_attr( $day ); ?>" value="<?php echo esc_attr( $start ); ?>" style="width:110px;height:34px;padding:4px 8px;border:1px solid #cbd5e1;border-radius:6px;" />
					</td>
					<td style="padding:8px 12px;">
						<input type="time" data-biz-col="end" data-day="<?php echo esc_attr( $day ); ?>" value="<?php echo esc_attr( $end ); ?>" style="width:110px;height:34px;padding:4px 8px;border:1px solid #cbd5e1;border-radius:6px;" />
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description" style="margin-top:8px;"><?php esc_html_e( 'The chat widget will show an offline message outside of these hours.', 'chat-quote-for-woocommerce' ); ?></p>
		</div>
		<?php
	}


	/**
	 * Render a checkbox field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_checkbox_field( $args ) {
		$key     = $args['key'];
		$setting = self::get_setting( $key, 0 );
		$checked = checked( 1, (int) $setting, false );
		printf(
			'<div class="cqfw-toggle-switch">
				<input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s />
				<label class="cqfw-toggle-slider" for="%1$s"></label>
			</div>',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $checked )
		);
	}

	/**
	 * Dial-code countries for WhatsApp number UI (CQFW-owned list).
	 *
	 * @return array<int,array{iso:string,name:string,dial:string,flag:string}>
	 */
	public static function get_dial_countries() {
		$countries = array(
			array( 'iso' => 'BD', 'name' => 'Bangladesh', 'dial' => '880', 'flag' => '🇧🇩' ),
			array( 'iso' => 'IN', 'name' => 'India', 'dial' => '91', 'flag' => '🇮🇳' ),
			array( 'iso' => 'US', 'name' => 'United States', 'dial' => '1', 'flag' => '🇺🇸' ),
			array( 'iso' => 'GB', 'name' => 'United Kingdom', 'dial' => '44', 'flag' => '🇬🇧' ),
			array( 'iso' => 'AE', 'name' => 'United Arab Emirates', 'dial' => '971', 'flag' => '🇦🇪' ),
			array( 'iso' => 'SA', 'name' => 'Saudi Arabia', 'dial' => '966', 'flag' => '🇸🇦' ),
			array( 'iso' => 'PK', 'name' => 'Pakistan', 'dial' => '92', 'flag' => '🇵🇰' ),
			array( 'iso' => 'MY', 'name' => 'Malaysia', 'dial' => '60', 'flag' => '🇲🇾' ),
			array( 'iso' => 'SG', 'name' => 'Singapore', 'dial' => '65', 'flag' => '🇸🇬' ),
			array( 'iso' => 'ID', 'name' => 'Indonesia', 'dial' => '62', 'flag' => '🇮🇩' ),
			array( 'iso' => 'AU', 'name' => 'Australia', 'dial' => '61', 'flag' => '🇦🇺' ),
			array( 'iso' => 'CA', 'name' => 'Canada', 'dial' => '1', 'flag' => '🇨🇦' ),
			array( 'iso' => 'DE', 'name' => 'Germany', 'dial' => '49', 'flag' => '🇩🇪' ),
			array( 'iso' => 'FR', 'name' => 'France', 'dial' => '33', 'flag' => '🇫🇷' ),
			array( 'iso' => 'NL', 'name' => 'Netherlands', 'dial' => '31', 'flag' => '🇳🇱' ),
			array( 'iso' => 'TR', 'name' => 'Turkey', 'dial' => '90', 'flag' => '🇹🇷' ),
			array( 'iso' => 'NG', 'name' => 'Nigeria', 'dial' => '234', 'flag' => '🇳🇬' ),
			array( 'iso' => 'ZA', 'name' => 'South Africa', 'dial' => '27', 'flag' => '🇿🇦' ),
			array( 'iso' => 'BR', 'name' => 'Brazil', 'dial' => '55', 'flag' => '🇧🇷' ),
			array( 'iso' => 'PH', 'name' => 'Philippines', 'dial' => '63', 'flag' => '🇵🇭' ),
		);

		/**
		 * Filter CQFW dial-code country list.
		 *
		 * @param array $countries Countries.
		 */
		return apply_filters( 'cqfw_dial_countries', $countries );
	}

	/**
	 * Split a stored E.164-ish digit string into dial code + national number.
	 *
	 * @param string $digits Digits only.
	 * @return array{dial:string,national:string,iso:string}
	 */
	public static function split_phone_number( $digits ) {
		$digits = preg_replace( '/\D+/', '', (string) $digits );
		$best   = array(
			'dial'     => '880',
			'national' => $digits,
			'iso'      => 'BD',
		);

		if ( '' === $digits ) {
			return $best;
		}

		$countries = self::get_dial_countries();
		usort(
			$countries,
			static function ( $a, $b ) {
				return strlen( $b['dial'] ) - strlen( $a['dial'] );
			}
		);

		foreach ( $countries as $country ) {
			$dial = $country['dial'];
			if ( 0 === strpos( $digits, $dial ) && strlen( $digits ) > strlen( $dial ) ) {
				return array(
					'dial'     => $dial,
					'national' => substr( $digits, strlen( $dial ) ),
					'iso'      => $country['iso'],
				);
			}
		}

		// Local BD numbers often start with 01…
		if ( preg_match( '/^0\d{9,10}$/', $digits ) ) {
			return array(
				'dial'     => '880',
				'national' => ltrim( $digits, '0' ),
				'iso'      => 'BD',
			);
		}

		return $best;
	}

	/**
	 * Floating toast markup used after settings save.
	 *
	 * @param string $message Message text.
	 * @param string $type    success|info|error.
	 * @return void
	 */
	public static function render_toast( $message, $type = 'success' ) {
		$type = in_array( $type, array( 'success', 'info', 'error' ), true ) ? $type : 'success';
		printf(
			'<div class="cqfw-toast is-%1$s" data-cqfw-toast="1" role="status"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * WhatsApp phone field with country dial picker (stores full digits).
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_phone_field( $args ) {
		$key      = $args['key'];
		$setting  = (string) self::get_setting( $key, '' );
		$parts    = self::split_phone_number( $setting );
		$countries = self::get_dial_countries();
		$uid      = 'cqfw-phone-' . sanitize_html_class( $key );
		?>
		<div class="cqfw-phone-field" data-cqfw-phone="<?php echo esc_attr( $key ); ?>">
			<input type="hidden" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( preg_replace( '/\D+/', '', $setting ) ); ?>" data-cqfw-phone-full />
			<label class="cqfw-phone-field__country screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-dial"><?php esc_html_e( 'Country code', 'chat-quote-for-woocommerce' ); ?></label>
			<select id="<?php echo esc_attr( $uid ); ?>-dial" class="cqfw-phone-field__dial" data-cqfw-phone-dial>
				<?php foreach ( $countries as $country ) : ?>
					<option
						value="<?php echo esc_attr( $country['dial'] ); ?>"
						data-iso="<?php echo esc_attr( $country['iso'] ); ?>"
						<?php selected( $parts['iso'] . '-' . $parts['dial'], $country['iso'] . '-' . $country['dial'] ); ?>
					>
						<?php echo esc_html( $country['flag'] . ' +' . $country['dial'] . ' ' . $country['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<input
				type="tel"
				inputmode="numeric"
				autocomplete="tel-national"
				class="cqfw-phone-field__local regular-text"
				id="<?php echo esc_attr( $uid ); ?>-local"
				value="<?php echo esc_attr( $parts['national'] ); ?>"
				placeholder="<?php echo esc_attr( '880' === $parts['dial'] ? '1732593040' : 'Phone number' ); ?>"
				data-cqfw-phone-local
			/>
			<p class="description cqfw-phone-field__hint">
				<?php esc_html_e( 'Saved as international digits for WhatsApp (example: 8801732593040). Leading 0 is removed automatically.', 'chat-quote-for-woocommerce' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render a text field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_text_field( $args ) {
		$key     = $args['key'];
		$setting = self::get_setting( $key, '' );
		printf(
			'<input type="text" class="regular-text" id="%1$s" name="%2$s[%1$s]" value="%3$s" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $setting )
		);
	}

	/**
	 * Notification email field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_notify_email_field( $args ) {
		$key     = $args['key'];
		$setting = self::get_setting( $key, '' );
		printf(
			'<input type="email" class="regular-text" id="%1$s" name="%2$s[%1$s]" value="%3$s" placeholder="%4$s" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $setting ),
			esc_attr( get_option( 'admin_email' ) )
		);
		echo '<p class="description">' . esc_html__( 'Leave blank to use the WordPress admin email. Emails send instantly when a customer messages — no cron.', 'chat-quote-for-woocommerce' ) . '</p>';
	}

	/**
	 * Hourly email cap field (shared hosting safe).
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_email_limit_field( $args ) {
		$key     = $args['key'];
		$setting = (int) self::get_setting( $key, 100 );
		printf(
			'<input type="number" min="0" max="500" step="1" class="small-text" id="%1$s" name="%2$s[%1$s]" value="%3$s" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( (string) $setting )
		);
		echo '<p class="description">' . esc_html__( 'Recommended 100 on shared hosting. 0 = no limit. Extra alerts wait for the next customer message (still no cron).', 'chat-quote-for-woocommerce' ) . '</p>';
	}

	/**
	 * Render a color field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_color_field( $args ) {
		$key     = $args['key'];
		$setting = self::get_setting( $key, '' );
		printf(
			'<input type="text" class="cqfw-color-field" id="%1$s" name="%2$s[%1$s]" value="%3$s" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $setting )
		);
	}

	/**
	 * Render an image upload field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_image_field( $args ) {
		$key     = $args['key'];
		$setting = self::get_setting( $key, '' );
		?>
		<div class="cqfw-image-field">
			<input type="text" class="regular-text cqfw-image-url" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $setting ); ?>" />
			<button type="button" class="button cqfw-upload-image"><?php echo esc_html__( 'Upload Image', 'chat-quote-for-woocommerce' ); ?></button>
			<button type="button" class="button cqfw-remove-image"><?php echo esc_html__( 'Remove', 'chat-quote-for-woocommerce' ); ?></button>
			<div class="cqfw-image-preview" <?php echo '' === $setting ? 'style="display:none;"' : ''; ?>>
				<img src="<?php echo esc_url( $setting ); ?>" alt="" />
			</div>
		</div>
		<?php
	}

	/**
	 * Render template field.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_template_field( $args ) {
		$key     = $args['key'];
		$setting = self::get_setting( $key, '' );
		printf(
			'<textarea class="large-text code" rows="8" id="%1$s" name="%2$s[%1$s]">%3$s</textarea><p class="description">%4$s</p>',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_textarea( $setting ),
			esc_html__( 'Available variables: {product_name}, {product_price}, {product_sku}, {product_qty}, {product_url}', 'chat-quote-for-woocommerce' )
		);
	}

	/**
	 * Render position selector.
	 *
	 * @param array<string,mixed> $args Field args.
	 * @return void
	 */
	public function render_button_position_field( $args ) {
		$key     = $args['key'];
		$setting = self::get_setting( $key, 'after_add_to_cart' );
		$options = array(
			'before_add_to_cart' => __( 'Before Add to Cart', 'chat-quote-for-woocommerce' ),
			'after_add_to_cart'  => __( 'After Add to Cart', 'chat-quote-for-woocommerce' ),
			'product_meta'       => __( 'Product Meta Area', 'chat-quote-for-woocommerce' ),
		);

		echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']">';
		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $setting, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Go Pro / business model page (Free installs).
	 *
	 * @return void
	 */
	public function render_go_pro_page() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			return;
		}
		if ( class_exists( 'CQFW_Upgrade' ) ) {
			CQFW_Upgrade::render_page();
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=cqfw-settings' ) );
		exit;
	}

	/**
	 * Render About page.
	 */
	public function render_about_page() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) { return; }
		self::render_page_header( 'cqfw-about' );
		$wc_active = function_exists( 'cqfw_woocommerce_active' ) && cqfw_woocommerce_active();
		?>
		<section class="cqfw-panel">
			<header class="cqfw-panel__head">
				<h2><?php esc_html_e( 'How to use Chat Quote', 'chat-quote-for-woocommerce' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						$wc_active
							? __( 'Three steps — then optional extras. Use the Setup tabs above to open each page.', 'chat-quote-for-woocommerce' )
							: __( 'Add your WhatsApp number and turn on the chat bubble. Support messaging works without WooCommerce; install WooCommerce later for shop buy buttons.', 'chat-quote-for-woocommerce' )
					);
					?>
				</p>
			</header>
			<div class="cqfw-panel__body">
				<?php $this->render_about_section(); ?>
			</div>
		</section>
		<?php
		self::render_page_footer();
	}

	/**
	 * Render Pro Configuration page.
	 */
	public function render_pro_page() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) { return; }
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$updated = isset( $_GET['updated'] ) && '1' === $_GET['updated'];
		self::render_page_header( 'cqfw-pro' );
		if ( $updated ) {
			echo '<div class="cqfw-toast is-success" data-cqfw-toast="1" role="status"><p>' . esc_html__( 'Pro Configuration saved.', 'chat-quote-for-woocommerce' ) . '</p></div>';
		}
		?>
		<section class="cqfw-panel">
			<header class="cqfw-panel__head">
				<h2><?php esc_html_e( 'Extra Pro tools', 'chat-quote-for-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'Optional extras — not required for basic WhatsApp buttons and chat. Basics stay under Setup steps 1–3.', 'chat-quote-for-woocommerce' ); ?></p>
			</header>
			<form class="cqfw-panel__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cqfw_save_pro_action', 'cqfw_pro_nonce' ); ?>
				<input type="hidden" name="action" value="cqfw_save_pro" />

				<div class="cqfw-pro-block">
					<h3><span class="dashicons dashicons-forms"></span> <?php esc_html_e( 'Custom Form Fields', 'chat-quote-for-woocommerce' ); ?></h3>
					<p><?php esc_html_e( 'Add extra fields (e.g. Company Name, File Upload) to the Quote Request form.', 'chat-quote-for-woocommerce' ); ?></p>
					<?php $this->render_pro_json_field( array( 'key' => 'cqfw_custom_form_fields' ) ); ?>
				</div>

				<div class="cqfw-pro-block">
					<h3><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'WhatsApp Multi-Agents', 'chat-quote-for-woocommerce' ); ?></h3>
					<p><?php esc_html_e( 'Add multiple support or sales agents with custom phone numbers, avatars, departments, and working hours.', 'chat-quote-for-woocommerce' ); ?></p>
					<?php $this->render_pro_json_field( array( 'key' => 'cqfw_whatsapp_agents' ) ); ?>
				</div>

				<div class="cqfw-pro-block">
					<h3><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Business Operating Hours', 'chat-quote-for-woocommerce' ); ?></h3>
					<p><?php esc_html_e( 'Enable schedule-based availability. The widget shows offline banners outside operating hours.', 'chat-quote-for-woocommerce' ); ?></p>
					<?php $this->render_pro_json_field( array( 'key' => 'cqfw_business_hours_schedule' ) ); ?>
				</div>

				<?php
				if ( class_exists( 'CQFW_Widget_Controls' ) ) {
					CQFW_Widget_Controls::render_admin_panels( 'pro' );
				}
				$this->render_rich_chat_pro_box();
				if ( class_exists( 'CQFW_Pro_Checkout_WhatsApp' ) ) {
					CQFW_Pro_Checkout_WhatsApp::render_admin_box();
				}
				?>

				<footer class="cqfw-panel__foot">
					<button type="submit" class="button button-primary cqfw-btn-save"><?php esc_html_e( 'Save Pro Configuration', 'chat-quote-for-woocommerce' ); ?></button>
				</footer>
			</form>
		</section>
		<?php
		self::render_page_footer();
	}

	/**
	 * Rich chat Pro toggles — rendered once (Pro page only).
	 *
	 * @return void
	 */
	private function render_rich_chat_pro_box() {
		if ( ! function_exists( 'cqfw_fs' ) || ! cqfw_fs()->is__premium_only() ) {
			return;
		}
		?>
		<div style="margin: 24px 0 32px; background:#ffffff; border:1px solid #a5f3fc; border-radius:10px; padding:20px;">
			<h3 style="font-size:16px;font-weight:700;margin:0 0 8px 0;color:#1e293b;display:flex;align-items:center;gap:8px;">
				<span class="dashicons dashicons-format-chat" style="color:#0ea5e9;"></span> <?php esc_html_e( 'Rich Chat Input & Attachments', 'chat-quote-for-woocommerce' ); ?>
			</h3>
			<p style="margin:0 0 16px 0; color:#64748b; font-size:13px;"><?php esc_html_e( 'Modern pill input with file/screenshot attachments and emoji picker.', 'chat-quote-for-woocommerce' ); ?></p>
			<div style="display:flex;flex-direction:column;gap:12px;background:#f0fdfa;padding:16px 18px;border-radius:8px;border:1px solid #e2e8f0;">
				<label style="display:flex;align-items:center;gap:10px;font-weight:600;color:#1e293b;cursor:pointer;">
					<input type="checkbox" name="cqfw_pro_enable_attachments" value="1" <?php checked( get_option( 'cqfw_pro_enable_attachments', '1' ), '1' ); ?> />
					<span><?php esc_html_e( 'File & screenshot attachments', 'chat-quote-for-woocommerce' ); ?></span>
				</label>
				<label style="display:flex;align-items:center;gap:10px;font-weight:600;color:#1e293b;cursor:pointer;">
					<input type="checkbox" name="cqfw_pro_enable_emojis" value="1" <?php checked( get_option( 'cqfw_pro_enable_emojis', '1' ), '1' ); ?> />
					<span><?php esc_html_e( 'Emoji reactions picker', 'chat-quote-for-woocommerce' ); ?></span>
				</label>
				<label style="display:flex;align-items:center;gap:10px;font-weight:600;color:#1e293b;cursor:pointer;">
					<input type="checkbox" name="cqfw_pro_modern_pill_input" value="1" <?php checked( get_option( 'cqfw_pro_modern_pill_input', '1' ), '1' ); ?> />
					<span><?php esc_html_e( 'Modern pill input bar', 'chat-quote-for-woocommerce' ); ?></span>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle save for Pro Configuration.
	 */
	public function handle_save_pro() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) { wp_die( 'Unauthorized' ); }
		check_admin_referer( 'cqfw_save_pro_action', 'cqfw_pro_nonce' );

		if ( isset( $_POST['cqfw_custom_form_fields'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$val = $this->sanitize_json_to_array( wp_unslash( $_POST['cqfw_custom_form_fields'] ) );
			update_option( 'cqfw_custom_form_fields', $val );
		}

		if ( isset( $_POST['cqfw_whatsapp_agents'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$val = $this->sanitize_json_to_array( wp_unslash( $_POST['cqfw_whatsapp_agents'] ) );
			update_option( 'cqfw_whatsapp_agents', $val );
		}

		if ( isset( $_POST['cqfw_business_hours_schedule'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$val = $this->sanitize_json_to_array( wp_unslash( $_POST['cqfw_business_hours_schedule'] ) );
			update_option( 'cqfw_business_hours_schedule', $val );
		}

		if ( isset( $_POST['cqfw_widget_controls'] ) && class_exists( 'CQFW_Widget_Controls' ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw = wp_unslash( $_POST['cqfw_widget_controls'] );
			if ( ! is_array( $raw ) ) {
				$raw = array();
			}
			$allow_pro = ! empty( $_POST['cqfw_controls_include_pro'] )
				&& function_exists( 'cqfw_fs' )
				&& cqfw_fs()->is__premium_only()
				&& CQFW_Widget_Controls::can_use_pro_controls();

			// Pro form: only normalize Pro checkboxes (keep global Free settings intact).
			$bool_keys = $allow_pro
				? CQFW_Widget_Controls::pro_checkbox_keys()
				: CQFW_Widget_Controls::free_checkbox_keys();
			foreach ( $bool_keys as $bk ) {
				if ( ! isset( $raw[ $bk ] ) ) {
					$raw[ $bk ] = 0;
				}
			}

			$controls = CQFW_Widget_Controls::sanitize( $raw, $allow_pro );
			update_option( CQFW_Widget_Controls::OPTION, $controls );
		}

		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro() ) {
			update_option( 'cqfw_pro_enable_attachments', isset( $_POST['cqfw_pro_enable_attachments'] ) ? '1' : '0' );
			update_option( 'cqfw_pro_enable_emojis', isset( $_POST['cqfw_pro_enable_emojis'] ) ? '1' : '0' );
			update_option( 'cqfw_pro_modern_pill_input', isset( $_POST['cqfw_pro_modern_pill_input'] ) ? '1' : '0' );
			if ( class_exists( 'CQFW_Pro_Checkout_WhatsApp' ) ) {
				CQFW_Pro_Checkout_WhatsApp::save_admin_option();
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'cqfw-pro', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

}
