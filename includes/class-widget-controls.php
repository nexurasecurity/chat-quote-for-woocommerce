<?php
/**
 * Widget display rules, greetings, tracking & share (Free + Pro hooks).
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_Widget_Controls {

	public const OPTION = 'cqfw_widget_controls';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'cqfw_should_show_widget', array( $this, 'filter_should_show' ), 10 );
		add_filter( 'cqfw_widget_localize', array( $this, 'filter_localize' ), 10 );
		add_filter( 'cqfw_whatsapp_number', array( $this, 'filter_whatsapp_number' ), 10, 1 );
		add_filter( 'cqfw_floating_cta_text', array( $this, 'filter_floating_cta' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'print_tracking_bootstrap' ), 5 );
		add_action( 'wp_ajax_cqfw_webhook_ping', array( $this, 'ajax_webhook_ping' ) );
		add_action( 'wp_ajax_nopriv_cqfw_webhook_ping', array( $this, 'ajax_webhook_ping' ) );
	}

	/**
	 * Default controls.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			// Display (Free).
			'display_enabled'       => 0,
			'post_types'            => array( 'page', 'post', 'product' ),
			'page_ids_include'      => '',
			'page_ids_exclude'      => '',
			'category_ids'          => '',
			'show_desktop'          => 1,
			'show_mobile'           => 1,
			// Greetings (Free: 1 & 2).
			'greeting_enabled'      => 1,
			'greeting_template'     => '1',
			'greeting_header'       => __( 'Hi there! 👋', 'chat-quote-for-woocommerce' ),
			'greeting_main'         => __( 'How can we help you today?', 'chat-quote-for-woocommerce' ),
			'greeting_bottom'       => '',
			'greeting_2_header'     => __( 'Welcome!', 'chat-quote-for-woocommerce' ),
			'greeting_2_main'       => __( 'Ask us anything about this product or place an order on WhatsApp.', 'chat-quote-for-woocommerce' ),
			'greeting_2_bottom'     => __( 'We typically reply in a few minutes.', 'chat-quote-for-woocommerce' ),
			// Effects & share (Free).
			'entry_effect'          => 'fade',
			'enable_share'          => 1,
			'enable_group_chat'     => 0,
			'group_chat_url'        => '',
			'share_message'         => __( 'Check this out: {url}', 'chat-quote-for-woocommerce' ),
			// Tracking (Free basic).
			'ga_enabled'            => 0,
			'ga_event_name'         => 'cqfw_whatsapp_click',
			'fb_pixel_enabled'      => 0,
			'fb_pixel_event'        => 'Contact',
			'webhook_enabled'       => 0,
			'webhook_url'           => '',
			// Pro-filled via merge / Pro class.
			'time_delay'            => 0,
			'scroll_delay'          => 0,
			'click_trigger'         => '',
			'viewport_trigger'      => '',
			'login_status'          => 'any',
			'countries_include'     => '',
			'countries_exclude'     => '',
			'offline_number'        => '',
			'offline_cta'           => '',
			'random_numbers'        => '',
			'random_enabled'        => 0,
			'google_ads_enabled'    => 0,
			'google_ads_send_to'    => '',
			'tracking_advanced'     => 0,
			'woo_greeting_enabled'  => 0,
			'woo_greeting_header'   => '',
			'woo_greeting_main'     => '',
			'woo_greeting_bottom'   => '',
			'notification_badge'    => 1,
		);
	}

	/**
	 * Get merged controls.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Pro-only option keys (stripped / ignored on free WordPress.org builds).
	 *
	 * @return array<int,string>
	 */
	public static function pro_option_keys() {
		return array(
			'time_delay',
			'scroll_delay',
			'click_trigger',
			'viewport_trigger',
			'login_status',
			'countries_include',
			'countries_exclude',
			'offline_number',
			'offline_cta',
			'random_numbers',
			'random_enabled',
			'google_ads_enabled',
			'google_ads_send_to',
			'tracking_advanced',
			'woo_greeting_enabled',
			'woo_greeting_header',
			'woo_greeting_main',
			'woo_greeting_bottom',
			'notification_badge',
		);
	}

	/**
	 * Free checkbox keys present on every controls form.
	 *
	 * @return array<int,string>
	 */
	public static function free_checkbox_keys() {
		return array(
			'display_enabled',
			'show_desktop',
			'show_mobile',
			'greeting_enabled',
			'enable_share',
			'enable_group_chat',
			'ga_enabled',
			'fb_pixel_enabled',
			'webhook_enabled',
		);
	}

	/**
	 * Pro checkbox keys (only reset when Pro form was submitted).
	 *
	 * @return array<int,string>
	 */
	public static function pro_checkbox_keys() {
		return array(
			'random_enabled',
			'google_ads_enabled',
			'tracking_advanced',
			'woo_greeting_enabled',
			'notification_badge',
		);
	}

	/**
	 * Whether this request/build can apply Pro widget controls.
	 * Freemius strips is__premium_only() blocks from the free .org ZIP (Guideline 5).
	 *
	 * @return bool
	 */
	public static function can_use_pro_controls() {
		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) {
			return function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro();
		}
		return false;
	}

	/**
	 * Sanitize and save controls.
	 *
	 * @param array<string,mixed> $input      Raw input.
	 * @param bool                $allow_pro  Whether Pro fields were on the form.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input, $allow_pro = false ) {
		$defaults = self::defaults();
		$out      = self::get();

		if ( ! is_array( $input ) ) {
			return $out;
		}

		$allow_pro = $allow_pro && self::can_use_pro_controls();

		foreach ( self::free_checkbox_keys() as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
			}
		}

		$text_keys = array(
			'page_ids_include', 'page_ids_exclude', 'category_ids',
			'group_chat_url', 'share_message', 'ga_event_name', 'fb_pixel_event', 'webhook_url',
			'greeting_header', 'greeting_main', 'greeting_bottom',
			'greeting_2_header', 'greeting_2_main', 'greeting_2_bottom',
		);
		foreach ( $text_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
			}
		}

		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$out['post_types'] = array_values( array_map( 'sanitize_key', $input['post_types'] ) );
		} elseif ( isset( $input['post_types'] ) && is_string( $input['post_types'] ) ) {
			$parts             = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', $input['post_types'] ) ) ) );
			$out['post_types'] = array_values( $parts );
		}

		if ( isset( $input['greeting_template'] ) ) {
			$out['greeting_template'] = in_array( (string) $input['greeting_template'], array( '1', '2' ), true ) ? (string) $input['greeting_template'] : '1';
		}

		if ( isset( $input['entry_effect'] ) ) {
			$allowed             = array( 'none', 'fade', 'slide', 'bounce', 'zoom' );
			$out['entry_effect'] = in_array( $input['entry_effect'], $allowed, true ) ? $input['entry_effect'] : 'fade';
		}

		if ( isset( $input['webhook_url'] ) ) {
			$url = esc_url_raw( wp_unslash( $input['webhook_url'] ) );
			$out['webhook_url'] = self::is_safe_webhook_url( $url ) ? $url : '';
		}
		if ( isset( $input['group_chat_url'] ) ) {
			$out['group_chat_url'] = esc_url_raw( wp_unslash( $input['group_chat_url'] ) );
		}

		// Pro fields: only accept when premium build + licensed (WP.org Guideline 5 / Freemius).
		if ( $allow_pro ) {
			foreach ( self::pro_checkbox_keys() as $key ) {
				if ( array_key_exists( $key, $input ) ) {
					$out[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
				}
			}

			$pro_text = array(
				'click_trigger', 'viewport_trigger', 'countries_include', 'countries_exclude',
				'offline_number', 'offline_cta', 'random_numbers', 'google_ads_send_to',
				'woo_greeting_header', 'woo_greeting_main', 'woo_greeting_bottom',
			);
			foreach ( $pro_text as $key ) {
				if ( isset( $input[ $key ] ) ) {
					$out[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
				}
			}

			if ( isset( $input['login_status'] ) ) {
				$allowed             = array( 'any', 'logged_in', 'logged_out' );
				$out['login_status'] = in_array( $input['login_status'], $allowed, true ) ? $input['login_status'] : 'any';
			}
			if ( isset( $input['time_delay'] ) ) {
				$out['time_delay'] = max( 0, absint( $input['time_delay'] ) );
			}
			if ( isset( $input['scroll_delay'] ) ) {
				$out['scroll_delay'] = min( 100, max( 0, absint( $input['scroll_delay'] ) ) );
			}
		}

		return wp_parse_args( $out, $defaults );
	}

	/**
	 * Validate webhook URL (block private/local targets — SSRF hardening).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_safe_webhook_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		if ( ! function_exists( 'wp_http_validate_url' ) || ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}
		$host = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		if ( '' === $host ) {
			return false;
		}
		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1', '0.0.0.0' ), true ) ) {
			return false;
		}
		if ( preg_match( '/^(10\.|192\.168\.|169\.254\.|127\.)/', $host ) ) {
			return false;
		}
		if ( preg_match( '/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host ) ) {
			return false;
		}
		/**
		 * Filter whether a webhook URL is allowed.
		 *
		 * @param bool   $allowed Default.
		 * @param string $url     URL.
		 */
		return (bool) apply_filters( 'cqfw_is_safe_webhook_url', true, $url );
	}

	/**
	 * Whether widget should render on this request.
	 *
	 * @param bool $show Default.
	 * @return bool
	 */
	public function filter_should_show( $show ) {
		if ( ! $show ) {
			return false;
		}

		$c = self::get();

		// Device (Free) — server-side soft check; JS refines.
		if ( empty( $c['show_desktop'] ) && empty( $c['show_mobile'] ) ) {
			return false;
		}

		if ( empty( $c['display_enabled'] ) ) {
			return apply_filters( 'cqfw_pro_display_rules', true, $c );
		}

		$post_type = get_post_type();
		if ( $post_type && ! empty( $c['post_types'] ) && ! in_array( $post_type, (array) $c['post_types'], true ) ) {
			// Still allow shop/archives when product is selected.
			$on_shop = ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() );
			if ( ! ( $on_shop && in_array( 'product', (array) $c['post_types'], true ) ) ) {
				return false;
			}
		}

		$page_id = (int) get_queried_object_id();
		$include = self::parse_id_list( $c['page_ids_include'] );
		$exclude = self::parse_id_list( $c['page_ids_exclude'] );

		if ( ! empty( $include ) && $page_id && ! in_array( $page_id, $include, true ) ) {
			return false;
		}
		if ( ! empty( $exclude ) && $page_id && in_array( $page_id, $exclude, true ) ) {
			return false;
		}

		$cats = self::parse_id_list( $c['category_ids'] );
		if ( ! empty( $cats ) ) {
			$match = false;
			if ( function_exists( 'is_product' ) && is_product() ) {
				$terms = wp_get_post_terms( $page_id, 'product_cat', array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $terms ) && array_intersect( $cats, array_map( 'intval', $terms ) ) ) {
					$match = true;
				}
			} elseif ( is_category() || is_tax() ) {
				$obj = get_queried_object();
				if ( $obj && ! empty( $obj->term_id ) && in_array( (int) $obj->term_id, $cats, true ) ) {
					$match = true;
				}
			}
			if ( ! $match ) {
				return false;
			}
		}

		return apply_filters( 'cqfw_pro_display_rules', true, $c );
	}

	/**
	 * Parse comma/space separated IDs.
	 *
	 * @param string $raw Raw string.
	 * @return array<int>
	 */
	public static function parse_id_list( $raw ) {
		if ( empty( $raw ) ) {
			return array();
		}
		$parts = preg_split( '/[\s,]+/', (string) $raw );
		$ids   = array();
		foreach ( (array) $parts as $p ) {
			$n = absint( $p );
			if ( $n ) {
				$ids[] = $n;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Active greeting strings.
	 *
	 * @return array{header:string,main:string,bottom:string}
	 */
	public static function get_active_greeting() {
		$c = self::get();
		if ( empty( $c['greeting_enabled'] ) ) {
			return array(
				'header' => '',
				'main'   => CQFW_Settings::get_setting( 'welcome_message', __( 'Hi there! 👋 How can we help you today?', 'chat-quote-for-woocommerce' ) ),
				'bottom' => '',
			);
		}

		// Pro WooCommerce product greeting override (premium package only).
		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) {
			if ( self::can_use_pro_controls() && ! empty( $c['woo_greeting_enabled'] ) && function_exists( 'is_product' ) && is_product() ) {
				return array(
					'header' => $c['woo_greeting_header'],
					'main'   => $c['woo_greeting_main'],
					'bottom' => $c['woo_greeting_bottom'],
				);
			}
		}

		if ( '2' === (string) $c['greeting_template'] ) {
			return array(
				'header' => $c['greeting_2_header'],
				'main'   => $c['greeting_2_main'],
				'bottom' => $c['greeting_2_bottom'],
			);
		}

		return array(
			'header' => $c['greeting_header'],
			'main'   => $c['greeting_main'],
			'bottom' => $c['greeting_bottom'],
		);
	}

	/**
	 * Localize data for frontend.
	 *
	 * @param array<string,mixed> $data Existing.
	 * @return array<string,mixed>
	 */
	public function filter_localize( $data ) {
		$c        = self::get();
		$greeting = self::get_active_greeting();

		$welcome = trim( $greeting['header'] . ' ' . $greeting['main'] );
		if ( $welcome ) {
			$data['welcomeMessage'] = $welcome;
		}

		$controls = array(
			'showDesktop'       => ! empty( $c['show_desktop'] ),
			'showMobile'        => ! empty( $c['show_mobile'] ),
			'entryEffect'       => $c['entry_effect'],
			'enableShare'       => ! empty( $c['enable_share'] ),
			'enableGroupChat'   => ! empty( $c['enable_group_chat'] ),
			'groupChatUrl'      => $c['group_chat_url'],
			'shareMessage'      => $c['share_message'],
			'greetingBottom'    => $greeting['bottom'],
			'notificationBadge' => true,
			'timeDelay'         => 0,
			'scrollDelay'       => 0,
			'clickTrigger'      => '',
			'viewportTrigger'   => '',
			'gaEnabled'         => ! empty( $c['ga_enabled'] ),
			'gaEventName'       => $c['ga_event_name'],
			'fbEnabled'         => ! empty( $c['fb_pixel_enabled'] ),
			'fbEvent'           => $c['fb_pixel_event'],
			'webhookEnabled'    => ! empty( $c['webhook_enabled'] ),
			'googleAdsEnabled'  => false,
			'googleAdsSendTo'   => '',
			'trackingAdvanced'  => false,
			'isPro'             => false,
		);

		// Premium-only runtime values (stripped from WordPress.org free ZIP).
		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) {
			$is_pro = self::can_use_pro_controls();
			$controls['isPro'] = $is_pro;
			if ( $is_pro ) {
				$controls['notificationBadge'] = ! empty( $c['notification_badge'] );
				$controls['timeDelay']         = absint( $c['time_delay'] );
				$controls['scrollDelay']       = absint( $c['scroll_delay'] );
				$controls['clickTrigger']      = $c['click_trigger'];
				$controls['viewportTrigger']   = $c['viewport_trigger'];
				$controls['googleAdsEnabled']  = ! empty( $c['google_ads_enabled'] );
				$controls['googleAdsSendTo']   = $c['google_ads_send_to'];
				$controls['trackingAdvanced']  = ! empty( $c['tracking_advanced'] );
			}
		}

		$data['controls'] = $controls;

		return $data;
	}

	/**
	 * Random / offline number selection.
	 *
	 * @param string $number Default number.
	 * @return string
	 */
	public function filter_whatsapp_number( $number ) {
		// Offline / random routing lives only in the premium package.
		if ( ! function_exists( 'cqfw_fs' ) || ! cqfw_fs()->is__premium_only() ) {
			return $number;
		}
		if ( ! self::can_use_pro_controls() ) {
			return $number;
		}

		$c    = self::get();
		$open = apply_filters( 'cqfw_is_business_open', true );

		if ( ! $open && ! empty( $c['offline_number'] ) ) {
			return preg_replace( '/[^0-9+]/', '', $c['offline_number'] );
		}

		if ( ! empty( $c['random_enabled'] ) && ! empty( $c['random_numbers'] ) ) {
			$list = preg_split( '/[\s,;]+/', $c['random_numbers'] );
			$list = array_values(
				array_filter(
					array_map(
						function ( $n ) {
							return preg_replace( '/[^0-9+]/', '', $n );
						},
						(array) $list
					)
				)
			);
			if ( ! empty( $list ) ) {
				return $list[ array_rand( $list ) ];
			}
		}

		return $number;
	}

	/**
	 * Offline CTA text.
	 *
	 * @param string $text   Default CTA.
	 * @param bool   $is_open Business open.
	 * @return string
	 */
	public function filter_floating_cta( $text, $is_open = true ) {
		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) {
			$c = self::get();
			if ( self::can_use_pro_controls() && ! $is_open && ! empty( $c['offline_cta'] ) ) {
				return $c['offline_cta'];
			}
		}
		return $text;
	}

	/**
	 * Detect visitor country (best-effort).
	 *
	 * @return string ISO country code or empty.
	 */
	public static function detect_country() {
		$headers = array( 'HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_APPENGINE_COUNTRY', 'GEOIP_COUNTRY_CODE' );
		foreach ( $headers as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$code = strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) );
				if ( 'XX' !== $code && 2 === strlen( $code ) ) {
					return $code;
				}
			}
		}
		return (string) apply_filters( 'cqfw_visitor_country', '' );
	}

	/**
	 * Lightweight tracking bootstrap (events fired from JS).
	 *
	 * @return void
	 */
	public function print_tracking_bootstrap() {
		$c = self::get();
		if ( empty( $c['ga_enabled'] ) && empty( $c['fb_pixel_enabled'] ) && empty( $c['google_ads_enabled'] ) ) {
			return;
		}
		// Handled via cqfwChat.controls in chat-widget.js — no extra markup needed.
	}

	/**
	 * Fire webhook on click (AJAX).
	 *
	 * @return void
	 */
	public function ajax_webhook_ping() {
		check_ajax_referer( 'cqfw_nonce', 'nonce' );

		$c = self::get();
		if ( empty( $c['webhook_enabled'] ) || empty( $c['webhook_url'] ) || ! self::is_safe_webhook_url( $c['webhook_url'] ) ) {
			wp_send_json_success( array( 'skipped' => true ) );
		}

		// Simple rate limit (Guideline 7 / abuse protection).
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'cqfw_wh_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 30 ) {
			wp_send_json_error( array( 'message' => 'rate_limited' ), 429 );
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );

		$payload = array(
			'event'      => 'whatsapp_click',
			'timestamp'  => gmdate( 'c' ),
			'site'       => home_url(),
			'page_url'   => isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '',
			'product_id' => isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0,
			'page_type'  => isset( $_POST['page_type'] ) ? sanitize_text_field( wp_unslash( $_POST['page_type'] ) ) : '',
		);

		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) {
			if ( self::can_use_pro_controls() && ! empty( $c['tracking_advanced'] ) ) {
				$payload['user_logged_in'] = is_user_logged_in();
				$payload['country']        = self::detect_country();
				$payload['user_agent']     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			}
		}

		wp_remote_post(
			$c['webhook_url'],
			array(
				'timeout'   => 8,
				'blocking'  => false,
				'headers'   => array( 'Content-Type' => 'application/json' ),
				'body'      => wp_json_encode( $payload ),
				'sslverify' => true,
			)
		);

		wp_send_json_success( array( 'sent' => true ) );
	}

	/**
	 * Admin panels — Click to Chat style: Global (Free) vs Pro (premium package only).
	 *
	 * @param string $scope 'global' = Free settings once on Floating Chat; 'pro' = Pro-only once on Team & Hours.
	 * @return void
	 */
	public static function render_admin_panels( $scope = 'global' ) {
		$scope  = ( 'pro' === $scope ) ? 'pro' : 'global';
		$c      = self::get();
		$is_pro = false;

		if ( 'pro' === $scope ) {
			if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && self::can_use_pro_controls() ) {
				$is_pro = true;
			} else {
				echo '<div class="notice notice-info inline"><p>' . esc_html__( 'These Pro settings unlock with a valid premium license.', 'chat-quote-for-woocommerce' ) . '</p></div>';
				return;
			}
		}

		if ( 'pro' === $scope && $is_pro ) {
			self::render_pro_panels( $c );
			return;
		}

		self::render_global_panels( $c );
	}

	/**
	 * Global Free settings (single place: Floating Chat).
	 *
	 * @param array<string,mixed> $c Controls.
	 * @return void
	 */
	private static function render_global_panels( $c ) {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<div class="cqfw-controls-admin">
			<p class="cqfw-ctrl-help" style="margin-bottom:8px;">
				<?php esc_html_e( 'Global settings — apply site-wide. Pro options (delays, country, agents, offline routing) live under Team & Hours.', 'chat-quote-for-woocommerce' ); ?>
			</p>
			<div class="cqfw-ctrl-card">
				<h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Where to Show Chat', 'chat-quote-for-woocommerce' ); ?></h3>
				<p class="cqfw-ctrl-help"><?php esc_html_e( 'Control which pages and devices show the floating WhatsApp chat.', 'chat-quote-for-woocommerce' ); ?></p>
				<label class="cqfw-ctrl-check">
					<input type="checkbox" name="cqfw_widget_controls[display_enabled]" value="1" <?php checked( ! empty( $c['display_enabled'] ) ); ?> />
					<?php esc_html_e( 'Enable display rules (when off, chat shows everywhere)', 'chat-quote-for-woocommerce' ); ?>
				</label>
				<div class="cqfw-ctrl-grid">
					<div>
						<span class="cqfw-ctrl-label"><?php esc_html_e( 'Post types', 'chat-quote-for-woocommerce' ); ?></span>
						<div class="cqfw-ctrl-checks">
							<?php foreach ( $post_types as $pt ) : ?>
								<label><input type="checkbox" name="cqfw_widget_controls[post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $c['post_types'], true ) ); ?> /> <?php echo esc_html( $pt->labels->singular_name ); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div>
						<label class="cqfw-ctrl-label" for="cqfw_page_ids_include"><?php esc_html_e( 'Only these Page / Post IDs', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" id="cqfw_page_ids_include" name="cqfw_widget_controls[page_ids_include]" value="<?php echo esc_attr( $c['page_ids_include'] ); ?>" placeholder="12, 45, 90" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-label" for="cqfw_page_ids_exclude"><?php esc_html_e( 'Hide on these Page / Post IDs', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" id="cqfw_page_ids_exclude" name="cqfw_widget_controls[page_ids_exclude]" value="<?php echo esc_attr( $c['page_ids_exclude'] ); ?>" placeholder="3, 8" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-label" for="cqfw_category_ids"><?php esc_html_e( 'Product / category IDs', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" id="cqfw_category_ids" name="cqfw_widget_controls[category_ids]" value="<?php echo esc_attr( $c['category_ids'] ); ?>" placeholder="15, 22" class="regular-text" />
					</div>
				</div>
				<div class="cqfw-ctrl-row">
					<label class="cqfw-ctrl-check"><input type="checkbox" name="cqfw_widget_controls[show_desktop]" value="1" <?php checked( ! empty( $c['show_desktop'] ) ); ?> /> <?php esc_html_e( 'Show on desktop', 'chat-quote-for-woocommerce' ); ?></label>
					<label class="cqfw-ctrl-check"><input type="checkbox" name="cqfw_widget_controls[show_mobile]" value="1" <?php checked( ! empty( $c['show_mobile'] ) ); ?> /> <?php esc_html_e( 'Show on mobile', 'chat-quote-for-woocommerce' ); ?></label>
				</div>
			</div>

			<div class="cqfw-ctrl-card">
				<h3><span class="dashicons dashicons-smiley"></span> <?php esc_html_e( 'Greetings', 'chat-quote-for-woocommerce' ); ?></h3>
				<label class="cqfw-ctrl-check">
					<input type="checkbox" name="cqfw_widget_controls[greeting_enabled]" value="1" <?php checked( ! empty( $c['greeting_enabled'] ) ); ?> />
					<?php esc_html_e( 'Use greeting templates', 'chat-quote-for-woocommerce' ); ?>
				</label>
				<div class="cqfw-ctrl-row" style="margin:12px 0;">
					<label><input type="radio" name="cqfw_widget_controls[greeting_template]" value="1" <?php checked( (string) $c['greeting_template'], '1' ); ?> /> <?php esc_html_e( 'Greeting 1', 'chat-quote-for-woocommerce' ); ?></label>
					<label><input type="radio" name="cqfw_widget_controls[greeting_template]" value="2" <?php checked( (string) $c['greeting_template'], '2' ); ?> /> <?php esc_html_e( 'Greeting 2', 'chat-quote-for-woocommerce' ); ?></label>
				</div>
				<div class="cqfw-ctrl-grid">
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Greeting 1 — header', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[greeting_header]" value="<?php echo esc_attr( $c['greeting_header'] ); ?>" class="regular-text" />
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Main text', 'chat-quote-for-woocommerce' ); ?></label>
						<textarea name="cqfw_widget_controls[greeting_main]" rows="2" class="large-text"><?php echo esc_textarea( $c['greeting_main'] ); ?></textarea>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Bottom note', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[greeting_bottom]" value="<?php echo esc_attr( $c['greeting_bottom'] ); ?>" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Greeting 2 — header', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[greeting_2_header]" value="<?php echo esc_attr( $c['greeting_2_header'] ); ?>" class="regular-text" />
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Main text', 'chat-quote-for-woocommerce' ); ?></label>
						<textarea name="cqfw_widget_controls[greeting_2_main]" rows="2" class="large-text"><?php echo esc_textarea( $c['greeting_2_main'] ); ?></textarea>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Bottom note', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[greeting_2_bottom]" value="<?php echo esc_attr( $c['greeting_2_bottom'] ); ?>" class="regular-text" />
					</div>
				</div>
			</div>

			<div class="cqfw-ctrl-card">
				<h3><span class="dashicons dashicons-share"></span> <?php esc_html_e( 'Effects, Share & Group', 'chat-quote-for-woocommerce' ); ?></h3>
				<div class="cqfw-ctrl-grid">
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Entry animation', 'chat-quote-for-woocommerce' ); ?></label>
						<select name="cqfw_widget_controls[entry_effect]">
							<?php
							$effects = array(
								'none'   => __( 'None', 'chat-quote-for-woocommerce' ),
								'fade'   => __( 'Fade in', 'chat-quote-for-woocommerce' ),
								'slide'  => __( 'Slide up', 'chat-quote-for-woocommerce' ),
								'bounce' => __( 'Bounce', 'chat-quote-for-woocommerce' ),
								'zoom'   => __( 'Zoom', 'chat-quote-for-woocommerce' ),
							);
							foreach ( $effects as $ek => $el ) {
								printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $ek ), selected( $c['entry_effect'], $ek, false ), esc_html( $el ) );
							}
							?>
						</select>
					</div>
					<div>
						<label class="cqfw-ctrl-check"><input type="checkbox" name="cqfw_widget_controls[enable_share]" value="1" <?php checked( ! empty( $c['enable_share'] ) ); ?> /> <?php esc_html_e( 'Show “Share page on WhatsApp”', 'chat-quote-for-woocommerce' ); ?></label>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Share message ({url} = page link)', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[share_message]" value="<?php echo esc_attr( $c['share_message'] ); ?>" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-check"><input type="checkbox" name="cqfw_widget_controls[enable_group_chat]" value="1" <?php checked( ! empty( $c['enable_group_chat'] ) ); ?> /> <?php esc_html_e( 'Enable WhatsApp Group link', 'chat-quote-for-woocommerce' ); ?></label>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Group invite URL', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="url" name="cqfw_widget_controls[group_chat_url]" value="<?php echo esc_attr( $c['group_chat_url'] ); ?>" class="regular-text" placeholder="https://chat.whatsapp.com/..." />
					</div>
				</div>
			</div>

			<div class="cqfw-ctrl-card">
				<h3><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Analytics & Tracking', 'chat-quote-for-woocommerce' ); ?></h3>
				<p class="cqfw-ctrl-help"><?php esc_html_e( 'Fire events when someone clicks WhatsApp. Works with tags already installed on your site.', 'chat-quote-for-woocommerce' ); ?></p>
				<div class="cqfw-ctrl-grid">
					<div>
						<label class="cqfw-ctrl-check"><input type="checkbox" name="cqfw_widget_controls[ga_enabled]" value="1" <?php checked( ! empty( $c['ga_enabled'] ) ); ?> /> <?php esc_html_e( 'Google Analytics / gtag', 'chat-quote-for-woocommerce' ); ?></label>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Event name', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[ga_event_name]" value="<?php echo esc_attr( $c['ga_event_name'] ); ?>" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-check"><input type="checkbox" name="cqfw_widget_controls[fb_pixel_enabled]" value="1" <?php checked( ! empty( $c['fb_pixel_enabled'] ) ); ?> /> <?php esc_html_e( 'Facebook Pixel', 'chat-quote-for-woocommerce' ); ?></label>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Event name', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[fb_pixel_event]" value="<?php echo esc_attr( $c['fb_pixel_event'] ); ?>" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-check"><input type="checkbox" name="cqfw_widget_controls[webhook_enabled]" value="1" <?php checked( ! empty( $c['webhook_enabled'] ) ); ?> /> <?php esc_html_e( 'Webhooks', 'chat-quote-for-woocommerce' ); ?></label>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Webhook URL', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="url" name="cqfw_widget_controls[webhook_url]" value="<?php echo esc_attr( $c['webhook_url'] ); ?>" class="regular-text" placeholder="https://hooks.example.com/..." />
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Pro-only panels (single place: Team & Hours). Freemius premium package.
	 *
	 * @param array<string,mixed> $c Controls.
	 * @return void
	 */
	private static function render_pro_panels( $c ) {
		?>
		<div class="cqfw-controls-admin">
			<input type="hidden" name="cqfw_controls_include_pro" value="1" />
			<p class="cqfw-ctrl-help" style="margin-bottom:8px;">
				<?php esc_html_e( 'Pro settings extend your global Floating Chat rules. Configure each section once here.', 'chat-quote-for-woocommerce' ); ?>
			</p>

			<div class="cqfw-ctrl-card cqfw-ctrl-pro">
				<h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Advanced Display (Pro)', 'chat-quote-for-woocommerce' ); ?></h3>
				<p class="cqfw-ctrl-help"><?php esc_html_e( 'Extra visibility rules on top of global post/page/device settings.', 'chat-quote-for-woocommerce' ); ?></p>
				<div class="cqfw-ctrl-grid">
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Login status', 'chat-quote-for-woocommerce' ); ?></label>
						<select name="cqfw_widget_controls[login_status]">
							<option value="any" <?php selected( $c['login_status'], 'any' ); ?>><?php esc_html_e( 'Everyone', 'chat-quote-for-woocommerce' ); ?></option>
							<option value="logged_in" <?php selected( $c['login_status'], 'logged_in' ); ?>><?php esc_html_e( 'Logged-in users only', 'chat-quote-for-woocommerce' ); ?></option>
							<option value="logged_out" <?php selected( $c['login_status'], 'logged_out' ); ?>><?php esc_html_e( 'Guests only', 'chat-quote-for-woocommerce' ); ?></option>
						</select>
					</div>
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Show only in countries', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[countries_include]" value="<?php echo esc_attr( $c['countries_include'] ); ?>" placeholder="BD, US, IN" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Hide in countries', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[countries_exclude]" value="<?php echo esc_attr( $c['countries_exclude'] ); ?>" placeholder="CN" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-check"><input type="checkbox" name="cqfw_widget_controls[notification_badge]" value="1" <?php checked( ! empty( $c['notification_badge'] ) ); ?> /> <?php esc_html_e( 'Notification badge on launcher', 'chat-quote-for-woocommerce' ); ?></label>
					</div>
				</div>
			</div>

			<div class="cqfw-ctrl-card cqfw-ctrl-pro">
				<h3><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Triggers & Delays (Pro)', 'chat-quote-for-woocommerce' ); ?></h3>
				<div class="cqfw-ctrl-grid">
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Time delay (seconds)', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="number" min="0" max="300" name="cqfw_widget_controls[time_delay]" value="<?php echo esc_attr( (string) $c['time_delay'] ); ?>" />
					</div>
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Page scroll % before show', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="number" min="0" max="100" name="cqfw_widget_controls[scroll_delay]" value="<?php echo esc_attr( (string) $c['scroll_delay'] ); ?>" />
					</div>
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Click CSS selector to open', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[click_trigger]" value="<?php echo esc_attr( $c['click_trigger'] ); ?>" placeholder=".buy-now, #open-chat" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Show when element reaches viewport', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[viewport_trigger]" value="<?php echo esc_attr( $c['viewport_trigger'] ); ?>" placeholder="#reviews, .footer" class="regular-text" />
					</div>
				</div>
			</div>

			<div class="cqfw-ctrl-card cqfw-ctrl-pro">
				<h3><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'WooCommerce Product Greeting (Pro)', 'chat-quote-for-woocommerce' ); ?></h3>
				<label class="cqfw-ctrl-check">
					<input type="checkbox" name="cqfw_widget_controls[woo_greeting_enabled]" value="1" <?php checked( ! empty( $c['woo_greeting_enabled'] ) ); ?> />
					<?php esc_html_e( 'Override greeting on single product pages', 'chat-quote-for-woocommerce' ); ?>
				</label>
				<div class="cqfw-ctrl-grid">
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Header', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[woo_greeting_header]" value="<?php echo esc_attr( $c['woo_greeting_header'] ); ?>" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Main', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[woo_greeting_main]" value="<?php echo esc_attr( $c['woo_greeting_main'] ); ?>" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Bottom', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[woo_greeting_bottom]" value="<?php echo esc_attr( $c['woo_greeting_bottom'] ); ?>" class="regular-text" />
					</div>
				</div>
			</div>

			<div class="cqfw-ctrl-card cqfw-ctrl-pro">
				<h3><span class="dashicons dashicons-phone"></span> <?php esc_html_e( 'Offline & Random Numbers (Pro)', 'chat-quote-for-woocommerce' ); ?></h3>
				<div class="cqfw-ctrl-grid">
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'WhatsApp number when offline', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[offline_number]" value="<?php echo esc_attr( $c['offline_number'] ); ?>" class="regular-text" placeholder="+8801..." />
					</div>
					<div>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Call-to-action text when offline', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[offline_cta]" value="<?php echo esc_attr( $c['offline_cta'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Leave a message', 'chat-quote-for-woocommerce' ); ?>" />
					</div>
					<div>
						<label class="cqfw-ctrl-check"><input type="checkbox" name="cqfw_widget_controls[random_enabled]" value="1" <?php checked( ! empty( $c['random_enabled'] ) ); ?> /> <?php esc_html_e( 'Rotate random WhatsApp numbers', 'chat-quote-for-woocommerce' ); ?></label>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'Numbers (comma separated)', 'chat-quote-for-woocommerce' ); ?></label>
						<textarea name="cqfw_widget_controls[random_numbers]" rows="2" class="large-text" placeholder="+8801..., +8801..."><?php echo esc_textarea( $c['random_numbers'] ); ?></textarea>
					</div>
				</div>
			</div>

			<div class="cqfw-ctrl-card cqfw-ctrl-pro">
				<h3><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Advanced Tracking (Pro)', 'chat-quote-for-woocommerce' ); ?></h3>
				<p class="cqfw-ctrl-help"><?php esc_html_e( 'Google Ads and advanced variables. Basic GA / Pixel / Webhooks are under Floating Chat.', 'chat-quote-for-woocommerce' ); ?></p>
				<div class="cqfw-ctrl-grid">
					<div>
						<label class="cqfw-ctrl-check"><input type="checkbox" name="cqfw_widget_controls[google_ads_enabled]" value="1" <?php checked( ! empty( $c['google_ads_enabled'] ) ); ?> /> <?php esc_html_e( 'Google Ads conversion', 'chat-quote-for-woocommerce' ); ?></label>
						<label class="cqfw-ctrl-label"><?php esc_html_e( 'send_to (AW-XXXX/label)', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" name="cqfw_widget_controls[google_ads_send_to]" value="<?php echo esc_attr( $c['google_ads_send_to'] ); ?>" class="regular-text" />
					</div>
					<div>
						<label class="cqfw-ctrl-check"><input type="checkbox" name="cqfw_widget_controls[tracking_advanced]" value="1" <?php checked( ! empty( $c['tracking_advanced'] ) ); ?> /> <?php esc_html_e( 'Advanced variables (page, product, country, login)', 'chat-quote-for-woocommerce' ); ?></label>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
