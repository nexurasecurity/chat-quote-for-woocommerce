<?php
/**
 * Product/shop WhatsApp buttons and floating chat widget.
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_WhatsApp {

	/**
	 * Prevent the same shop CTA from printing twice (classic hook + block filter).
	 *
	 * @var array<int,bool>
	 */
	private static $shop_cta_rendered = array();

	/**
	 * Build a Free-safe default agent list for card popups when Pro multi-agent is unavailable.
	 * Needed after Freemius free deploy strips Pro agents — otherwise Style 1/2 popups never render.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_default_popup_agents( $settings ) {
		$phone = '';
		if ( ! empty( $settings['whatsapp_number'] ) ) {
			$phone = preg_replace( '/\D/', '', (string) $settings['whatsapp_number'] );
		}
		if ( ! $phone && ! empty( $settings['whatsapp_fallback_number'] ) ) {
			$phone = preg_replace( '/\D/', '', (string) $settings['whatsapp_fallback_number'] );
		}

		$site_name = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$name      = $site_name ? $site_name : __( 'Support Team', 'chat-quote-for-woocommerce' );

		return array(
			array(
				'name'       => $name,
				'phone'      => $phone,
				'department' => __( 'Support', 'chat-quote-for-woocommerce' ),
				'avatar'     => ! empty( $settings['widget_avatar_url'] ) ? $settings['widget_avatar_url'] : '',
			),
		);
	}

	/**
	 * Resolve agents for the floating popup (Pro list, or Free default for card styles).
	 *
	 * @param array<string,mixed> $settings    Plugin settings.
	 * @param string              $popup_style Active popup style id.
	 * @return array<int,array<string,mixed>>
	 */
	private function resolve_popup_agents( $settings, $popup_style ) {
		$agents = apply_filters( 'cqfw_whatsapp_button_agents', array() );
		if ( empty( $agents ) && class_exists( 'CQFW_Pro_Business_Hours' ) ) {
			$agents = CQFW_Pro_Business_Hours::get_agents();
		}
		if ( ! is_array( $agents ) ) {
			$agents = array();
		}

		if ( ! empty( $agents ) ) {
			return array_values( $agents );
		}

		// Card popup styles (Free Style 1/2 + Pro 3–6) need at least one card to paint.
		$card_styles = array(
			'style_1_modern_card',
			'classic_whatsapp',
			'style_3_compact',
			'style_4_glass',
			'style_5_dark',
			'style_6_gradient',
		);
		if ( in_array( $popup_style, $card_styles, true ) ) {
			return $this->get_default_popup_agents( $settings );
		}

		return array();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Core support: assets + floating chat (works without WooCommerce).
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_floating_widget' ) );

		// Shop / product / cart CTAs need WooCommerce.
		if ( ! function_exists( 'cqfw_woocommerce_active' ) || ! cqfw_woocommerce_active() ) {
			return;
		}

		add_filter( 'body_class', array( 'CQFW_Shop_Button_Styles', 'filter_body_class' ) );
		add_action( 'wp', array( $this, 'setup_shop_loop_buttons' ) );

		// Block themes / product grids — only when not already handled by classic loop.
		add_filter( 'render_block', array( $this, 'filter_product_button_block' ), 20, 2 );
		add_filter( 'woocommerce_blocks_product_grid_item_html', array( $this, 'filter_blocks_product_grid_item' ), 20, 3 );

		$position = CQFW_Settings::get_setting( 'button_position', 'after_add_to_cart' );
		if ( 'before_add_to_cart' === $position ) {
			add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_product_button' ) );
		} elseif ( 'product_meta' === $position ) {
			add_action( 'woocommerce_product_meta_start', array( $this, 'render_product_button' ) );
		} else {
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_product_button' ) );
		}
	}

	/**
	 * Theme-agnostic shop loop: remove theme ATC and render our controlled pair/CTA.
	 *
	 * @return void
	 */
	public function setup_shop_loop_buttons() {
		$settings = CQFW_Settings::get_settings();
		if ( empty( $settings['enable_shop_button'] ) ) {
			return;
		}

		// Classic / hybrid themes — drop default loop ATC so any theme layout won't fight us.
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' );

		// Always hook classic loop (shortcodes / hybrid). Duplicate prints are blocked in render_styled_cta().
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_shop_button' ), 10 );
	}

	/**
	 * Block themes (e.g. Twenty Twenty-Five): replace Product Button block with our CTA pair.
	 *
	 * @param string              $block_content Block HTML.
	 * @param array<string,mixed> $block         Parsed block.
	 * @return string
	 */
	public function filter_product_button_block( $block_content, $block ) {
		$settings = CQFW_Settings::get_settings();
		if ( empty( $settings['enable_shop_button'] ) ) {
			return $block_content;
		}

		$name = isset( $block['blockName'] ) ? $block['blockName'] : '';
		if ( 'woocommerce/product-button' !== $name ) {
			return $block_content;
		}

		// Catalog/archive only — never replace single-product form ATC.
		if ( function_exists( 'is_product' ) && is_product() && ! ( function_exists( 'wc_get_loop_prop' ) && wc_get_loop_prop( 'name' ) ) ) {
			return $block_content;
		}

		$product = $this->get_loop_product();
		if ( ! $product ) {
			return $block_content;
		}

		ob_start();
		$this->render_styled_cta( $product, 'shop' );
		$cta = ob_get_clean();

		return ( $cta && '' !== trim( $cta ) ) ? $cta : $block_content;
	}

	/**
	 * Legacy Woo product grid block HTML filter.
	 *
	 * @param string     $html    Item HTML.
	 * @param object     $data    Grid item data.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function filter_blocks_product_grid_item( $html, $data, $product ) {
		$settings = CQFW_Settings::get_settings();
		if ( empty( $settings['enable_shop_button'] ) || ! $product instanceof WC_Product ) {
			return $html;
		}

		// Already injected via woocommerce/product-button render_block — do not append again.
		if ( false !== strpos( $html, 'cqfw-shop-loop-actions' ) || false !== strpos( $html, 'cqfw-cta-wa-btn' ) ) {
			return $html;
		}

		ob_start();
		$this->render_styled_cta( $product, 'shop' );
		$cta = ob_get_clean();
		if ( ! $cta || '' === trim( $cta ) ) {
			return $html;
		}

		return $html . $cta;
	}

	/**
	 * Current product in loop / queried object.
	 *
	 * @return WC_Product|null
	 */
	private function get_loop_product() {
		global $product;
		if ( $product instanceof WC_Product ) {
			return $product;
		}
		if ( function_exists( 'wc_get_product' ) ) {
			$id = get_the_ID();
			if ( $id ) {
				$obj = wc_get_product( $id );
				if ( $obj ) {
					return $obj;
				}
			}
		}
		return null;
	}

	/**
	 * Should assets load.
	 *
	 * @return bool
	 */
	private function should_load_assets() {
		if ( is_admin() ) {
			return false;
		}

		$settings = CQFW_Settings::get_settings();

		if ( ! empty( $settings['enable_chat_widget'] ) || ! empty( $settings['enable_floating_button'] ) ) {
			return true;
		}

		if ( function_exists( 'is_product' ) && is_product() && ! empty( $settings['enable_product_button'] ) ) {
			return true;
		}

		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() && ( ! empty( $settings['enable_shop_button'] ) || ! empty( $settings['enable_product_button'] ) ) ) {
			return true;
		}

		if ( function_exists( 'is_shop' ) && is_shop() && ! empty( $settings['enable_shop_button'] ) ) {
			return true;
		}

		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() && ! empty( $settings['enable_shop_button'] ) ) {
			return true;
		}

		if ( function_exists( 'is_cart' ) && is_cart() && ! empty( $settings['enable_cart_button'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue front-end assets.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets() {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		$css_ver = file_exists( CQFW_PATH . 'assets/css/chat-widget.css' ) ? (string) filemtime( CQFW_PATH . 'assets/css/chat-widget.css' ) : CQFW_VERSION;
		$js_ver  = file_exists( CQFW_PATH . 'assets/js/chat-widget.js' ) ? (string) filemtime( CQFW_PATH . 'assets/js/chat-widget.js' ) : CQFW_VERSION;

		wp_enqueue_style(
			'cqfw-chat-widget',
			CQFW_URL . 'assets/css/chat-widget.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'cqfw-chat-widget',
			CQFW_URL . 'assets/js/chat-widget.js',
			array(),
			$js_ver,
			true
		);

		if ( class_exists( 'CQFW_Shop_Button_Styles' ) ) {
			$cta_vars = CQFW_Shop_Button_Styles::get_css_variables();
			if ( $cta_vars ) {
				wp_add_inline_style( 'cqfw-chat-widget', ':root{' . $cta_vars . ';}' );
			}
		}

		$is_open = apply_filters( 'cqfw_is_business_open', true );
		$agents  = apply_filters( 'cqfw_whatsapp_button_agents', array() );

		$product_obj = $this->get_current_product();
		$template    = CQFW_Settings::get_setting( 'default_message_template', CQFW_Analytics::get_default_template() );
		if ( $product_obj ) {
			$default_msg = str_replace(
				array( '{product_name}', '{product_price}', '{product_sku}', '{product_qty}', '{product_url}' ),
				array(
					$product_obj->get_name(),
					CQFW_Analytics::format_price_text( $product_obj->get_price() ),
					$product_obj->get_sku(),
					'1',
					get_permalink( $product_obj->get_id() ),
				),
				$template
			);
		} else {
			$default_msg = CQFW_Settings::get_setting( 'welcome_message', __( 'Hi there! 👋 How can we help you today?', 'chat-quote-for-woocommerce' ) );
		}

		$localize = array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'cqfw_nonce' ),
			'settings'          => CQFW_Settings::get_settings(),
			'ajaxActionTrack'   => 'cqfw_log_click',
			'ajaxActionChat'    => 'cqfw_send_chat_message',
			'ajaxActionHistory' => 'cqfw_get_chat_history',
			'ajaxActionPoll'    => 'cqfw_poll_messages',
			'ajaxActionWebhook' => 'cqfw_webhook_ping',
			'context'           => $this->get_page_context(),
			'product'           => $this->get_product_context(),
			'agentName'         => CQFW_Settings::get_setting( 'agent_name', '' ),
			'welcomeMessage'    => CQFW_Settings::get_setting( 'welcome_message', __( 'Hi there! 👋 How can we help you today?', 'chat-quote-for-woocommerce' ) ),
			'defaultMessage'    => $default_msg,
			'isBusinessOpen'    => $is_open,
			'agents'            => $agents,
			'controls'          => array(),
		);

		$localize = apply_filters( 'cqfw_widget_localize', $localize );

		wp_localize_script( 'cqfw-chat-widget', 'cqfwChat', $localize );
	}

	/**
	 * Render the product button.
	 *
	 * @return void
	 */
	public function render_product_button() {
		$settings = CQFW_Settings::get_settings();

		if ( empty( $settings['enable_product_button'] ) || ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$product = $this->get_current_product();
		if ( ! $product ) {
			return;
		}

		$this->render_styled_cta( $product, 'product' );
	}

	/**
	 * Render the shop/archive button.
	 *
	 * @return void
	 */
	public function render_shop_button() {
		$settings = CQFW_Settings::get_settings();

		if ( empty( $settings['enable_shop_button'] ) || ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$this->render_styled_cta( $product, 'shop' );
	}

	/**
	 * Shared WhatsApp CTA markup (matches admin style preview classes).
	 * Shop context always renders a theme-independent ATC + Buy pair.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $context product|shop.
	 * @return void
	 */
	private function render_styled_cta( $product, $context = 'shop' ) {
		$url = $this->build_product_whatsapp_url( $product );
		if ( '' === $url ) {
			return;
		}

		// Hard stop duplicate shop pairs (block filter + classic hook / double blocks).
		if ( 'shop' === $context ) {
			// Never print shop-loop pair on the main single-product summary.
			if ( function_exists( 'is_product' ) && is_product() ) {
				$loop_name = function_exists( 'wc_get_loop_prop' ) ? wc_get_loop_prop( 'name' ) : '';
				$in_named_loop = ! empty( $loop_name ) || ! empty( $GLOBALS['woocommerce_loop']['name'] );
				if ( ! $in_named_loop && ! ( function_exists( 'is_shop' ) && is_shop() ) && ! ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) ) {
					return;
				}
			}

			$pid = absint( $product->get_id() );
			if ( $pid && ! empty( self::$shop_cta_rendered[ $pid ] ) ) {
				return;
			}
			if ( $pid ) {
				self::$shop_cta_rendered[ $pid ] = true;
			}
		}

		$config = class_exists( 'CQFW_Shop_Button_Styles' )
			? CQFW_Shop_Button_Styles::get_style_config()
			: array(
				'id'             => 'stack_outline',
				'layout'         => 'stack',
				'wa_variant'     => 'outline',
				'label_template' => __( 'Buy {product_name} via WhatsApp', 'chat-quote-for-woocommerce' ),
				'wa_bg'          => '#ffffff',
				'wa_text'        => '#22c55e',
				'wa_border'      => '#22c55e',
				'radius'         => '999px',
				'atc_bg'         => '#22c55e',
				'atc_text'       => '#ffffff',
			);

		$label = class_exists( 'CQFW_Shop_Button_Styles' )
			? CQFW_Shop_Button_Styles::resolve_label( $config['label_template'], $product->get_name() )
			: $product->get_name();

		$wrapper_class = class_exists( 'CQFW_Shop_Button_Styles' )
			? CQFW_Shop_Button_Styles::get_wrapper_classes( $config, $context )
			: 'cqfw-shop-cta cqfw-cta-wrap';

		if ( 'product' === $context ) {
			$wrapper_class = str_replace( 'cqfw-shop-cta', 'cqfw-product-cta', $wrapper_class );
		}

		$css_vars = class_exists( 'CQFW_Shop_Button_Styles' )
			? CQFW_Shop_Button_Styles::get_css_variables( $config )
			: '';

		$wa_style = class_exists( 'CQFW_Shop_Button_Styles' )
			? CQFW_Shop_Button_Styles::get_wa_inline_style( $config )
			: CQFW_Settings::get_button_inline_style();

		$allowed_icon = array(
			'span' => array(
				'class'       => array(),
				'aria-hidden' => array(),
				'style'       => array(),
			),
			'svg'  => array(
				'class'   => array(),
				'width'   => array(),
				'height'  => array(),
				'viewBox' => array(),
				'fill'    => array(),
				'xmlns'   => array(),
			),
			'path' => array(
				'd' => array(),
			),
		);

		$icon_context = ( 'shop' === $context ) ? 'shop' : 'default';
		$style_id     = ! empty( $config['id'] ) ? $config['id'] : '';
		$layout       = ! empty( $config['layout'] ) ? $config['layout'] : 'stack';
		$icon_html    = ( 'side_by_side' === $style_id )
			? ''
			: wp_kses( $this->render_button_icon( $icon_context ), $allowed_icon );

		$allowed_cta = array_merge(
			$allowed_icon,
			array(
				'a' => array(
					'href'              => true,
					'class'             => true,
					'style'             => true,
					'rel'               => true,
					'aria-label'        => true,
					'data-quantity'     => true,
					'data-product_id'   => true,
					'data-product_sku'  => true,
					'data-cqfw-track'   => true,
					'data-page-type'    => true,
					'data-product-id'   => true,
					'data-whatsapp-url' => true,
				),
			)
		);

		$wa_btn = sprintf(
			'<a class="cqfw-inline-btn cqfw-cta-wa-btn" style="display:flex;justify-content:center;align-items:center;%1$s" href="%2$s" data-cqfw-track="1" data-page-type="%3$s" data-product-id="%4$d" data-whatsapp-url="%2$s">%5$s%6$s</a>',
			esc_attr( $wa_style ),
			esc_url( $url ),
			esc_attr( $context ),
			absint( $product->get_id() ),
			$icon_html,
			'<span class="cqfw-floating-label">' . esc_html( $label ) . '</span>'
		);

		// Shop / archive: plugin owns both buttons so any theme looks like the admin preview.
		if ( 'shop' === $context ) {
			$atc_style = sprintf(
				'background-color:%1$s !important;color:%2$s !important;border-radius:%3$s !important;border:none !important;',
				esc_attr( ! empty( $config['atc_bg'] ) ? $config['atc_bg'] : '#3b82f6' ),
				esc_attr( ! empty( $config['atc_text'] ) ? $config['atc_text'] : '#ffffff' ),
				esc_attr( ! empty( $config['radius'] ) ? $config['radius'] : '10px' )
			);

			$atc_classes = array(
				'button',
				'cqfw-cta-atc-btn',
				'product_type_' . $product->get_type(),
			);
			if ( $product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() && $product->is_in_stock() ) {
				$atc_classes[] = 'add_to_cart_button';
				$atc_classes[] = 'ajax_add_to_cart';
			}

			$atc_btn = sprintf(
				'<a href="%1$s" data-quantity="1" class="%2$s" style="%3$s" data-product_id="%4$d" data-product_sku="%5$s" aria-label="%6$s" rel="nofollow">%7$s</a>',
				esc_url( $product->add_to_cart_url() ),
				esc_attr( implode( ' ', $atc_classes ) ),
				esc_attr( $atc_style ),
				absint( $product->get_id() ),
				esc_attr( $product->get_sku() ),
				esc_attr( $product->add_to_cart_description() ),
				esc_html( $product->add_to_cart_text() )
			);

			$pair_class = 'cqfw-shop-loop-actions cqfw-cta-wrap cqfw-cta-layout--' . sanitize_html_class( $layout )
				. ' cqfw-cta-style--' . sanitize_html_class( $style_id )
				. ' cqfw-cta-has-pair';

			printf(
				'<div class="%1$s" style="%2$s">%3$s%4$s</div>',
				esc_attr( $pair_class ),
				esc_attr( $css_vars ),
				wp_kses( $atc_btn, $allowed_cta ),
				wp_kses( $wa_btn, $allowed_cta )
			);
			return;
		}

		printf(
			'<div class="%1$s" style="%2$s">%3$s</div>',
			esc_attr( $wrapper_class ),
			esc_attr( $css_vars ),
			wp_kses( $wa_btn, $allowed_cta )
		);
	}

	/**
	 * Render floating button and messenger widget.
	 *
	 * @return void
	 */
	public function render_floating_widget() {
		$settings = CQFW_Settings::get_settings();

		if ( empty( $settings['enable_chat_widget'] ) || empty( $settings['enable_floating_button'] ) ) {
			return;
		}

		if ( ! apply_filters( 'cqfw_should_show_widget', true ) ) {
			return;
		}

		$title      = ! empty( $settings['chat_popup_title'] ) ? $settings['chat_popup_title'] : __( 'Chat with us', 'chat-quote-for-woocommerce' );
		$buttonText = ! empty( $settings['floating_button_text'] ) ? $settings['floating_button_text'] : __( 'Chat with us', 'chat-quote-for-woocommerce' );
		$product    = $this->get_product_context();
		$page_url   = $this->get_current_url();
		$has_icon   = '' !== CQFW_Settings::get_button_icon_url( 'floating' );
		$button_classes = $has_icon ? 'cqfw-floating-button cqfw-floating-button--icon-only' : 'cqfw-floating-button';

		$is_open = apply_filters( 'cqfw_is_business_open', true );
		$buttonText = apply_filters( 'cqfw_floating_cta_text', $buttonText, $is_open );

		$controls = class_exists( 'CQFW_Widget_Controls' ) ? CQFW_Widget_Controls::get() : array();
		$greeting = class_exists( 'CQFW_Widget_Controls' ) ? CQFW_Widget_Controls::get_active_greeting() : array( 'header' => '', 'main' => '', 'bottom' => '' );
		$entry    = ! empty( $controls['entry_effect'] ) ? $controls['entry_effect'] : 'fade';
		$show_badge = ! isset( $controls['notification_badge'] ) || ! empty( $controls['notification_badge'] );

		// Chat Styles configuration (resolve popup style before agents — Free card styles need a fallback agent).
		$style_cfg = class_exists( 'CQFW_Chat_Styles' ) ? CQFW_Chat_Styles::get_saved_settings() : array();
		$active_style = ! empty( $style_cfg['active_style'] ) ? $style_cfg['active_style'] : 'style_1';
		$popup_style    = ! empty( $style_cfg['popup_style'] ) ? $style_cfg['popup_style'] : 'style_1_modern_card';
		$agents         = $this->resolve_popup_agents( $settings, $popup_style );
		$has_multi_agents = ! empty( $agents );
		$is_style_3     = ( 'style_3_compact' === $popup_style );
		$is_style_4     = ( 'style_4_glass' === $popup_style );
		$is_style_5     = ( 'style_5_dark' === $popup_style );
		$is_style_6     = ( 'style_6_gradient' === $popup_style );
		$is_classic_wa  = ( 'classic_whatsapp' === $popup_style );
		$is_modern_card = ( ! $is_classic_wa && ! $is_style_3 && ! $is_style_4 && ! $is_style_5 && ! $is_style_6 );
		$style_class  = 'cqfw-launcher--' . sanitize_html_class( str_replace( '_', '-', $active_style ) );
		$is_icon_style = in_array( $active_style, array( 'style_2', 'style_3', 'style_3_extend', 'style_7', 'style_7_extend' ), true );
		$show_icon    = $is_icon_style || ! isset( $style_cfg['add_icon'] ) || '0' !== (string) $style_cfg['add_icon'];
		$icon_color   = ! empty( $style_cfg['icon_color'] ) ? $style_cfg['icon_color'] : '';
		$icon_size    = ! empty( $style_cfg['icon_size'] ) ? $style_cfg['icon_size'] : '';
		$custom_image = ! empty( $style_cfg['custom_image'] ) ? $style_cfg['custom_image'] : '';
		$full_mobile  = ! empty( $style_cfg['full_width_mobile'] );

		// Placement & CSS Variables
		$pos_type     = ! empty( $style_cfg['position_type'] ) ? $style_cfg['position_type'] : 'fixed';
		$pos_v_side   = ! empty( $style_cfg['pos_v_side'] ) ? $style_cfg['pos_v_side'] : 'bottom';
		$pos_v_offset = ! empty( $style_cfg['pos_v_offset'] ) ? $style_cfg['pos_v_offset'] : '24px';
		$pos_h_side   = ! empty( $style_cfg['pos_h_side'] ) ? $style_cfg['pos_h_side'] : 'right';
		$pos_h_offset = ! empty( $style_cfg['pos_h_offset'] ) ? $style_cfg['pos_h_offset'] : '24px';

		$root_styles = array(
			'position:' . esc_attr( $pos_type ),
			esc_attr( $pos_v_side ) . ':' . esc_attr( $pos_v_offset ),
			esc_attr( $pos_h_side ) . ':' . esc_attr( $pos_h_offset ),
		);

		if ( ! empty( $style_cfg['background_color'] ) ) {
			$root_styles[] = '--cq-green:' . esc_attr( $style_cfg['background_color'] );
			$root_styles[] = '--cq-button-custom-bg:' . esc_attr( $style_cfg['background_color'] );
			$root_styles[] = '--cqfw-button-bg:' . esc_attr( $style_cfg['background_color'] );
		}
		if ( ! empty( $style_cfg['text_color'] ) ) {
			$root_styles[] = '--cq-button-custom-text:' . esc_attr( $style_cfg['text_color'] );
			$root_styles[] = '--cqfw-button-text:' . esc_attr( $style_cfg['text_color'] );
		}
		if ( ! empty( $icon_color ) ) {
			$root_styles[] = '--cq-icon-custom-color:' . esc_attr( $icon_color );
		}
		if ( ! empty( $icon_size ) ) {
			$root_styles[] = '--cq-icon-custom-size:' . esc_attr( $icon_size );
		}

		$root_style_attr = implode( ';', $root_styles ) . ';' . CQFW_Settings::get_button_style_attr();
		$pos_classes     = 'cqfw-pos-' . sanitize_html_class( $pos_h_side ) . ' cqfw-pos-' . sanitize_html_class( $pos_v_side );
		$entry_class     = 'cqfw-entry-' . sanitize_html_class( $entry );
		$root_attrs      = array(
			'data-page-url'   => $page_url,
			'data-product-id' => (string) $product['product_id'],
			'data-entry'      => $entry,
		);
		$root_attrs = apply_filters( 'cqfw_widget_root_attrs', $root_attrs );

		// Only hide until JS triggers when Pro delays/viewport are active (no FOUC / no JS = still visible).
		$needs_defer = false;
		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && class_exists( 'CQFW_Widget_Controls' ) && CQFW_Widget_Controls::can_use_pro_controls() ) {
			$needs_defer = ( ! empty( $controls['time_delay'] ) || ! empty( $controls['scroll_delay'] ) || ! empty( $controls['viewport_trigger'] ) );
		}
		$visibility_class = $needs_defer ? 'cqfw-is-hidden' : 'cqfw-is-ready';

		$attrs_html = '';
		foreach ( $root_attrs as $attr_k => $attr_v ) {
			$attrs_html .= ' ' . esc_attr( $attr_k ) . '="' . esc_attr( $attr_v ) . '"';
		}
		?>
<div id="cqfw-widget-root" class="cqfw-widget-root <?php echo esc_attr( $visibility_class ); ?> <?php echo esc_attr( $pos_classes ); ?> <?php echo esc_attr( $entry_class ); ?> <?php echo $full_mobile ? 'cqfw-has-full-mobile' : ''; ?>" style="<?php echo esc_attr( $root_style_attr ); ?>"<?php echo $attrs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr above. ?>>
    <?php if ( ! empty( $settings['enable_floating_button'] ) ) : ?>
    <button type="button" class="cqfw-floating-button cqfw-is-whatsapp <?php echo esc_attr( $style_class ); ?> <?php echo $full_mobile ? 'cqfw-launcher--full-mobile' : ''; ?>" aria-expanded="false" aria-controls="cqfw-chat-panel" aria-label="<?php echo esc_attr( $buttonText ); ?>">
        <?php if ( 'style_99' === $active_style && ! empty( $custom_image ) ) : ?>
            <img src="<?php echo esc_url( $custom_image ); ?>" alt="<?php echo esc_attr( $buttonText ); ?>" class="cqfw-launcher-custom-img" />
        <?php elseif ( 'style_5' === $active_style ) : 
            $avatar_src = ! empty( $custom_image ) ? $custom_image : ( ! empty( $agents[0]['avatar'] ) ? $agents[0]['avatar'] : ( ! empty( $settings['widget_avatar_url'] ) ? $settings['widget_avatar_url'] : '' ) );
        ?>
            <div class="cqfw-launcher-avatar-wrap">
                <?php if ( ! empty( $avatar_src ) ) : ?>
                    <img src="<?php echo esc_url( $avatar_src ); ?>" alt="<?php echo esc_attr( $buttonText ); ?>" class="cqfw-launcher-avatar-img" />
                <?php else : ?>
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="#64748b" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <?php endif; ?>
                <span class="cqfw-launcher-avatar-dot"></span>
            </div>
        <?php elseif ( 'style_10' === $active_style ) : 
            $s10_title = __( 'Chat with us', 'chat-quote-for-woocommerce' );
            $s10_sub   = __( '3 agents online', 'chat-quote-for-woocommerce' );
            $s10_avatars = array(
                ! empty( $style_cfg['agent_1_avatar'] ) ? $style_cfg['agent_1_avatar'] : CQFW_URL . 'assets/images/agent-1.png',
                ! empty( $style_cfg['agent_2_avatar'] ) ? $style_cfg['agent_2_avatar'] : CQFW_URL . 'assets/images/agent-2.png',
                ! empty( $style_cfg['agent_3_avatar'] ) ? $style_cfg['agent_3_avatar'] : CQFW_URL . 'assets/images/agent-3.png',
            );
        ?>
            <div class="cqfw-s10-content">
                <div class="cqfw-s10-avatars">
                    <img class="cqfw-s10-avatar" src="<?php echo esc_url( $s10_avatars[0] ); ?>" alt="Agent 1" style="z-index: 1;" />
                    <img class="cqfw-s10-avatar" src="<?php echo esc_url( $s10_avatars[1] ); ?>" alt="Agent 2" style="z-index: 2;" />
                    <img class="cqfw-s10-avatar" src="<?php echo esc_url( $s10_avatars[2] ); ?>" alt="Agent 3" style="z-index: 3;" />
                    <span class="cqfw-s10-online-dot"></span>
                </div>
                <div class="cqfw-s10-text">
                    <span class="cqfw-s10-title"><?php echo esc_html( $s10_title ); ?></span>
                    <span class="cqfw-s10-subtitle"><?php echo esc_html( $s10_sub ); ?></span>
                </div>
                <div class="cqfw-s10-wa-btn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="#ffffff" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
                    </svg>
                </div>
            </div>
        <?php elseif ( 'style_11' === $active_style ) : 
            $s11_avatar = ! empty( $style_cfg['agent_1_avatar'] ) ? $style_cfg['agent_1_avatar'] : CQFW_URL . 'assets/images/agent-sales.png';
            $s11_online = __( 'Online', 'chat-quote-for-woocommerce' );
            $s11_role   = __( 'Sales Support', 'chat-quote-for-woocommerce' );
        ?>
            <div class="cqfw-s11-wrapper">
                <div class="cqfw-s11-bubble">
                    <img class="cqfw-s11-avatar" src="<?php echo esc_url( $s11_avatar ); ?>" alt="Agent" />
                    <div class="cqfw-s11-info">
                        <div class="cqfw-s11-status">
                            <span class="cqfw-s11-status-text"><?php echo esc_html( $s11_online ); ?></span>
                            <span class="cqfw-s11-status-dot"></span>
                        </div>
                        <span class="cqfw-s11-role"><?php echo esc_html( $s11_role ); ?></span>
                    </div>
                </div>
                <div class="cqfw-s11-btn-wrap">
                    <div class="cqfw-s11-halo-ring"></div>
                    <div class="cqfw-s11-wa-btn">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="#ffffff" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
                        </svg>
                        <span class="cqfw-s11-beacon-dot"></span>
                    </div>
                </div>
            </div>
        <?php elseif ( 'style_12' === $active_style ) : 
            $s12_online = __( 'Online now', 'chat-quote-for-woocommerce' );
            $s12_title  = __( '3 agents available', 'chat-quote-for-woocommerce' );
            $s12_sub    = __( 'Tap to chat with our team', 'chat-quote-for-woocommerce' );
            $s12_avatars = array(
                ! empty( $style_cfg['agent_1_avatar'] ) ? $style_cfg['agent_1_avatar'] : CQFW_URL . 'assets/images/agent-1.png',
                ! empty( $style_cfg['agent_2_avatar'] ) ? $style_cfg['agent_2_avatar'] : CQFW_URL . 'assets/images/agent-2.png',
                ! empty( $style_cfg['agent_3_avatar'] ) ? $style_cfg['agent_3_avatar'] : CQFW_URL . 'assets/images/agent-3.png',
            );
        ?>
            <div class="cqfw-s12-wrapper">
                <div class="cqfw-s12-top-container">
                    <div class="cqfw-s12-card">
                        <div class="cqfw-s12-sparks" aria-hidden="true">
                            <svg width="22" height="18" viewBox="0 0 24 20" fill="none">
                                <path d="M7 16L3 2" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                                <path d="M14 18L21 8" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="cqfw-s12-status">
                            <span class="cqfw-s12-status-dot"></span>
                            <span class="cqfw-s12-status-text"><?php echo esc_html( $s12_online ); ?></span>
                        </div>
                        <div class="cqfw-s12-title"><?php echo esc_html( $s12_title ); ?></div>
                        <div class="cqfw-s12-sub"><?php echo esc_html( $s12_sub ); ?></div>
                    </div>
                    <div class="cqfw-s12-agents-pill">
                        <?php foreach ( $s12_avatars as $idx => $avatar ) : ?>
                            <div class="cqfw-s12-agent-item">
                                <img class="cqfw-s12-avatar" src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( 'Agent ' . ( $idx + 1 ) ); ?>" />
                                <span class="cqfw-s12-agent-dot"></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="cqfw-s12-btn-wrap">
                    <div class="cqfw-s12-halo-ring"></div>
                    <div class="cqfw-s12-wa-btn">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="#ffffff" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
                        </svg>
                        <span class="cqfw-s12-count-badge">3</span>
                    </div>
                </div>
            </div>
        <?php elseif ( 'style_6' === $active_style ) : ?>
            <span class="cqfw-launcher-text-only"><?php echo esc_html( $buttonText ); ?></span>
        <?php else : ?>
            <?php if ( $show_icon ) : ?>
            <svg class="cqfw-wa-svg-icon" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
            </svg>
            <?php endif; ?>

            <?php if ( in_array( $active_style, array( 'style_1', 'style_4', 'style_8' ), true ) ) : ?>
                <span class="cqfw-launcher-label"><?php echo esc_html( $buttonText ); ?></span>
            <?php endif; ?>

            <?php if ( $show_badge && 'style_3_extend' === $active_style ) : ?>
                <span class="cqfw-launcher-badge">1</span>
            <?php endif; ?>
        <?php endif; ?>
    </button>
    <?php endif; ?>

    <div id="cqfw-chat-panel" class="cqfw-chat-panel" aria-hidden="true">

        <?php if ( $has_multi_agents ) : ?>
        <!-- Multi-Agent Cards View (Click to Chat matching reference image) -->
        <div class="cqfw-multi-agent-view <?php 
            if ( $is_style_6 ) {
                echo 'cqfw-popup--style-6';
            } elseif ( $is_style_5 ) {
                echo 'cqfw-popup--style-5';
            } elseif ( $is_style_4 ) {
                echo 'cqfw-popup--style-4';
            } elseif ( $is_style_3 ) {
                echo 'cqfw-popup--style-3';
            } elseif ( $is_classic_wa ) {
                echo 'cqfw-popup--classic-whatsapp';
            } else {
                echo 'cqfw-popup--modern-card';
            }
        ?>">
            <?php if ( $is_style_6 ) : ?>
            <div class="cqfw-multi-agent-header cqfw-s6-header">
                <div class="cqfw-s6-header-left">
                    <div class="cqfw-s6-chat-badge" aria-hidden="true">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="#ffffff">
                            <path d="M12 3C6.477 3 2 7.029 2 12c0 1.83.612 3.528 1.666 4.93L2.5 21l4.288-1.144C8.163 20.524 10.022 21 12 21c5.523 0 10-4.029 10-9s-4.477-9-10-9z"/>
                            <circle cx="8" cy="12" r="1.3" fill="#059669"/>
                            <circle cx="12" cy="12" r="1.3" fill="#059669"/>
                            <circle cx="16" cy="12" r="1.3" fill="#059669"/>
                        </svg>
                    </div>
                    <div class="cqfw-s6-header-text">
                        <h3 class="cqfw-s6-title"><?php echo ! empty( $settings['chat_popup_title'] ) ? esc_html( $settings['chat_popup_title'] ) : esc_html__( 'Chat with us', 'chat-quote-for-woocommerce' ); ?></h3>
                        <p class="cqfw-s6-subtitle"><?php echo ! empty( $settings['chat_popup_subtitle'] ) ? esc_html( $settings['chat_popup_subtitle'] ) : esc_html__( 'Any questions related to Multi Agent?', 'chat-quote-for-woocommerce' ); ?></p>
                    </div>
                </div>
                <button type="button" class="cqfw-chat-close cqfw-s6-close-btn" aria-label="<?php echo esc_attr__( 'Close chat', 'chat-quote-for-woocommerce' ); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <?php elseif ( $is_style_5 ) : ?>
            <div class="cqfw-multi-agent-header cqfw-s5-header">
                <div class="cqfw-s5-header-inner">
                    <div class="cqfw-s5-bot-bubble">
                        <svg width="42" height="42" viewBox="0 0 44 44" fill="none">
                            <path d="M22 6C13.1634 6 6 12.7157 6 21C6 24.4124 7.21808 27.5615 9.30058 30.1009L7.54452 35.808C7.34861 36.4447 7.95529 37.0142 8.58356 36.7944L14.6295 34.6793C16.8524 35.5348 19.3496 36 22 36C30.8366 36 38 29.2843 38 21C38 12.7157 30.8366 6 22 6Z" fill="#FFFFFF"/>
                            <rect x="15" y="15" width="14" height="11" rx="3.5" fill="#7c3aed"/>
                            <circle cx="18.5" cy="20" r="1.3" fill="#FFFFFF"/>
                            <circle cx="25.5" cy="20" r="1.3" fill="#FFFFFF"/>
                            <line x1="22" y1="12" x2="22" y2="15" stroke="#7c3aed" stroke-width="1.8" stroke-linecap="round"/>
                            <circle cx="22" cy="11.5" r="1.3" fill="#7c3aed"/>
                            <rect x="13.2" y="18.5" width="1.8" height="4" rx="0.9" fill="#7c3aed"/>
                            <rect x="29" y="18.5" width="1.8" height="4" rx="0.9" fill="#7c3aed"/>
                            <path d="M19.5 23.2C20.2 24 23.8 24 24.5 23.2" stroke="#FFFFFF" stroke-width="1.2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="cqfw-s5-header-text">
                        <h3 class="cqfw-s5-title"><?php echo ! empty( $settings['chat_popup_title'] ) ? esc_html( $settings['chat_popup_title'] ) : esc_html__( 'Chat with us', 'chat-quote-for-woocommerce' ); ?></h3>
                        <p class="cqfw-s5-subtitle"><?php echo ! empty( $settings['chat_popup_subtitle'] ) ? esc_html( $settings['chat_popup_subtitle'] ) : esc_html__( 'Any questions related to Multi Agent?', 'chat-quote-for-woocommerce' ); ?></p>
                    </div>
                </div>
                <button type="button" class="cqfw-chat-close cqfw-s5-close-btn" aria-label="<?php echo esc_attr__( 'Close chat', 'chat-quote-for-woocommerce' ); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <?php elseif ( $is_style_4 ) : ?>
            <div class="cqfw-multi-agent-header cqfw-s4-header">
                <div class="cqfw-s4-header-inner">
                    <div class="cqfw-s4-bot-wrap">
                        <span class="cqfw-s4-sparkles" aria-hidden="true">&#92; /</span>
                        <img src="<?php echo esc_url( CQFW_URL . 'assets/images/style-4-bot.png' ); ?>" alt="Bot" class="cqfw-s4-bot-img" />
                    </div>
                    <div class="cqfw-s4-header-text">
                        <h3 class="cqfw-s4-title"><?php echo ! empty( $settings['chat_popup_title'] ) ? esc_html( $settings['chat_popup_title'] ) : esc_html__( 'Chat with us', 'chat-quote-for-woocommerce' ); ?></h3>
                        <p class="cqfw-s4-subtitle"><?php echo ! empty( $settings['chat_popup_subtitle'] ) ? esc_html( $settings['chat_popup_subtitle'] ) : esc_html__( 'Any questions related to Multi Agent?', 'chat-quote-for-woocommerce' ); ?></p>
                    </div>
                </div>
                <button type="button" class="cqfw-chat-close cqfw-s4-close-btn" aria-label="<?php echo esc_attr__( 'Close chat', 'chat-quote-for-woocommerce' ); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <?php elseif ( $is_style_3 ) : ?>
            <div class="cqfw-multi-agent-header cqfw-s3-header">
                <div class="cqfw-s3-top-bar">
                    <div class="cqfw-s3-status">
                        <span class="cqfw-s3-status-dot"></span>
                        <span><?php esc_html_e( "We're online", 'chat-quote-for-woocommerce' ); ?></span>
                    </div>
                    <button type="button" class="cqfw-chat-close cqfw-s3-close-btn" aria-label="<?php echo esc_attr__( 'Close chat', 'chat-quote-for-woocommerce' ); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <h3 class="cqfw-s3-title"><?php echo ! empty( $settings['chat_popup_title'] ) ? esc_html( $settings['chat_popup_title'] ) : esc_html__( 'Chat with us', 'chat-quote-for-woocommerce' ); ?></h3>
                <p class="cqfw-s3-subtitle"><?php echo ! empty( $settings['chat_popup_subtitle'] ) ? esc_html( $settings['chat_popup_subtitle'] ) : esc_html__( 'Choose a team member to start a conversation', 'chat-quote-for-woocommerce' ); ?></p>
            </div>
            <?php else : ?>
            <div class="cqfw-multi-agent-header">
                <div class="cqfw-multi-agent-header-left">
                    <div class="cqfw-multi-agent-header-icon" aria-hidden="true">
                        <?php if ( ! $is_modern_card ) : ?>
                            <!-- Official WhatsApp Logo for Style 2 (Classic WhatsApp) -->
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="#FFFFFF">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
                            </svg>
                        <?php else : ?>
                            <!-- Modern Speech Bubble for Style 1 (Modern Card Style) -->
                            <svg width="42" height="42" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22 6C13.1634 6 6 12.7157 6 21C6 24.4124 7.21808 27.5615 9.30058 30.1009L7.54452 35.808C7.34861 36.4447 7.95529 37.0142 8.58356 36.7944L14.6295 34.6793C16.8524 35.5348 19.3496 36 22 36C30.8366 36 38 29.2843 38 21C38 12.7157 30.8366 6 22 6Z" fill="#FFFFFF"/>
                                <circle cx="15" cy="21" r="2.3" fill="#00a884"/>
                                <circle cx="22" cy="21" r="2.3" fill="#00a884"/>
                                <circle cx="29" cy="21" r="2.3" fill="#00a884"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="cqfw-multi-agent-titles">
                        <h3 class="cqfw-multi-agent-title">
                            <?php 
                            if ( ! empty( $settings['chat_popup_title'] ) ) {
                                echo esc_html( $settings['chat_popup_title'] );
                            } else {
                                echo ( $is_modern_card ) ? esc_html__( 'Chat with us', 'chat-quote-for-woocommerce' ) : esc_html__( 'WhatsApp Chat', 'chat-quote-for-woocommerce' );
                            }
                            ?>
                        </h3>
                        <p class="cqfw-multi-agent-subtitle">
                            <?php 
                            if ( $is_modern_card ) {
                                esc_html_e( 'Any questions related to Multi Agent?', 'chat-quote-for-woocommerce' );
                            } else {
                                esc_html_e( 'Typically replies fast', 'chat-quote-for-woocommerce' );
                            }
                            ?>
                        </p>
                    </div>
                </div>
                <button type="button" class="cqfw-chat-close" aria-label="<?php echo esc_attr__( 'Close chat', 'chat-quote-for-woocommerce' ); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <?php endif; ?>

            <?php if ( ! $is_open ) : ?>
            <div class="cqfw-offline-banner">
                <span>⏰ <?php esc_html_e( 'We are currently offline. Working hours resume soon.', 'chat-quote-for-woocommerce' ); ?></span>
            </div>
            <?php endif; ?>

            <div class="cqfw-multi-agent-cards">
                <?php if ( $is_style_6 ) : 
                    // Style 6: Frosted Glass & Online Badges (100% screenshot match)
                    $s6_defs = array(
                        0 => array( 'name' => 'Sales Support', 'role' => 'Sales Inquiry', 'gradient' => 'linear-gradient(135deg, #059669 0%, #10b981 100%)', 'phone' => '' ),
                        1 => array( 'name' => 'Support', 'role' => 'Support', 'gradient' => 'linear-gradient(135deg, #0284c7 0%, #38bdf8 100%)', 'phone' => '' ),
                        2 => array( 'name' => 'Office', 'role' => 'office', 'gradient' => 'linear-gradient(135deg, #7c3aed 0%, #a855f7 100%)', 'phone' => '' ),
                    );

                    $render_list = array();
                    if ( ! empty( $agents ) && is_array( $agents ) ) {
                        foreach ( $agents as $a_idx => $a_item ) {
                            $def_item = isset( $s6_defs[ $a_idx ] ) ? $s6_defs[ $a_idx ] : $s6_defs[ $a_idx % 3 ];
                            $a_name = ! empty( $a_item['name'] ) ? $a_item['name'] : $def_item['name'];
                            $render_list[] = array(
                                'name'     => $a_name,
                                'role'     => ! empty( $a_item['role'] ) ? $a_item['role'] : ( ! empty( $a_item['department'] ) ? $a_item['department'] : $def_item['role'] ),
                                'gradient' => $def_item['gradient'],
                                'avatar'   => ! empty( $a_item['avatar'] ) ? $a_item['avatar'] : '',
                                'phone'    => ! empty( $a_item['phone'] ) ? preg_replace( '/\D/', '', $a_item['phone'] ) : '',
                                'raw'      => $a_item,
                            );
                        }
                    } else {
                        $render_list = $s6_defs;
                    }

                    foreach ( $render_list as $s6_i => $s6_agent ) :
                        $ag_phone = ! empty( $s6_agent['phone'] ) ? $s6_agent['phone'] : ( ! empty( $phone ) ? $phone : '' );
                        $is_online = true;
                        if ( ! empty( $s6_agent['raw'] ) && class_exists( 'CQFW_Pro_Business_Hours' ) ) {
                            $is_online = CQFW_Pro_Business_Hours::is_agent_online( $s6_agent['raw'] );
                        }
                ?>
                    <div class="cqfw-s6-card cqfw-agent-card <?php echo $is_online ? 'is-online' : 'is-offline'; ?>" 
                         data-phone="<?php echo esc_attr( $ag_phone ); ?>" 
                         data-name="<?php echo esc_attr( $s6_agent['name'] ); ?>" 
                         data-online="<?php echo $is_online ? '1' : '0'; ?>" 
                         role="button" 
                         tabindex="<?php echo $is_online ? '0' : '-1'; ?>">
                        <div class="cqfw-s6-card-avatar" style="background: <?php echo esc_attr( $s6_agent['gradient'] ); ?>;">
                            <?php if ( ! empty( $s6_agent['avatar'] ) ) : ?>
                                <img src="<?php echo esc_url( $s6_agent['avatar'] ); ?>" alt="" />
                            <?php else : ?>
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                                    <path d="M4.5 13.5v-2.5a7.5 7.5 0 0 1 15 0v2.5" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round"/>
                                    <rect x="3" y="12" width="3" height="4.5" rx="1.5" fill="#ffffff"/>
                                    <rect x="18" y="12" width="3" height="4.5" rx="1.5" fill="#ffffff"/>
                                    <circle cx="12" cy="10.5" r="3.2" fill="#ffffff"/>
                                    <path d="M6.5 20a5.5 5.5 0 0 1 11 0" fill="#ffffff"/>
                                </svg>
                            <?php endif; ?>
                            <span class="cqfw-s6-dot <?php echo $is_online ? 'is-online' : 'is-offline'; ?>"></span>
                        </div>
                        <div class="cqfw-s6-card-content">
                            <h4 class="cqfw-s6-agent-name"><?php echo esc_html( $s6_agent['name'] ); ?></h4>
                            <p class="cqfw-s6-agent-role"><?php echo esc_html( $s6_agent['role'] ); ?></p>
                            <span class="cqfw-s6-badge-pill <?php echo $is_online ? 'is-online' : 'is-offline'; ?>"><?php echo $is_online ? esc_html__( 'Online', 'chat-quote-for-woocommerce' ) : esc_html__( 'Offline', 'chat-quote-for-woocommerce' ); ?></span>
                        </div>
                        <div class="cqfw-s6-actions" aria-hidden="true">
                            <span class="cqfw-s6-wa-wrap">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="#10b981">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
                                </svg>
                            </span>
                            <span class="cqfw-s6-chevron">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php elseif ( $is_style_5 ) : 
                    // Style 5: Cyberpunk Dark Theme (100% screenshot match)
                    $s5_defs = array(
                        0 => array( 'name' => 'Sales Support', 'role' => 'Sales Inquiry', 'initial' => 'S', 'color' => '#10b981', 'phone' => '' ),
                        1 => array( 'name' => 'Support', 'role' => 'Support', 'initial' => 'S', 'color' => '#0284c7', 'phone' => '' ),
                        2 => array( 'name' => 'office', 'role' => 'office', 'initial' => 'O', 'color' => '#a855f7', 'phone' => '' ),
                    );

                    $render_list = array();
                    if ( ! empty( $agents ) && is_array( $agents ) ) {
                        foreach ( $agents as $a_idx => $a_item ) {
                            $def_item = isset( $s5_defs[ $a_idx ] ) ? $s5_defs[ $a_idx ] : $s5_defs[ $a_idx % 3 ];
                            $a_name = ! empty( $a_item['name'] ) ? $a_item['name'] : $def_item['name'];
                            $render_list[] = array(
                                'name'    => $a_name,
                                'role'    => ! empty( $a_item['role'] ) ? $a_item['role'] : ( ! empty( $a_item['department'] ) ? $a_item['department'] : $def_item['role'] ),
                                'initial' => mb_strtoupper( mb_substr( $a_name, 0, 1 ) ),
                                'color'   => $def_item['color'],
                                'avatar'  => ! empty( $a_item['avatar'] ) ? $a_item['avatar'] : '',
                                'phone'   => ! empty( $a_item['phone'] ) ? preg_replace( '/\D/', '', $a_item['phone'] ) : '',
                                'raw'     => $a_item,
                            );
                        }
                    } else {
                        $render_list = $s5_defs;
                    }

                    foreach ( $render_list as $s5_i => $s5_agent ) :
                        $ag_phone = ! empty( $s5_agent['phone'] ) ? $s5_agent['phone'] : ( ! empty( $phone ) ? $phone : '' );
                        $is_online = true;
                        if ( ! empty( $s5_agent['raw'] ) && class_exists( 'CQFW_Pro_Business_Hours' ) ) {
                            $is_online = CQFW_Pro_Business_Hours::is_agent_online( $s5_agent['raw'] );
                        }
                ?>
                    <div class="cqfw-s5-card cqfw-agent-card <?php echo $is_online ? 'is-online' : 'is-offline'; ?>" 
                         data-phone="<?php echo esc_attr( $ag_phone ); ?>" 
                         data-name="<?php echo esc_attr( $s5_agent['name'] ); ?>" 
                         data-online="<?php echo $is_online ? '1' : '0'; ?>" 
                         role="button" 
                         tabindex="<?php echo $is_online ? '0' : '-1'; ?>">
                        <span class="cqfw-s5-card-accent" style="background: <?php echo esc_attr( $s5_agent['color'] ); ?>;"></span>
                        <div class="cqfw-s5-card-avatar" style="background: <?php echo esc_attr( $s5_agent['color'] ); ?>;">
                            <?php if ( ! empty( $s5_agent['avatar'] ) ) : ?>
                                <img src="<?php echo esc_url( $s5_agent['avatar'] ); ?>" alt="" />
                            <?php else : ?>
                                <span><?php echo esc_html( $s5_agent['initial'] ); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="cqfw-s5-card-content">
                            <h4 class="cqfw-s5-agent-name"><?php echo esc_html( $s5_agent['name'] ); ?></h4>
                            <p class="cqfw-s5-agent-role"><?php echo esc_html( $s5_agent['role'] ); ?></p>
                        </div>
                        <div class="cqfw-s5-wa-action" aria-hidden="true">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="#22c55e">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
                            </svg>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php elseif ( $is_style_4 ) : 
                    // Style 4: Pastel Bot Mascot & Department Cards (RESTORED)
                    $s4_defs = array(
                        0 => array( 'name' => 'Sales Support', 'role' => 'Sales Inquiry', 'type' => 'sales', 'color' => '#10b981', 'chevron_bg' => '#dcfce7', 'chevron_color' => '#10b981', 'phone' => '' ),
                        1 => array( 'name' => 'Support', 'role' => 'Support', 'type' => 'support', 'color' => '#0284c7', 'chevron_bg' => '#e0f2fe', 'chevron_color' => '#0284c7', 'phone' => '' ),
                        2 => array( 'name' => 'Office', 'role' => 'office', 'type' => 'office', 'color' => '#a855f7', 'chevron_bg' => '#f3e8ff', 'chevron_color' => '#a855f7', 'phone' => '' ),
                    );

                    $render_list = array();
                    if ( ! empty( $agents ) && is_array( $agents ) ) {
                        foreach ( $agents as $a_idx => $a_item ) {
                            $def_item = isset( $s4_defs[ $a_idx ] ) ? $s4_defs[ $a_idx ] : $s4_defs[ $a_idx % 3 ];
                            $render_list[] = array(
                                'name'          => ! empty( $a_item['name'] ) ? $a_item['name'] : $def_item['name'],
                                'role'          => ! empty( $a_item['role'] ) ? $a_item['role'] : ( ! empty( $a_item['department'] ) ? $a_item['department'] : $def_item['role'] ),
                                'type'          => $def_item['type'],
                                'color'         => $def_item['color'],
                                'chevron_bg'    => $def_item['chevron_bg'],
                                'chevron_color' => $def_item['chevron_color'],
                                'avatar'        => ! empty( $a_item['avatar'] ) ? $a_item['avatar'] : '',
                                'phone'         => ! empty( $a_item['phone'] ) ? preg_replace( '/\D/', '', $a_item['phone'] ) : '',
                                'raw'           => $a_item,
                            );
                        }
                    } else {
                        $render_list = $s4_defs;
                    }

                    foreach ( $render_list as $s4_i => $s4_agent ) :
                        $ag_phone = ! empty( $s4_agent['phone'] ) ? $s4_agent['phone'] : ( ! empty( $phone ) ? $phone : '' );
                        $is_online = true;
                        if ( ! empty( $s4_agent['raw'] ) && class_exists( 'CQFW_Pro_Business_Hours' ) ) {
                            $is_online = CQFW_Pro_Business_Hours::is_agent_online( $s4_agent['raw'] );
                        }
                ?>
                    <div class="cqfw-s4-card s4-<?php echo esc_attr( $s4_agent['type'] ); ?> cqfw-agent-card <?php echo $is_online ? 'is-online' : 'is-offline'; ?>" 
                         data-phone="<?php echo esc_attr( $ag_phone ); ?>" 
                         data-name="<?php echo esc_attr( $s4_agent['name'] ); ?>" 
                         data-online="<?php echo $is_online ? '1' : '0'; ?>" 
                         role="button" 
                         tabindex="<?php echo $is_online ? '0' : '-1'; ?>">
                        <div class="cqfw-s4-icon-wrap <?php echo esc_attr( $s4_agent['type'] ); ?>">
                            <?php if ( ! empty( $s4_agent['avatar'] ) ) : ?>
                                <img src="<?php echo esc_url( $s4_agent['avatar'] ); ?>" alt="" style="width:46px;height:46px;border-radius:50%;object-fit:cover;" />
                            <?php elseif ( 'office' === $s4_agent['type'] ) : ?>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="#ffffff">
                                    <path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16h4v2H2v-2h2zm2-2h8V5H6v14zm2-12h4v2H8V7zm0 4h4v2H8v-2zm0 4h4v2H8v-2zm10 0h2v4h-2v-4zm0-4h2v2h-2v-2z"/>
                                </svg>
                            <?php else : ?>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                                    <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                                    <path d="M8 21v-1a4 4 0 0 1 4-4h2"></path>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="cqfw-s4-card-content">
                            <h4 class="cqfw-s4-agent-name"><?php echo esc_html( $s4_agent['name'] ); ?></h4>
                            <p class="cqfw-s4-agent-role"><?php echo esc_html( $s4_agent['role'] ); ?></p>
                        </div>
                        <div class="cqfw-s4-chevron-btn" style="background: <?php echo esc_attr( $s4_agent['chevron_bg'] ); ?>; color: <?php echo esc_attr( $s4_agent['chevron_color'] ); ?>;" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php elseif ( $is_style_3 ) : 
                    // Style 3 100% replica of reference image
                    $s3_defs = array(
                        0 => array( 'name' => 'Sales Support', 'dept' => 'Sales', 'role' => 'Product & Pricing', 'badge' => 'sales', 'img' => CQFW_URL . 'assets/images/style-3-agent-1.png', 'phone' => '' ),
                        1 => array( 'name' => 'Technical Support', 'dept' => 'Support', 'role' => 'Setup & Troubleshooting', 'badge' => 'support', 'img' => CQFW_URL . 'assets/images/style-3-agent-2.png', 'phone' => '' ),
                        2 => array( 'name' => 'General Inquiry', 'dept' => 'Office', 'role' => 'Orders & Other', 'badge' => 'office', 'img' => CQFW_URL . 'assets/images/style-3-agent-3.png', 'phone' => '' ),
                    );

                    $render_list = array();
                    if ( ! empty( $agents ) && is_array( $agents ) ) {
                        foreach ( $agents as $a_idx => $a_item ) {
                            $def_item = isset( $s3_defs[ $a_idx ] ) ? $s3_defs[ $a_idx ] : $s3_defs[ $a_idx % 3 ];
                            $render_list[] = array(
                                'name'    => ! empty( $a_item['name'] ) ? $a_item['name'] : $def_item['name'],
                                'dept'    => ! empty( $a_item['department'] ) ? $a_item['department'] : $def_item['dept'],
                                'role'    => ! empty( $a_item['role'] ) ? $a_item['role'] : $def_item['role'],
                                'badge'   => ( $a_idx === 0 ) ? 'sales' : ( ( $a_idx === 1 ) ? 'support' : 'office' ),
                                'img'     => ! empty( $a_item['avatar'] ) ? $a_item['avatar'] : $def_item['img'],
                                'phone'   => ! empty( $a_item['phone'] ) ? preg_replace( '/\D/', '', $a_item['phone'] ) : '',
                                'raw'     => $a_item,
                            );
                        }
                    } else {
                        $render_list = $s3_defs;
                    }

                    foreach ( $render_list as $s3_i => $s3_agent ) :
                        $ag_phone = ! empty( $s3_agent['phone'] ) ? $s3_agent['phone'] : ( ! empty( $phone ) ? $phone : '' );
                        $is_online = true;
                        if ( ! empty( $s3_agent['raw'] ) && class_exists( 'CQFW_Pro_Business_Hours' ) ) {
                            $is_online = CQFW_Pro_Business_Hours::is_agent_online( $s3_agent['raw'] );
                        }
                ?>
                    <div class="cqfw-s3-card cqfw-agent-card <?php echo $is_online ? 'is-online' : 'is-offline'; ?>" 
                         data-phone="<?php echo esc_attr( $ag_phone ); ?>" 
                         data-name="<?php echo esc_attr( $s3_agent['name'] ); ?>" 
                         data-online="<?php echo $is_online ? '1' : '0'; ?>" 
                         role="button" 
                         tabindex="<?php echo $is_online ? '0' : '-1'; ?>">
                        <div class="cqfw-s3-card-avatar">
                            <img src="<?php echo esc_url( $s3_agent['img'] ); ?>" alt="<?php echo esc_attr( $s3_agent['name'] ); ?>" />
                            <span class="cqfw-s3-dot"></span>
                        </div>
                        <div class="cqfw-s3-card-content">
                            <span class="cqfw-s3-badge cqfw-s3-badge--<?php echo esc_attr( $s3_agent['badge'] ); ?>"><?php echo esc_html( $s3_agent['dept'] ); ?></span>
                            <h4 class="cqfw-s3-agent-name"><?php echo esc_html( $s3_agent['name'] ); ?></h4>
                            <p class="cqfw-s3-agent-role"><?php echo esc_html( $s3_agent['role'] ); ?></p>
                        </div>
                        <div class="cqfw-s3-chevron-btn" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php else : ?>
                <?php foreach ( $agents as $idx => $agent ) :
                    /* translators: %d is the agent number, e.g. Agent 1, Agent 2 */
                    $agent_name   = ! empty( $agent['name'] ) ? $agent['name'] : sprintf( __( 'Agent %d', 'chat-quote-for-woocommerce' ), $idx + 1 );
                    $agent_phone  = ! empty( $agent['phone'] ) ? preg_replace( '/\D/', '', $agent['phone'] ) : '';
                    $agent_dept   = ! empty( $agent['department'] ) ? $agent['department'] : '';
                    $custom_avatar_key = 'agent_' . ( $idx + 1 ) . '_avatar';
                    $agent_avatar = ! empty( $agent['avatar'] ) ? $agent['avatar'] : ( ! empty( $style_cfg[ $custom_avatar_key ] ) ? $style_cfg[ $custom_avatar_key ] : '' );
                    $initial      = mb_strtoupper( mb_substr( $agent_name, 0, 1 ) );
                    
                    $is_online = class_exists( 'CQFW_Pro_Business_Hours' )
                        ? CQFW_Pro_Business_Hours::is_agent_online( $agent )
                        : true;
                    $is_featured = ( 0 === $idx );
                ?>
                <div class="cqfw-agent-card <?php echo $is_featured ? 'cqfw-agent-card--featured' : ''; ?> <?php echo $is_online ? 'is-online' : 'is-offline'; ?>" 
                     data-phone="<?php echo esc_attr( $agent_phone ); ?>" 
                     data-name="<?php echo esc_attr( $agent_name ); ?>" 
                     data-online="<?php echo $is_online ? '1' : '0'; ?>" 
                     role="button" 
                     tabindex="<?php echo $is_online ? '0' : '-1'; ?>" 
                     <?php echo ! $is_online ? 'aria-disabled="true" title="' . esc_attr__( 'This agent is currently offline', 'chat-quote-for-woocommerce' ) . '"' : ''; ?>>
                    
                    <div class="cqfw-agent-card__avatar-wrap">
                        <?php if ( ! empty( $agent_avatar ) ) : ?>
                            <img class="cqfw-agent-card__avatar-img" src="<?php echo esc_url( $agent_avatar ); ?>" alt="<?php echo esc_attr( $agent_name ); ?>" />
                        <?php else : ?>
                            <div class="cqfw-agent-card__avatar-initial" data-idx="<?php echo esc_attr( $idx % 5 ); ?>"><span><?php echo esc_html( $initial ); ?></span></div>
                        <?php endif; ?>
                        <span class="cqfw-agent-status-dot <?php echo $is_online ? 'is-online' : 'is-offline'; ?>" title="<?php echo $is_online ? esc_attr__( 'Online', 'chat-quote-for-woocommerce' ) : esc_attr__( 'Offline', 'chat-quote-for-woocommerce' ); ?>"></span>
                    </div>

                    <div class="cqfw-agent-card__content">
                        <span class="cqfw-agent-card__name"><?php echo esc_html( $agent_name ); ?></span>
                        <?php if ( ! empty( $agent_dept ) ) : ?>
                            <span class="cqfw-agent-card__dept"><?php echo esc_html( $agent_dept ); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="cqfw-agent-card__action">
                        <?php if ( $is_online ) : ?>
                            <svg class="cqfw-agent-card__wa-svg is-online" width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
                            </svg>
                        <?php else : ?>
                            <div class="cqfw-agent-card__offline-indicator">
                                <svg class="cqfw-agent-card__wa-svg is-offline" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
                                </svg>
                                <?php /* translators: %s is the agent's available start time, e.g. 09:00 AM */ ?>
                                <span><?php echo ! empty( $agent['start_time'] ) ? sprintf( esc_html__( 'At %s', 'chat-quote-for-woocommerce' ), esc_html( $agent['start_time'] ) ) : esc_html__( 'Offline', 'chat-quote-for-woocommerce' ); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="cqfw-multi-agent-footer">
                <?php if ( $is_style_6 ) : ?>
                <button type="button" class="cqfw-switch-to-webchat cqfw-s6-footer-pill">
                    <span class="cqfw-s6-footer-left">
                        <span class="cqfw-s6-footer-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="#ffffff">
                                <path d="M12 3C6.477 3 2 7.029 2 12c0 1.83.612 3.528 1.666 4.93L2.5 21l4.288-1.144C8.163 20.524 10.022 21 12 21c5.523 0 10-4.029 10-9s-4.477-9-10-9z"/>
                                <circle cx="8" cy="12" r="1.3" fill="#059669"/>
                                <circle cx="12" cy="12" r="1.3" fill="#059669"/>
                                <circle cx="16" cy="12" r="1.3" fill="#059669"/>
                            </svg>
                        </span>
                        <span class="cqfw-s6-footer-divider"></span>
                        <span class="cqfw-s6-footer-text"><?php esc_html_e( 'Or send message on website', 'chat-quote-for-woocommerce' ); ?></span>
                    </span>
                    <span class="cqfw-s6-footer-arrow" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </span>
                </button>
                <?php elseif ( $is_style_5 ) : ?>
                <button type="button" class="cqfw-switch-to-webchat cqfw-s5-footer-pill">
                    <span class="cqfw-s5-footer-left">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="#ffffff" stroke-width="1.8" fill="rgba(255,255,255,0.2)"/>
                            <circle cx="8" cy="12" r="1.3" fill="#FFFFFF"/>
                            <circle cx="12" cy="12" r="1.3" fill="#FFFFFF"/>
                            <circle cx="16" cy="12" r="1.3" fill="#FFFFFF"/>
                        </svg>
                        <span class="cqfw-s5-footer-text"><?php esc_html_e( 'Or send message on website', 'chat-quote-for-woocommerce' ); ?></span>
                    </span>
                    <span class="cqfw-s5-footer-arrow" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </span>
                </button>
                <?php elseif ( $is_style_4 ) : ?>
                <button type="button" class="cqfw-switch-to-webchat cqfw-s4-footer-pill">
                    <span class="cqfw-s4-footer-left">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="#0084ff">
                            <path d="M12 2C6.477 2 2 6.477 2 12c0 1.821.487 3.53 1.338 5L2 22l5.223-1.306C8.63 21.464 10.264 22 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/>
                            <circle cx="8" cy="12" r="1.3" fill="#FFFFFF"/>
                            <circle cx="12" cy="12" r="1.3" fill="#FFFFFF"/>
                            <circle cx="16" cy="12" r="1.3" fill="#FFFFFF"/>
                        </svg>
                        <span class="cqfw-s4-footer-text"><?php esc_html_e( 'Or send message on website', 'chat-quote-for-woocommerce' ); ?></span>
                    </span>
                    <span class="cqfw-s4-footer-arrow" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0084ff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </span>
                </button>
                <?php elseif ( $is_style_3 ) : ?>
                <button type="button" class="cqfw-switch-to-webchat cqfw-s3-footer-pill">
                    <span class="cqfw-s3-footer-left">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" fill="#00a884"/>
                            <circle cx="8" cy="12" r="1.3" fill="#FFFFFF"/>
                            <circle cx="12" cy="12" r="1.3" fill="#FFFFFF"/>
                            <circle cx="16" cy="12" r="1.3" fill="#FFFFFF"/>
                        </svg>
                        <span class="cqfw-s3-footer-text"><?php esc_html_e( 'Or send message on website', 'chat-quote-for-woocommerce' ); ?></span>
                    </span>
                    <span class="cqfw-s3-footer-arrow" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                </button>
                <?php else : ?>
                <button type="button" class="cqfw-switch-to-webchat cqfw-pill-footer-btn">
                    <span class="cqfw-pill-footer-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" fill="#00a884"/>
                            <circle cx="8" cy="12" r="1.3" fill="#FFFFFF"/>
                            <circle cx="12" cy="12" r="1.3" fill="#FFFFFF"/>
                            <circle cx="16" cy="12" r="1.3" fill="#FFFFFF"/>
                        </svg>
                    </span>
                    <span class="cqfw-pill-footer-text"><?php esc_html_e( 'Or send message on website', 'chat-quote-for-woocommerce' ); ?></span>
                    <span class="cqfw-pill-footer-arrow" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00a884" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </span>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Web Chat View -->
        <div class="cqfw-webchat-view" style="<?php echo $has_multi_agents ? 'display:none;' : ''; ?>">
            <div class="cqfw-chat-panel__header">
                <?php if ( $has_multi_agents ) : ?>
                <button type="button" class="cqfw-back-to-agents" title="<?php esc_attr_e( 'Back to agents', 'chat-quote-for-woocommerce' ); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </button>
                <?php endif; ?>
                <div class="cqfw-header-brand">
                    <div class="cqfw-header-avatar">
                        <?php if ( ! empty( $settings['widget_avatar_url'] ) ) : ?>
                            <img src="<?php echo esc_url( $settings['widget_avatar_url'] ); ?>" alt="Avatar" class="cqfw-avatar-img" style="width:100%; height:100%; border-radius:50%; object-fit:cover;" />
                        <?php else : ?>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        <?php endif; ?>
                        <span class="cqfw-online-dot" style="<?php echo ! $is_open ? 'background:#f59e0b;' : ''; ?>"></span>
                    </div>
                    <div class="cqfw-header-text">
                        <strong>
                            <?php echo esc_html( $title ); ?>
                        </strong>
                        <p style="<?php echo ! $is_open ? 'color:#fbbf24;' : ''; ?>"><?php echo $is_open ? esc_html__( 'online', 'chat-quote-for-woocommerce' ) : esc_html__( 'offline (outside hours)', 'chat-quote-for-woocommerce' ); ?></p>
                    </div>
                </div>
                <button type="button" class="cqfw-chat-close"
                    aria-label="<?php echo esc_attr__( 'Close chat', 'chat-quote-for-woocommerce' ); ?>">&times;</button>
            </div>

            <?php if ( ! $is_open && ! $has_multi_agents ) : ?>
            <div class="cqfw-offline-banner" style="background:#fffbeb; color:#92400e; padding:8px 14px; font-size:12px; display:flex; align-items:center; gap:6px; border-bottom:1px solid #fef3c7;">
                <span>⏰ <?php esc_html_e( 'We are currently offline. Leave a message and we will respond during business hours!', 'chat-quote-for-woocommerce' ); ?></span>
            </div>
            <?php endif; ?>

            <div class="cqfw-chat-panel__messages" role="log" aria-live="polite"></div>

            <!-- Typing indicator -->
            <div class="cqfw-typing-indicator" aria-hidden="true">
                <span class="cqfw-typing-dot"></span>
                <span class="cqfw-typing-dot"></span>
                <span class="cqfw-typing-dot"></span>
            </div>

            <div class="cqfw-chat-form-container">
                <form class="cqfw-chat-form" novalidate>
                    <div class="cqfw-form-inputs">
                        <label class="screen-reader-text"
                            for="cqfw-name"><?php echo esc_html__( 'Your name', 'chat-quote-for-woocommerce' ); ?></label>
                        <input type="text" id="cqfw-name" name="name"
                            placeholder="<?php echo esc_attr__( 'Your name', 'chat-quote-for-woocommerce' ); ?>"
                            autocomplete="name" />

                        <label class="screen-reader-text"
                            for="cqfw-phone"><?php echo esc_html__( 'Phone number', 'chat-quote-for-woocommerce' ); ?></label>
                        <input type="tel" id="cqfw-phone" name="phone"
                            placeholder="<?php echo esc_attr__( 'Phone number', 'chat-quote-for-woocommerce' ); ?>"
                            autocomplete="tel" />
                    </div>
                    
                    <!-- Attachment Preview Chip (PRO) -->
                    <?php if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && cqfw_can_use_pro() ) : ?>
                    <div class="cqfw-attachment-preview" style="display:none;">
                        <span class="cqfw-attachment-chip">
                            <span class="cqfw-attachment-icon">📎</span>
                            <span class="cqfw-attachment-name"></span>
                            <button type="button" class="cqfw-attachment-remove" title="<?php esc_attr_e( 'Remove attachment', 'chat-quote-for-woocommerce' ); ?>">&times;</button>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="cqfw-message-input-wrapper cqfw-pro-pill-input">
                        <?php if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && cqfw_can_use_pro() ) : ?>
                        <!-- Attachment Menu (+ Button) (PRO) -->
                        <div class="cqfw-attach-dropdown">
                            <button type="button" class="cqfw-chat-attach-btn" title="<?php esc_attr_e( 'Attach file or screenshot', 'chat-quote-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Attach', 'chat-quote-for-woocommerce' ); ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            </button>
                            <div class="cqfw-attach-menu" hidden>
                                <button type="button" class="cqfw-attach-item cqfw-attach-file">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                                    <span><?php esc_html_e( 'Send a file', 'chat-quote-for-woocommerce' ); ?></span>
                                </button>
                                <button type="button" class="cqfw-attach-item cqfw-attach-screenshot">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                    <span><?php esc_html_e( 'Add screenshot', 'chat-quote-for-woocommerce' ); ?></span>
                                </button>
                            </div>
                            <input type="file" class="cqfw-hidden-file-input" style="display:none;" />
                        </div>
                        <?php endif; ?>

                        <!-- Message Textarea -->
                        <label class="screen-reader-text" for="cqfw-message"><?php echo esc_html__( 'Message', 'chat-quote-for-woocommerce' ); ?></label>
                        <textarea id="cqfw-message" name="message" rows="1" placeholder="<?php echo esc_attr__( 'Write a message...', 'chat-quote-for-woocommerce' ); ?>"></textarea>

                        <?php if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && cqfw_can_use_pro() ) : ?>
                        <!-- Emoji Reactions Picker (PRO) -->
                        <div class="cqfw-emoji-dropdown">
                            <button type="button" class="cqfw-chat-emoji-btn" title="<?php esc_attr_e( 'Insert emoji', 'chat-quote-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Emoji', 'chat-quote-for-woocommerce' ); ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
                            </button>
                            <div class="cqfw-emoji-picker" hidden>
                                <div class="cqfw-emoji-grid">
                                    <button type="button" class="cqfw-emoji-item" data-emoji="😊" title="Smile">😊</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="😁" title="Grin">😁</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="😂" title="Joy">😂</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="🥰" title="Love">🥰</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="😍" title="Heart Eyes">😍</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="😐" title="Neutral">😐</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="😟" title="Worried">😟</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="🥱" title="Yawn">🥱</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="😢" title="Cry">😢</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="😭" title="Sob">😭</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="🎉" title="Party">🎉</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="❤️" title="Heart">❤️</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="👌" title="OK">👌</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="👍" title="Thumbs Up">👍</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="👎" title="Thumbs Down">👎</button>
                                    <button type="button" class="cqfw-emoji-item" data-emoji="🙏" title="Pray">🙏</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Send Button (Upward Arrow) -->
                        <button type="submit" class="cqfw-chat-send" title="<?php echo esc_attr__( 'Send', 'chat-quote-for-woocommerce' ); ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="19" x2="12" y2="5"></line>
                                <polyline points="5 12 12 5 19 12"></polyline>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
		$share_on  = ! empty( $controls['enable_share'] );
		$group_on  = ! empty( $controls['enable_group_chat'] ) && ! empty( $controls['group_chat_url'] );
		$greet_bot = ! empty( $greeting['bottom'] ) ? $greeting['bottom'] : '';
		if ( $share_on || $group_on || $greet_bot ) :
			?>
    <div class="cqfw-widget-extras" hidden>
        <?php if ( $greet_bot ) : ?>
            <div class="cqfw-greeting-bottom"><?php echo esc_html( $greet_bot ); ?></div>
        <?php endif; ?>
        <?php if ( $share_on || $group_on ) : ?>
        <div class="cqfw-extra-actions">
            <?php if ( $share_on ) : ?>
                <button type="button" class="cqfw-extra-action cqfw-share-page" data-share-tpl="<?php echo esc_attr( ! empty( $controls['share_message'] ) ? $controls['share_message'] : 'Check this out: {url}' ); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92c0-1.61-1.31-2.92-2.92-2.92z"/></svg>
                    <?php esc_html_e( 'Share page', 'chat-quote-for-woocommerce' ); ?>
                </button>
            <?php endif; ?>
            <?php if ( $group_on ) : ?>
                <a class="cqfw-extra-action cqfw-group-chat" href="<?php echo esc_url( $controls['group_chat_url'] ); ?>" target="_blank" rel="noopener noreferrer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    <?php esc_html_e( 'Join group', 'chat-quote-for-woocommerce' ); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php
	}

	/**
	 * Get current product object.
	 *
	 * @return WC_Product|null
	 */
	private function get_current_product() {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product_id = get_queried_object_id();
		if ( ! $product_id ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		return $product ? $product : null;
	}

	/**
	 * Get product context for localizing scripts.
	 *
	 * @return array<string,mixed>
	 */
	private function get_product_context() {
		$product = $this->get_current_product();

		if ( ! $product ) {
			return array(
				'product_id'    => 0,
				'product_name'  => '',
				'product_price' => '',
				'product_sku'   => '',
				'product_url'   => '',
				'product_qty'   => 1,
				'product_image' => '',
			);
		}

		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

		return array(
			'product_id'    => absint( $product->get_id() ),
			'product_name'  => $product->get_name(),
			'product_price' => CQFW_Analytics::format_price_text( $product->get_price() ),
			'product_sku'   => $product->get_sku(),
			'product_url'   => get_permalink( $product->get_id() ),
			'product_qty'   => 1,
			'product_image' => $image_url ? esc_url( $image_url ) : '',
		);
	}

	/**
	 * Get current URL.
	 *
	 * @return string
	 */
	private function get_current_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$current_url = home_url( $request_uri );
		return esc_url_raw( $current_url );
	}

	/**
	 * Get page context.
	 *
	 * @return string
	 */
	private function get_page_context() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return 'product';
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return 'cart';
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return 'shop';
		}

		return 'page';
	}

	/**
	 * Build product WhatsApp URL.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	private function build_product_whatsapp_url( $product ) {
		$template = CQFW_Settings::get_setting( 'default_message_template', CQFW_Analytics::get_default_template() );
		$message  = str_replace(
			array( '{product_name}', '{product_price}', '{product_sku}', '{product_qty}', '{product_url}' ),
			array(
				$product->get_name(),
				CQFW_Analytics::format_price_text( $product->get_price() ),
				$product->get_sku(),
				'1',
				get_permalink( $product->get_id() ),
			),
			$template
		);

		return ( new CQFW_Analytics() )->build_whatsapp_url( $message );
	}

	/**
	 * Render the configured icon HTML.
	 *
	 * @return string
	 */
	private function render_button_icon( $context = 'default' ) {
		$icon_url = CQFW_Settings::get_button_icon_url( $context );
		if ( '' === $icon_url ) {
			return '<svg class="cqfw-button-svg-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg>';
		}

		return sprintf(
			'<span class="cqfw-button-icon" aria-hidden="true" style="background-image:url(%s)"></span>',
			esc_url( $icon_url )
		);
	}

	/**
	 * Render the button label, hiding it when an icon is configured.
	 *
	 * @param string $label Label text.
	 * @param string $context Button context.
	 * @return string
	 */
	private function render_button_label( $label, $context = 'default' ) {
		if ( '' !== CQFW_Settings::get_button_icon_url( $context ) ) {
			return '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
		}

		return '<span class="cqfw-floating-label">' . esc_html( $label ) . '</span>';
	}

}
