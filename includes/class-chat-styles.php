<?php
/**
 * Chat Styles & Live Customizer Class.
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_Chat_Styles {

	/**
	 * Option name for styles.
	 */
	const OPTION_NAME = 'cqfw_chat_styles';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_styles_setting' ) );
		add_action( 'admin_init', array( $this, 'register_styles_page' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles_assets' ) );
		add_action( 'admin_post_cqfw_save_chat_styles', array( $this, 'handle_save_styles' ) );
	}

	/**
	 * Register the cqfw_chat_styles setting so options.php saves it with cqfw_settings_group.
	 *
	 * @return void
	 */
	public function register_styles_setting() {
		register_setting(
			'cqfw_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_styles' ),
				'default'           => self::get_default_settings(),
			)
		);
	}

	/**
	 * Legacy cqfw-styles slug — redirect into Chat Bubble → Look tab (no separate menu).
	 *
	 * @return void
	 */
	public function register_styles_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || 'cqfw-styles' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		$this->redirect_to_settings_tab();
	}

	/**
	 * Redirect old Chat Look URL to Chat Bubble Look tab.
	 *
	 * @return void
	 */
	public function redirect_to_settings_tab() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			return;
		}
		$args = array(
			'page' => 'cqfw-widget',
			'tab'  => 'look',
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			$args['updated'] = '1';
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Enqueue assets for the Chat Styles on settings screen.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_styles_assets( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		$on_look = ( 'cqfw-widget' === $page && 'look' === $tab )
			|| ( 'cqfw-styles' === $page )
			|| ( false !== strpos( (string) $hook, 'cqfw-styles' ) );

		if ( ! $on_look ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		$css_ver = file_exists( CQFW_PATH . 'assets/css/admin-styles.css' ) ? (string) filemtime( CQFW_PATH . 'assets/css/admin-styles.css' ) : CQFW_VERSION;
		$js_ver  = file_exists( CQFW_PATH . 'assets/js/admin-styles.js' ) ? (string) filemtime( CQFW_PATH . 'assets/js/admin-styles.js' ) : CQFW_VERSION;

		wp_enqueue_style(
			'cqfw-admin-styles-css',
			CQFW_URL . 'assets/css/admin-styles.css',
			array( 'wp-color-picker' ),
			$css_ver
		);

		wp_enqueue_script(
			'cqfw-admin-styles-js',
			CQFW_URL . 'assets/js/admin-styles.js',
			array( 'jquery', 'wp-color-picker' ),
			$js_ver,
			true
		);

		$upgrade_url = function_exists( 'cqfw_fs' ) ? cqfw_fs()->get_upgrade_url() : 'https://wpchatquote.com/pro';

		wp_localize_script(
			'cqfw-admin-styles-js',
			'cqfwStylesConfig',
			array(
				'isPro'       => function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro(),
				'upgradeUrl'  => $upgrade_url,
				'i18n'        => array(
					'proRequired'      => __( 'Pro Feature', 'chat-quote-for-woocommerce' ),
					'proModalTitle'    => __( 'Unlock All 10+ Premium Chat Styles', 'chat-quote-for-woocommerce' ),
					'proModalDesc'     => __( 'This style is exclusively available in the Pro version of Chat Quote for WooCommerce. Upgrade now to unlock all 10+ modern widget & button styles, custom image uploads, agent working hours, live chat and much more!', 'chat-quote-for-woocommerce' ),
					'upgradeButton'    => __( 'Upgrade to PRO', 'chat-quote-for-woocommerce' ),
					'close'            => __( 'Close', 'chat-quote-for-woocommerce' ),
					'mediaTitle'       => __( 'Choose Custom Chat Button Image', 'chat-quote-for-woocommerce' ),
					'mediaButton'      => __( 'Use This Image', 'chat-quote-for-woocommerce' ),
					'agentMediaTitle'  => __( 'Choose Agent Avatar Photo', 'chat-quote-for-woocommerce' ),
					'agentMediaButton' => __( 'Set Agent Avatar', 'chat-quote-for-woocommerce' ),
				),
				'urls'        => array(
					'agent1'     => CQFW_URL . 'assets/images/agent-1.png',
					'agent2'     => CQFW_URL . 'assets/images/agent-2.png',
					'agent3'     => CQFW_URL . 'assets/images/agent-3.png',
					'agentSales' => CQFW_URL . 'assets/images/agent-sales.png',
				),
			)
		);
	}

	/**
	 * Get definitions of all styles.
	 * Free styles always ship. Pro styles are wrapped in is__premium_only() so
	 * Freemius strips them from the WordPress.org free ZIP (no trialware locks).
	 *
	 * @return array
	 */
	public static function get_all_styles() {
		$styles = array(
			'style_1' => array(
				'id'          => 'style_1',
				'name'        => __( 'Style 1', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Modern Card Style', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Modern card popup with multi-agent cards, live availability dots, and smooth conversation switch.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => false,
			),
			'style_2' => array(
				'id'          => 'style_2',
				'name'        => __( 'Style 2', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Square Icon', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Modern square rounded icon button with smooth shadow and crisp white logo.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => false,
			),
		);

		// Premium-only styles — removed from free package by Freemius preprocessor.
		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) {
			$styles['style_3'] = array(
				'id'          => 'style_3',
				'name'        => __( 'Style 3', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Round Icon', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Classic circular floating WhatsApp button with subtle pulse.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_3_extend'] = array(
				'id'          => 'style_3_extend',
				'name'        => __( 'Style 3 Extend', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Round Icon with Badge', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Circular icon button with active online notification badge.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_4'] = array(
				'id'          => 'style_4',
				'name'        => __( 'Style 4', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Chip Button', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Modern capsule chip button with icon and compact WhatsApp us label.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_5'] = array(
				'id'          => 'style_5',
				'name'        => __( 'Style 5', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Image Slider / Avatar', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Agent avatar photo with pulsing WhatsApp indicator for higher click-throughs.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_6'] = array(
				'id'          => 'style_6',
				'name'        => __( 'Style 6', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Text Only', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Clean minimalist text-only link without heavy icons.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_7'] = array(
				'id'          => 'style_7',
				'name'        => __( 'Style 7', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Rounded Button', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Pill-shaped solid rounded button with soft hover scaling.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_7_extend'] = array(
				'id'          => 'style_7_extend',
				'name'        => __( 'Style 7 Extend', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Elevated Rounded Button', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Deep shadow elevated rounded button with glowing green border.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_8'] = array(
				'id'          => 'style_8',
				'name'        => __( 'Style 8', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Rect Button', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Crisp rectangular solid bar with bold WhatsApp branding.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_10'] = array(
				'id'          => 'style_10',
				'name'        => __( 'Style 10', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Team Agents Pill', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Modern pill badge with multi-agent avatar stack, online count, and floating WhatsApp action button.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_11'] = array(
				'id'          => 'style_11',
				'name'        => __( 'Style 11', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Animated Agent Callout', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Floating animated speech bubble with live agent status, halo glow, and circular WhatsApp trigger.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_12'] = array(
				'id'          => 'style_12',
				'name'        => __( 'Style 12', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( '3 Agents Callout & Counter', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Modern multi-agent vertical column with conversational prompt card, unread badge counter and floating halo button.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_99'] = array(
				'id'          => 'style_99',
				'name'        => __( 'Style 99', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Custom Image', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Upload your own custom designed button, banner or mascot from Media Library.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
		}

		return $styles;
	}

	/**
	 * Get definitions of all popup window styles.
	 * Pro popup styles are stripped from the free WordPress.org package by Freemius.
	 *
	 * @return array
	 */
	public static function get_all_popup_styles() {
		$styles = array(
			'style_1_modern_card' => array(
				'id'          => 'style_1_modern_card',
				'name'        => __( 'Style 1', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Modern Card Style', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Clean modern card popup with multi-agent cards, live availability dots, and website chat switch.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => false,
			),
			'classic_whatsapp' => array(
				'id'          => 'classic_whatsapp',
				'name'        => __( 'Style 2', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Classic WhatsApp', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Official WhatsApp inspired green header layout with clean agent contact rows.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => false,
			),
		);

		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) {
			$styles['style_3_compact'] = array(
				'id'          => 'style_3_compact',
				'name'        => __( 'Style 3', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Clean White Card', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Clean white conversation card with status badges, department pills, and quick connect.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_4_glass'] = array(
				'id'          => 'style_4_glass',
				'name'        => __( 'Style 4', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Pastel Bot & Departments', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Friendly 3D bot mascot with soft sky-water header, pastel department cards, and quick connection.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_5_dark'] = array(
				'id'          => 'style_5_dark',
				'name'        => __( 'Style 5', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Dark Neon Gradient Card', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Cyberpunk dark theme with purple-blue gradient header, neon agent accent bars, and WhatsApp connect.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_6_gradient'] = array(
				'id'          => 'style_6_gradient',
				'name'        => __( 'Style 6', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Frosted Glass & Online Badges', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Clean frosted glass card with live online status pills, agent headset avatars, and quick WhatsApp connect.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_7_minimal'] = array(
				'id'          => 'style_7_minimal',
				'name'        => __( 'Style 7', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Minimal Boutique', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Pure minimalist typography and hairline dividers for luxury & boutique shops.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_8_pill_stack'] = array(
				'id'          => 'style_8_pill_stack',
				'name'        => __( 'Style 8', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Pill Stack Modal', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Full rounded capsule agent cards with interactive floating bubble triggers.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_9_neumorphic'] = array(
				'id'          => 'style_9_neumorphic',
				'name'        => __( 'Style 9', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'Soft Neumorphic Card', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Soft embossed shadows with tactile 3D cards and smooth hover depths.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
			$styles['style_10_vip_concierge'] = array(
				'id'          => 'style_10_vip_concierge',
				'name'        => __( 'Style 10', 'chat-quote-for-woocommerce' ),
				'subtitle'    => __( 'VIP Concierge Modal', 'chat-quote-for-woocommerce' ),
				'description' => __( 'Gold trimmed premium concierge styling with verified representative badges.', 'chat-quote-for-woocommerce' ),
				'is_pro'      => true,
			);
		}

		return $styles;
	}

	/**
	 * Get default configuration values.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'active_style'      => 'style_1',
			'popup_style'       => 'style_1_modern_card',
			'text_color'        => '',
			'background_color'  => '',
			'add_icon'          => '1',
			'icon_color'        => '',
			'icon_size'         => '18px',
			'custom_image'      => '',
			'agent_1_avatar'    => '',
			'agent_2_avatar'    => '',
			'agent_3_avatar'    => '',
			'full_width_mobile' => '0',
			// Positioning
			'position_type'     => 'fixed', // fixed or absolute
			'pos_v_side'        => 'bottom', // bottom or top
			'pos_v_offset'      => '24px',
			'pos_h_side'        => 'right',  // right or left
			'pos_h_offset'      => '24px',
		);
	}

	/**
	 * Sanitize styles input callback.
	 *
	 * @param array $input Input data.
	 * @return array
	 */
	public static function sanitize_styles( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$is_pro            = function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro();
		$style_id          = isset( $input['active_style'] ) ? sanitize_key( $input['active_style'] ) : 'style_1';
		$popup_style       = isset( $input['popup_style'] ) ? sanitize_key( $input['popup_style'] ) : 'style_1_modern_card';
		$free_styles       = array( 'style_1', 'style_2' );
		$free_popup_styles = array( 'style_1_modern_card', 'classic_whatsapp' );

		if ( ! $is_pro ) {
			if ( ! in_array( $style_id, $free_styles, true ) ) {
				$style_id = 'style_1';
			}
			if ( ! in_array( $popup_style, $free_popup_styles, true ) ) {
				$popup_style = 'style_1_modern_card';
			}
		}

		return array(
			'active_style'      => $style_id,
			'popup_style'       => $popup_style,
			'text_color'        => sanitize_hex_color( isset( $input['text_color'] ) ? $input['text_color'] : '' ),
			'background_color'  => sanitize_hex_color( isset( $input['background_color'] ) ? $input['background_color'] : '' ),
			'add_icon'          => ( isset( $input['add_icon'] ) && '1' === (string) $input['add_icon'] ) || ! isset( $input['add_icon_present'] ) ? '1' : '0',
			'icon_color'        => sanitize_hex_color( isset( $input['icon_color'] ) ? $input['icon_color'] : '' ),
			'icon_size'         => sanitize_text_field( isset( $input['icon_size'] ) ? $input['icon_size'] : '18px' ),
			'custom_image'      => esc_url_raw( isset( $input['custom_image'] ) ? $input['custom_image'] : '' ),
			'agent_1_avatar'    => esc_url_raw( isset( $input['agent_1_avatar'] ) ? $input['agent_1_avatar'] : '' ),
			'agent_2_avatar'    => esc_url_raw( isset( $input['agent_2_avatar'] ) ? $input['agent_2_avatar'] : '' ),
			'agent_3_avatar'    => esc_url_raw( isset( $input['agent_3_avatar'] ) ? $input['agent_3_avatar'] : '' ),
			'full_width_mobile' => ! empty( $input['full_width_mobile'] ) ? '1' : '0',
			'position_type'     => ( isset( $input['position_type'] ) && 'absolute' === $input['position_type'] && $is_pro ) ? 'absolute' : 'fixed',
			'pos_v_side'        => ( isset( $input['pos_v_side'] ) && 'top' === $input['pos_v_side'] ) ? 'top' : 'bottom',
			'pos_v_offset'      => sanitize_text_field( isset( $input['pos_v_offset'] ) ? $input['pos_v_offset'] : '24px' ),
			'pos_h_side'        => ( isset( $input['pos_h_side'] ) && 'left' === $input['pos_h_side'] ) ? 'left' : 'right',
			'pos_h_offset'      => sanitize_text_field( isset( $input['pos_h_offset'] ) ? $input['pos_h_offset'] : '24px' ),
		);
	}

	/**
	 * Get saved style settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_saved_settings() {
		$saved = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$merged = wp_parse_args( $saved, self::get_default_settings() );

		if ( empty( $merged['popup_style'] ) || 'style_1' === $merged['popup_style'] ) {
			$merged['popup_style'] = 'style_1_modern_card';
		}

		// Enforce Pro licensing: if not Pro, active styles fall back to free styles (Style 1 or Style 2).
		$is_pro            = function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro();
		$free_styles       = array( 'style_1', 'style_2' );
		$free_popup_styles = array( 'style_1_modern_card', 'classic_whatsapp' );

		if ( ! $is_pro ) {
			if ( ! in_array( $merged['active_style'], $free_styles, true ) ) {
				$merged['active_style'] = 'style_1';
			}
			if ( ! in_array( $merged['popup_style'], $free_popup_styles, true ) ) {
				$merged['popup_style'] = 'style_1_modern_card';
			}
		}

		return $merged;
	}

	/**
	 * Save style settings from standalone form submission.
	 *
	 * @return void
	 */
	public function handle_save_styles() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'chat-quote-for-woocommerce' ) );
		}

		check_admin_referer( 'cqfw_save_chat_styles_action', 'cqfw_styles_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_styles = isset( $_POST['cqfw_chat_styles'] ) ? wp_unslash( $_POST['cqfw_chat_styles'] ) : wp_unslash( $_POST );
		$settings   = self::sanitize_styles( is_array( $raw_styles ) ? $raw_styles : array() );
		update_option( self::OPTION_NAME, $settings );

		wp_safe_redirect( add_query_arg( array( 'page' => 'cqfw-widget', 'tab' => 'look', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render the Chat Styles tab content inside the main settings page.
	 *
	 * @return void
	 */
	public static function render_styles_tab_content() {
		$is_pro       = function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro();
		$settings     = self::get_saved_settings();
		$all_styles   = self::get_all_styles();
		$active_style       = $settings['active_style'];
		$active_popup_style = ! empty( $settings['popup_style'] ) ? $settings['popup_style'] : 'style_1_modern_card';
		$active_meta  = isset( $all_styles[ $active_style ] ) ? $all_styles[ $active_style ] : $all_styles['style_1'];

		?>
		<div class="cqfw-styles-tab-wrapper">
			<input type="hidden" name="cqfw_chat_styles[active_style]" id="cqfw_active_style_input" value="<?php echo esc_attr( $active_style ); ?>">

			<!-- Step 1: Select Style Grid -->
			<div class="cqfw-card-section">
				<div class="cqfw-section-heading">
					<div class="cqfw-section-step">1</div>
					<div>
						<h2><?php esc_html_e( 'Select Style', 'chat-quote-for-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Click on a style below to choose your favorite button/widget look.', 'chat-quote-for-woocommerce' ); ?></p>
					</div>
				</div>

				<div class="cqfw-styles-grid">
					<?php foreach ( $all_styles as $s_id => $style ) : 
						$is_selected = ( $s_id === $active_style );
						$is_locked   = ( ! empty( $style['is_pro'] ) && ! $is_pro );
					?>
					<div class="cqfw-style-card <?php echo $is_selected ? 'is-active' : ''; ?> <?php echo $is_locked ? 'is-pro-locked' : ''; ?>"
						 data-style-id="<?php echo esc_attr( $s_id ); ?>"
						 data-is-pro="<?php echo ! empty( $style['is_pro'] ) ? '1' : '0'; ?>"
						 data-style-title="<?php echo esc_attr( $style['name'] ); ?>"
						 data-style-desc="<?php echo esc_attr( $style['description'] ); ?>">
						
						<div class="cqfw-style-card__status-check">
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
						</div>

						<?php if ( ! empty( $style['is_pro'] ) ) : ?>
							<span class="cqfw-style-badge pro"><?php esc_html_e( 'PRO', 'chat-quote-for-woocommerce' ); ?></span>
						<?php else : ?>
							<span class="cqfw-style-badge free"><?php esc_html_e( 'FREE', 'chat-quote-for-woocommerce' ); ?></span>
						<?php endif; ?>

						<div class="cqfw-style-card__preview">
							<?php self::render_style_preview_thumbnail( $s_id ); ?>
						</div>

						<div class="cqfw-style-card__footer">
							<strong class="cqfw-style-name"><?php echo esc_html( $style['name'] ); ?></strong>
							<span class="cqfw-style-subtitle"><?php echo esc_html( $style['subtitle'] ); ?></span>
						</div>

						<div class="cqfw-style-card__action-bar">
							<?php if ( $is_locked ) : ?>
								<span class="cqfw-style-btn unlock">🔒 <?php esc_html_e( 'Unlock PRO', 'chat-quote-for-woocommerce' ); ?></span>
							<?php else : ?>
								<span class="cqfw-style-btn customize">✏️ <?php esc_html_e( 'Customize', 'chat-quote-for-woocommerce' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Step 2: Select Popup Window Style (Visual Cards Grid) -->
			<div class="cqfw-card-section">
				<div class="cqfw-section-heading">
					<div class="cqfw-section-step">3</div>
					<div>
						<h2><?php esc_html_e( 'Select Popup Window Style', 'chat-quote-for-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Choose your favorite multi-agent chat popup look. Whichever style you select here is exactly what visitors see on frontend.', 'chat-quote-for-woocommerce' ); ?></p>
					</div>
				</div>

				<input type="hidden" name="cqfw_chat_styles[popup_style]" id="cqfw_popup_style_input" value="<?php echo esc_attr( $active_popup_style ); ?>">

				<div class="cqfw-popup-styles-grid">
					<?php 
					$all_popup_styles = self::get_all_popup_styles();
					foreach ( $all_popup_styles as $p_id => $p_style ) : 
						$is_p_selected = ( $p_id === $active_popup_style );
						$is_p_locked   = ( ! empty( $p_style['is_pro'] ) && ! $is_pro );
					?>
					<div class="cqfw-popup-style-card <?php echo $is_p_selected ? 'is-active' : ''; ?> <?php echo $is_p_locked ? 'is-pro-locked' : ''; ?>"
						 data-popup-style-id="<?php echo esc_attr( $p_id ); ?>"
						 data-is-pro="<?php echo ! empty( $p_style['is_pro'] ) ? '1' : '0'; ?>"
						 data-style-title="<?php echo esc_attr( $p_style['name'] . ' - ' . $p_style['subtitle'] ); ?>"
						 data-style-desc="<?php echo esc_attr( $p_style['description'] ); ?>">
						
						<div class="cqfw-style-card__status-check">
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
						</div>

						<?php if ( ! empty( $p_style['is_pro'] ) ) : ?>
							<span class="cqfw-style-badge pro"><?php esc_html_e( 'PRO', 'chat-quote-for-woocommerce' ); ?></span>
						<?php else : ?>
							<span class="cqfw-style-badge free"><?php esc_html_e( 'FREE', 'chat-quote-for-woocommerce' ); ?></span>
						<?php endif; ?>

						<div class="cqfw-popup-card__preview">
							<?php self::render_popup_style_thumbnail( $p_id ); ?>
						</div>

						<div class="cqfw-style-card__footer">
							<strong class="cqfw-style-name"><?php echo esc_html( $p_style['name'] ); ?></strong>
							<span class="cqfw-style-subtitle"><?php echo esc_html( $p_style['subtitle'] ); ?></span>
						</div>

						<div class="cqfw-style-card__action-bar">
							<?php if ( $is_p_locked ) : ?>
								<span class="cqfw-style-btn unlock">🔒 <?php esc_html_e( 'Unlock PRO', 'chat-quote-for-woocommerce' ); ?></span>
							<?php else : ?>
								<span class="cqfw-style-btn customize"><?php echo $is_p_selected ? esc_html__( '✓ Selected', 'chat-quote-for-woocommerce' ) : esc_html__( 'Select Style', 'chat-quote-for-woocommerce' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( ! cqfw_can_use_pro() ) : ?>
			<div class="cqfw-card-section cqfw-upsell-banner" style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1px solid #bbf7d0;border-radius:12px;padding:20px 24px;margin-top:8px;">
				<p style="margin:0 0 12px;font-size:14px;line-height:1.5;color:#166534;">
					<?php esc_html_e( 'Want 10+ more chat button styles, custom image buttons, absolute positioning, and advanced popup designs? Upgrade to Pro — Pro features ship in a separate premium package (not locked inside the free plugin).', 'chat-quote-for-woocommerce' ); ?>
				</p>
				<a href="<?php echo esc_url( function_exists( 'cqfw_fs' ) ? cqfw_fs()->get_upgrade_url() : 'https://wpchatquote.com/pro' ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Upgrade to Pro', 'chat-quote-for-woocommerce' ); ?>
				</a>
			</div>
			<?php endif; ?>

			<!-- Step 3: Customization Options (Matching Reference Screenshot 1) -->
			<div class="cqfw-card-section cqfw-customize-section" id="cqfw-customize-section">
				<div class="cqfw-section-heading">
					<div class="cqfw-section-step">3</div>
					<div>
						<h2 id="cqfw-customize-header-title"><?php echo esc_html( $active_meta['name'] ); ?></h2>
						<p id="cqfw-customize-header-desc"><?php echo esc_html( $active_meta['description'] ); ?></p>
					</div>
				</div>

				<div class="cqfw-options-grid">
					<!-- Text Color -->
					<div class="cqfw-option-row">
						<label class="cqfw-option-label" for="cqfw_text_color"><?php esc_html_e( 'Text Color', 'chat-quote-for-woocommerce' ); ?></label>
						<div class="cqfw-color-picker-wrap">
							<input type="text" id="cqfw_text_color" name="cqfw_chat_styles[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>" class="cqfw-color-field" data-default-color="" placeholder="<?php esc_attr_e( 'DEFAULT COLOR', 'chat-quote-for-woocommerce' ); ?>" />
						</div>
					</div>

					<!-- Background Color -->
					<div class="cqfw-option-row">
						<label class="cqfw-option-label" for="cqfw_background_color"><?php esc_html_e( 'Background Color', 'chat-quote-for-woocommerce' ); ?></label>
						<div class="cqfw-color-picker-wrap">
							<input type="text" id="cqfw_background_color" name="cqfw_chat_styles[background_color]" value="<?php echo esc_attr( $settings['background_color'] ); ?>" class="cqfw-color-field" data-default-color="" placeholder="<?php esc_attr_e( 'DEFAULT COLOR', 'chat-quote-for-woocommerce' ); ?>" />
						</div>
					</div>

					<!-- Add Icon Checkbox -->
					<div class="cqfw-option-row cqfw-checkbox-row">
						<label class="cqfw-checkbox-label">
							<input type="hidden" name="cqfw_chat_styles[add_icon_present]" value="1" /><input type="checkbox" id="cqfw_add_icon" name="cqfw_chat_styles[add_icon]" value="1" <?php checked( $settings['add_icon'], '1' ); ?> />
							<span class="cqfw-checkbox-custom"></span>
							<strong><?php esc_html_e( 'Add Icon', 'chat-quote-for-woocommerce' ); ?></strong>
						</label>
					</div>

					<!-- Icon Color -->
					<div class="cqfw-option-row" id="cqfw_icon_color_row">
						<label class="cqfw-option-label" for="cqfw_icon_color"><?php esc_html_e( 'Icon Color', 'chat-quote-for-woocommerce' ); ?></label>
						<div class="cqfw-color-picker-wrap">
							<input type="text" id="cqfw_icon_color" name="cqfw_chat_styles[icon_color]" value="<?php echo esc_attr( $settings['icon_color'] ); ?>" class="cqfw-color-field" data-default-color="" placeholder="<?php esc_attr_e( 'DEFAULT COLOR', 'chat-quote-for-woocommerce' ); ?>" />
						</div>
					</div>

					<!-- Icon Size -->
					<div class="cqfw-option-row" id="cqfw_icon_size_row">
						<label class="cqfw-option-label" for="cqfw_icon_size"><?php esc_html_e( 'Icon Size', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" id="cqfw_icon_size" name="cqfw_chat_styles[icon_size]" value="<?php echo esc_attr( $settings['icon_size'] ); ?>" class="cqfw-input-text" placeholder="18px" />
						<span class="cqfw-field-desc"><?php esc_html_e( 'Icon Size - E.g. 16px, 18px, 24px', 'chat-quote-for-woocommerce' ); ?></span>
					</div>

					<!-- Custom Image Upload (Only for Style 99 - Custom) -->
					<div class="cqfw-option-row" id="cqfw_custom_image_row" style="<?php echo ( 'style_99' === $active_style ) ? '' : 'display:none;'; ?>">
						<label class="cqfw-option-label" for="cqfw_custom_image"><?php esc_html_e( 'Custom Button Image / Avatar', 'chat-quote-for-woocommerce' ); ?></label>
						<div class="cqfw-media-upload-box">
							<div class="cqfw-media-preview-wrap">
								<img id="cqfw_custom_image_preview" src="<?php echo esc_url( ! empty( $settings['custom_image'] ) ? $settings['custom_image'] : CQFW_URL . 'assets/images/placeholder-avatar.svg' ); ?>" alt="Preview" />
							</div>
							<div class="cqfw-media-controls">
								<input type="hidden" id="cqfw_custom_image" name="cqfw_chat_styles[custom_image]" value="<?php echo esc_attr( $settings['custom_image'] ); ?>" />
								<button type="button" class="button cqfw-btn-upload-image" id="cqfw_upload_img_btn"><?php esc_html_e( 'Upload Image', 'chat-quote-for-woocommerce' ); ?></button>
								<button type="button" class="button cqfw-btn-remove-image" id="cqfw_remove_img_btn" style="<?php echo empty( $settings['custom_image'] ) ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Remove', 'chat-quote-for-woocommerce' ); ?></button>
							</div>
						</div>
						<span class="cqfw-field-desc"><?php esc_html_e( 'Recommended square PNG or SVG with transparent background (120x120px).', 'chat-quote-for-woocommerce' ); ?></span>
					</div>

					<!-- Agent Avatars Customization (Style 10, Style 11, Style 12) -->
					<div class="cqfw-option-row" id="cqfw_agent_avatars_row" style="<?php echo in_array( $active_style, array( 'style_10', 'style_11', 'style_12' ), true ) ? '' : 'display:none;'; ?>">
						<label class="cqfw-option-label">
							<?php esc_html_e( 'Agent Avatar Images', 'chat-quote-for-woocommerce' ); ?>
							<span class="cqfw-badge-pill"><?php esc_html_e( 'Click on any avatar to change', 'chat-quote-for-woocommerce' ); ?></span>
						</label>
						<div class="cqfw-agent-uploaders-container">
							<!-- Agent 1 / Sales Support -->
							<div class="cqfw-agent-uploader-item" id="cqfw_agent_uploader_1">
								<div class="cqfw-agent-avatar-preview-wrap">
									<?php 
										$default_agent_1 = ( 'style_11' === $active_style ) ? CQFW_URL . 'assets/images/agent-sales.png' : CQFW_URL . 'assets/images/agent-1.png';
										$agent_1_src     = ! empty( $settings['agent_1_avatar'] ) ? $settings['agent_1_avatar'] : $default_agent_1;
									?>
									<img id="cqfw_agent_1_preview" class="cqfw-agent-preview-img" src="<?php echo esc_url( $agent_1_src ); ?>" alt="Agent 1" />
									<span class="cqfw-agent-edit-overlay" title="<?php esc_attr_e( 'Change Image', 'chat-quote-for-woocommerce' ); ?>">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
									</span>
								</div>
								<div class="cqfw-agent-uploader-meta">
									<strong class="cqfw-agent-uploader-label" id="cqfw_agent_label_1"><?php echo ( 'style_11' === $active_style ) ? esc_html__( 'Sales Support', 'chat-quote-for-woocommerce' ) : esc_html__( 'Agent 1', 'chat-quote-for-woocommerce' ); ?></strong>
									<input type="hidden" id="cqfw_agent_1_avatar" name="cqfw_chat_styles[agent_1_avatar]" value="<?php echo esc_attr( $settings['agent_1_avatar'] ); ?>" />
									<div class="cqfw-agent-buttons-group">
										<button type="button" class="button button-small cqfw-btn-agent-upload" data-agent-slot="1"><?php esc_html_e( 'Change', 'chat-quote-for-woocommerce' ); ?></button>
										<button type="button" class="button button-small cqfw-btn-agent-reset" data-agent-slot="1" style="<?php echo empty( $settings['agent_1_avatar'] ) ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Reset', 'chat-quote-for-woocommerce' ); ?></button>
									</div>
								</div>
							</div>

							<!-- Agent 2 -->
							<div class="cqfw-agent-uploader-item" id="cqfw_agent_uploader_2" style="<?php echo ( 'style_11' === $active_style ) ? 'display:none;' : ''; ?>">
								<div class="cqfw-agent-avatar-preview-wrap">
									<?php $agent_2_src = ! empty( $settings['agent_2_avatar'] ) ? $settings['agent_2_avatar'] : CQFW_URL . 'assets/images/agent-2.png'; ?>
									<img id="cqfw_agent_2_preview" class="cqfw-agent-preview-img" src="<?php echo esc_url( $agent_2_src ); ?>" alt="Agent 2" />
									<span class="cqfw-agent-edit-overlay" title="<?php esc_attr_e( 'Change Image', 'chat-quote-for-woocommerce' ); ?>">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
									</span>
								</div>
								<div class="cqfw-agent-uploader-meta">
									<strong class="cqfw-agent-uploader-label"><?php esc_html_e( 'Agent 2', 'chat-quote-for-woocommerce' ); ?></strong>
									<input type="hidden" id="cqfw_agent_2_avatar" name="cqfw_chat_styles[agent_2_avatar]" value="<?php echo esc_attr( $settings['agent_2_avatar'] ); ?>" />
									<div class="cqfw-agent-buttons-group">
										<button type="button" class="button button-small cqfw-btn-agent-upload" data-agent-slot="2"><?php esc_html_e( 'Change', 'chat-quote-for-woocommerce' ); ?></button>
										<button type="button" class="button button-small cqfw-btn-agent-reset" data-agent-slot="2" style="<?php echo empty( $settings['agent_2_avatar'] ) ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Reset', 'chat-quote-for-woocommerce' ); ?></button>
									</div>
								</div>
							</div>

							<!-- Agent 3 -->
							<div class="cqfw-agent-uploader-item" id="cqfw_agent_uploader_3" style="<?php echo ( 'style_11' === $active_style ) ? 'display:none;' : ''; ?>">
								<div class="cqfw-agent-avatar-preview-wrap">
									<?php $agent_3_src = ! empty( $settings['agent_3_avatar'] ) ? $settings['agent_3_avatar'] : CQFW_URL . 'assets/images/agent-3.png'; ?>
									<img id="cqfw_agent_3_preview" class="cqfw-agent-preview-img" src="<?php echo esc_url( $agent_3_src ); ?>" alt="Agent 3" />
									<span class="cqfw-agent-edit-overlay" title="<?php esc_attr_e( 'Change Image', 'chat-quote-for-woocommerce' ); ?>">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
									</span>
								</div>
								<div class="cqfw-agent-uploader-meta">
									<strong class="cqfw-agent-uploader-label"><?php esc_html_e( 'Agent 3', 'chat-quote-for-woocommerce' ); ?></strong>
									<input type="hidden" id="cqfw_agent_3_avatar" name="cqfw_chat_styles[agent_3_avatar]" value="<?php echo esc_attr( $settings['agent_3_avatar'] ); ?>" />
									<div class="cqfw-agent-buttons-group">
										<button type="button" class="button button-small cqfw-btn-agent-upload" data-agent-slot="3"><?php esc_html_e( 'Change', 'chat-quote-for-woocommerce' ); ?></button>
										<button type="button" class="button button-small cqfw-btn-agent-reset" data-agent-slot="3" style="<?php echo empty( $settings['agent_3_avatar'] ) ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Reset', 'chat-quote-for-woocommerce' ); ?></button>
									</div>
								</div>
							</div>
						</div>
						<span class="cqfw-field-desc"><?php esc_html_e( 'Click "Change" or click directly on any agent avatar in the preview cards to upload a photo from WordPress Media Library.', 'chat-quote-for-woocommerce' ); ?></span>
					</div>

					<!-- Full Width on Mobile -->
					<div class="cqfw-option-row cqfw-checkbox-row">
						<label class="cqfw-checkbox-label">
							<input type="checkbox" id="cqfw_full_width_mobile" name="cqfw_chat_styles[full_width_mobile]" value="1" <?php checked( $settings['full_width_mobile'], '1' ); ?> />
							<span class="cqfw-checkbox-custom"></span>
							<strong><?php esc_html_e( 'Full Width on Mobile', 'chat-quote-for-woocommerce' ); ?></strong>
						</label>
						<span class="cqfw-field-desc"><?php esc_html_e( 'Expands the chat button to span across the bottom of the mobile screen for maximum conversions.', 'chat-quote-for-woocommerce' ); ?></span>
					</div>
				</div>

				<div class="cqfw-info-notice">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
					<span><?php esc_html_e( 'These settings belong to the selected style. Wherever this style is active, it uses these exact values.', 'chat-quote-for-woocommerce' ); ?></span>
				</div>
			</div>

			<!-- Step 3: Position & Placement (Matching Reference Screenshot 2) -->
			<div class="cqfw-card-section">
				<div class="cqfw-section-heading">
					<div class="cqfw-section-step">4</div>
					<div>
						<h2><?php esc_html_e( 'Position & Screen Placement', 'chat-quote-for-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Define exactly where the floating button appears on desktop and mobile devices.', 'chat-quote-for-woocommerce' ); ?></p>
					</div>
				</div>

				<div class="cqfw-placement-wrap">
					<!-- Position Type -->
					<div class="cqfw-placement-field">
						<label class="cqfw-option-label" for="cqfw_position_type"><?php esc_html_e( 'Position Type:', 'chat-quote-for-woocommerce' ); ?></label>
						<select id="cqfw_position_type" name="cqfw_chat_styles[position_type]" class="cqfw-select">
							<option value="fixed" <?php selected( $settings['position_type'], 'fixed' ); ?>><?php esc_html_e( 'Fixed', 'chat-quote-for-woocommerce' ); ?></option>
							<?php if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) : ?>
							<option value="absolute" <?php selected( $settings['position_type'], 'absolute' ); ?> <?php echo ! $is_pro ? 'disabled' : ''; ?>><?php esc_html_e( 'Absolute (PRO)', 'chat-quote-for-woocommerce' ); ?></option>
							<?php endif; ?>
						</select>
						<span class="cqfw-field-desc">
							<strong><?php esc_html_e( 'Fixed:', 'chat-quote-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Position relative to the screen, stays in the same place even after page scroll.', 'chat-quote-for-woocommerce' ); ?>
							<?php if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) : ?>
							<br/><strong><?php esc_html_e( 'Absolute (PRO):', 'chat-quote-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Position relative to page content and moves with scroll.', 'chat-quote-for-woocommerce' ); ?>
							<?php endif; ?>
						</span>
					</div>

					<!-- Position Coordinates (Matching Screenshot 2) -->
					<div class="cqfw-placement-field">
						<label class="cqfw-option-label"><?php esc_html_e( 'Position to Place:', 'chat-quote-for-woocommerce' ); ?></label>
						
						<div class="cqfw-coord-row">
							<div class="cqfw-coord-select">
								<select name="cqfw_chat_styles[pos_v_side]" id="cqfw_pos_v_side" class="cqfw-select">
									<option value="bottom" <?php selected( $settings['pos_v_side'], 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'chat-quote-for-woocommerce' ); ?></option>
									<option value="top" <?php selected( $settings['pos_v_side'], 'top' ); ?>><?php esc_html_e( 'Top', 'chat-quote-for-woocommerce' ); ?></option>
								</select>
							</div>
							<div class="cqfw-coord-input">
								<input type="text" name="cqfw_chat_styles[pos_v_offset]" id="cqfw_pos_v_offset" value="<?php echo esc_attr( $settings['pos_v_offset'] ); ?>" class="cqfw-input-text" placeholder="24px" />
							</div>
						</div>

						<div class="cqfw-coord-row" style="margin-top:12px;">
							<div class="cqfw-coord-select">
								<select name="cqfw_chat_styles[pos_h_side]" id="cqfw_pos_h_side" class="cqfw-select">
									<option value="right" <?php selected( $settings['pos_h_side'], 'right' ); ?>><?php esc_html_e( 'Right', 'chat-quote-for-woocommerce' ); ?></option>
									<option value="left" <?php selected( $settings['pos_h_side'], 'left' ); ?>><?php esc_html_e( 'Left', 'chat-quote-for-woocommerce' ); ?></option>
								</select>
							</div>
							<div class="cqfw-coord-input">
								<input type="text" name="cqfw_chat_styles[pos_h_offset]" id="cqfw_pos_h_offset" value="<?php echo esc_attr( $settings['pos_h_offset'] ); ?>" class="cqfw-input-text" placeholder="24px" />
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Pro Upgrade Modal (premium build / license upsell only) -->
		<?php if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) : ?>
		<div class="cqfw-pro-modal-backdrop" id="cqfw-pro-modal-backdrop" style="display:none;">
			<div class="cqfw-pro-modal" role="dialog" aria-modal="true">
				<div class="cqfw-pro-modal__header">
					<div class="cqfw-pro-modal__icon">✨</div>
					<h3><?php esc_html_e( 'Upgrade to PRO Version', 'chat-quote-for-woocommerce' ); ?></h3>
					<button type="button" class="cqfw-pro-modal__close" id="cqfw-pro-modal-close">&times;</button>
				</div>
				<div class="cqfw-pro-modal__body">
					<p class="cqfw-pro-modal__desc">
						<?php esc_html_e( 'This chat style is exclusively unlocked with Chat Quote for WooCommerce PRO. Upgrade today to unlock all 10+ gorgeous widget & button styles, custom image upload, live multi-agent scheduling, and conversion boosts!', 'chat-quote-for-woocommerce' ); ?>
					</p>
					<ul class="cqfw-pro-modal__features">
						<li>✓ <?php esc_html_e( '10+ High-Converting Chat Button Styles', 'chat-quote-for-woocommerce' ); ?></li>
						<li>✓ <?php esc_html_e( 'Custom Image & Mascot Button Upload', 'chat-quote-for-woocommerce' ); ?></li>
						<li>✓ <?php esc_html_e( 'Per-Agent Time Scheduling & Offline Badges', 'chat-quote-for-woocommerce' ); ?></li>
						<li>✓ <?php esc_html_e( 'Multi-Agent Selection & Live Web Chat', 'chat-quote-for-woocommerce' ); ?></li>
						<li>✓ <?php esc_html_e( 'Full Width Mobile Responsive Layout', 'chat-quote-for-woocommerce' ); ?></li>
					</ul>
				</div>
				<div class="cqfw-pro-modal__footer">
					<a href="<?php echo esc_url( function_exists( 'cqfw_fs' ) ? cqfw_fs()->get_upgrade_url() : 'https://wpchatquote.com/pro' ); ?>" class="button cqfw-btn-upgrade-now" target="_blank" rel="noopener noreferrer">
						🚀 <?php esc_html_e( 'Get Pro Now', 'chat-quote-for-woocommerce' ); ?>
					</a>
					<button type="button" class="button" id="cqfw-pro-modal-cancel"><?php esc_html_e( 'Maybe Later', 'chat-quote-for-woocommerce' ); ?></button>
				</div>
			</div>
		</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render visual preview thumbnails for style cards.
	 *
	 * @param string $style_id Style ID.
	 * @return void
	 */
	public static function render_style_preview_thumbnail( $style_id ) {
		switch ( $style_id ) {
			case 'style_1':
				// Style 1: Theme Button (Text + WhatsApp icon)
				echo '<div class="cqfw-thumb cqfw-thumb--style-1"><span class="cqfw-thumb-wa-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg></span><span class="cqfw-thumb-text">WhatsApp us</span></div>';
				break;
			case 'style_2':
				// Style 2: Square Icon
				echo '<div class="cqfw-thumb cqfw-thumb--style-2"><svg width="28" height="28" viewBox="0 0 24 24" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg></div>';
				break;
			case 'style_3':
				// Style 3: Round Icon
				echo '<div class="cqfw-thumb cqfw-thumb--style-3"><svg width="28" height="28" viewBox="0 0 24 24" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg></div>';
				break;
			case 'style_3_extend':
				// Style 3 Extend: Round Icon with Badge
				echo '<div class="cqfw-thumb cqfw-thumb--style-3-extend"><svg width="28" height="28" viewBox="0 0 24 24" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg><span class="cqfw-thumb-badge">1</span></div>';
				break;
			case 'style_4':
				// Style 4: Chip Button
				echo '<div class="cqfw-thumb cqfw-thumb--style-4"><span class="cqfw-thumb-chip-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg></span><span>WhatsApp us</span></div>';
				break;
			case 'style_5':
				// Style 5: Avatar Slider / Agent
				echo '<div class="cqfw-thumb cqfw-thumb--style-5"><svg width="22" height="22" viewBox="0 0 24 24" fill="#64748b" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span class="cqfw-thumb-avatar-dot"></span></div>';
				break;
			case 'style_6':
				// Style 6: Text Only
				echo '<div class="cqfw-thumb cqfw-thumb--style-6"><span style="color:#2563eb; font-weight:700;">WhatsApp us</span></div>';
				break;
			case 'style_7':
				// Style 7: Rounded Button
				echo '<div class="cqfw-thumb cqfw-thumb--style-7"><svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg></div>';
				break;
			case 'style_7_extend':
				// Style 7 Extend: Rounded Button Extend
				echo '<div class="cqfw-thumb cqfw-thumb--style-7-extend"><svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg></div>';
				break;
			case 'style_8':
				// Style 8: Rect Button
				echo '<div class="cqfw-thumb cqfw-thumb--style-8"><span class="cqfw-thumb-wa-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg></span><span>WhatsApp</span></div>';
				break;
			case 'style_10':
				// Style 10: Multi-Agent Team Pill Badge (100% exact to reference image)
				$settings = self::get_saved_settings();
				$s10_a1 = ! empty( $settings['agent_1_avatar'] ) ? $settings['agent_1_avatar'] : CQFW_URL . 'assets/images/agent-1.png';
				$s10_a2 = ! empty( $settings['agent_2_avatar'] ) ? $settings['agent_2_avatar'] : CQFW_URL . 'assets/images/agent-2.png';
				$s10_a3 = ! empty( $settings['agent_3_avatar'] ) ? $settings['agent_3_avatar'] : CQFW_URL . 'assets/images/agent-3.png';
				echo '<div class="cqfw-thumb cqfw-thumb--style-10">
					<div class="cqfw-thumb-s10-avatars">
						<img class="cqfw-thumb-s10-avatar cqfw-clickable-avatar cqfw-agent-slot-1-img" data-agent-slot="1" src="' . esc_url( $s10_a1 ) . '" alt="" title="' . esc_attr__( 'Click to change Agent 1 image', 'chat-quote-for-woocommerce' ) . '" style="z-index:1;" />
						<img class="cqfw-thumb-s10-avatar cqfw-clickable-avatar cqfw-agent-slot-2-img" data-agent-slot="2" src="' . esc_url( $s10_a2 ) . '" alt="" title="' . esc_attr__( 'Click to change Agent 2 image', 'chat-quote-for-woocommerce' ) . '" style="z-index:2;" />
						<img class="cqfw-thumb-s10-avatar cqfw-clickable-avatar cqfw-agent-slot-3-img" data-agent-slot="3" src="' . esc_url( $s10_a3 ) . '" alt="" title="' . esc_attr__( 'Click to change Agent 3 image', 'chat-quote-for-woocommerce' ) . '" style="z-index:3;" />
						<span class="cqfw-thumb-s10-dot"></span>
					</div>
					<div class="cqfw-thumb-s10-text">
						<span class="cqfw-thumb-s10-title">' . esc_html__( 'Chat with us', 'chat-quote-for-woocommerce' ) . '</span>
						<span class="cqfw-thumb-s10-subtitle">' . esc_html__( '3 agents online', 'chat-quote-for-woocommerce' ) . '</span>
					</div>
					<div class="cqfw-thumb-s10-wa-btn">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg>
					</div>
				</div>';
				break;
			case 'style_11':
				// Style 11: Animated Agent Callout with Speech Bubble and Halo Button
				$settings = self::get_saved_settings();
				$s11_avatar = ! empty( $settings['agent_1_avatar'] ) ? $settings['agent_1_avatar'] : CQFW_URL . 'assets/images/agent-sales.png';
				echo '<div class="cqfw-thumb cqfw-thumb--style-11">
					<div class="cqfw-thumb-s11-bubble">
						<img class="cqfw-thumb-s11-avatar cqfw-clickable-avatar cqfw-agent-slot-1-img" data-agent-slot="1" src="' . esc_url( $s11_avatar ) . '" alt="" title="' . esc_attr__( 'Click to change agent image', 'chat-quote-for-woocommerce' ) . '" />
						<div class="cqfw-thumb-s11-info">
							<div class="cqfw-thumb-s11-status">
								<span class="cqfw-thumb-s11-online">' . esc_html__( 'Online', 'chat-quote-for-woocommerce' ) . '</span>
								<span class="cqfw-thumb-s11-dot"></span>
							</div>
							<span class="cqfw-thumb-s11-role">' . esc_html__( 'Sales Support', 'chat-quote-for-woocommerce' ) . '</span>
						</div>
					</div>
					<div class="cqfw-thumb-s11-btn-wrap">
						<div class="cqfw-thumb-s11-halo"></div>
						<div class="cqfw-thumb-s11-wa-btn">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg>
						</div>
						<span class="cqfw-thumb-s11-badge-dot"></span>
					</div>
				</div>';
				break;
			case 'style_12':
				// Style 12: 3 Agents Callout & Counter Badge
				$settings = self::get_saved_settings();
				$s12_a1 = ! empty( $settings['agent_1_avatar'] ) ? $settings['agent_1_avatar'] : CQFW_URL . 'assets/images/agent-1.png';
				$s12_a2 = ! empty( $settings['agent_2_avatar'] ) ? $settings['agent_2_avatar'] : CQFW_URL . 'assets/images/agent-2.png';
				$s12_a3 = ! empty( $settings['agent_3_avatar'] ) ? $settings['agent_3_avatar'] : CQFW_URL . 'assets/images/agent-3.png';
				echo '<div class="cqfw-thumb cqfw-thumb--style-12">
					<div class="cqfw-thumb-s12-top">
						<div class="cqfw-thumb-s12-card">
							<div class="cqfw-thumb-s12-status">
								<span class="cqfw-thumb-s12-dot"></span>
								<span class="cqfw-thumb-s12-online">' . esc_html__( 'Online now', 'chat-quote-for-woocommerce' ) . '</span>
							</div>
							<div class="cqfw-thumb-s12-title">' . esc_html__( '3 agents available', 'chat-quote-for-woocommerce' ) . '</div>
							<div class="cqfw-thumb-s12-sub">' . esc_html__( 'Tap to chat with our team', 'chat-quote-for-woocommerce' ) . '</div>
						</div>
						<div class="cqfw-thumb-s12-column">
							<div class="cqfw-thumb-s12-agent-item">
								<img class="cqfw-thumb-s12-avatar cqfw-clickable-avatar cqfw-agent-slot-1-img" data-agent-slot="1" src="' . esc_url( $s12_a1 ) . '" alt="" title="' . esc_attr__( 'Click to change Agent 1 image', 'chat-quote-for-woocommerce' ) . '" />
								<span class="cqfw-thumb-s12-agent-dot"></span>
							</div>
							<div class="cqfw-thumb-s12-agent-item">
								<img class="cqfw-thumb-s12-avatar cqfw-clickable-avatar cqfw-agent-slot-2-img" data-agent-slot="2" src="' . esc_url( $s12_a2 ) . '" alt="" title="' . esc_attr__( 'Click to change Agent 2 image', 'chat-quote-for-woocommerce' ) . '" />
								<span class="cqfw-thumb-s12-agent-dot"></span>
							</div>
							<div class="cqfw-thumb-s12-agent-item">
								<img class="cqfw-thumb-s12-avatar cqfw-clickable-avatar cqfw-agent-slot-3-img" data-agent-slot="3" src="' . esc_url( $s12_a3 ) . '" alt="" title="' . esc_attr__( 'Click to change Agent 3 image', 'chat-quote-for-woocommerce' ) . '" />
								<span class="cqfw-thumb-s12-agent-dot"></span>
							</div>
						</div>
					</div>
					<div class="cqfw-thumb-s12-btn-wrap">
						<div class="cqfw-thumb-s12-halo"></div>
						<div class="cqfw-thumb-s12-wa-btn">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg>
						</div>
						<span class="cqfw-thumb-s12-count-badge">3</span>
					</div>
				</div>';
				break;
			case 'style_99':
			default:
				// Style 99: Custom Image
				echo '<div class="cqfw-thumb cqfw-thumb--style-99"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></div>';
				break;
		}
	}
	/**
	 * Render visual preview thumbnails for popup modal style cards.
	 *
	 * @param string $style_id Popup style ID.
	 * @return void
	 */
	public static function render_popup_style_thumbnail( $style_id ) {
		$settings = self::get_saved_settings();
		$saved_agents = class_exists( 'CQFW_Pro_Business_Hours' ) ? CQFW_Pro_Business_Hours::get_agents() : array();

		switch ( $style_id ) {
			case 'style_1_modern_card':
				$agent_1_name = ! empty( $saved_agents[0]['name'] ) ? $saved_agents[0]['name'] : 'DEO';
				$agent_1_dept = ! empty( $saved_agents[0]['department'] ) ? $saved_agents[0]['department'] : 'Tech';
				$thumb_a1_img = ! empty( $saved_agents[0]['avatar'] ) ? $saved_agents[0]['avatar'] : ( ! empty( $settings['agent_1_avatar'] ) ? $settings['agent_1_avatar'] : '' );

				$agent_2_name = ! empty( $saved_agents[1]['name'] ) ? $saved_agents[1]['name'] : 'Prokash';
				$agent_2_dept = ! empty( $saved_agents[1]['department'] ) ? $saved_agents[1]['department'] : 'Support';
				$thumb_a2_img = ! empty( $saved_agents[1]['avatar'] ) ? $saved_agents[1]['avatar'] : ( ! empty( $settings['agent_2_avatar'] ) ? $settings['agent_2_avatar'] : '' );

				$init_1 = mb_strtoupper( mb_substr( $agent_1_name, 0, 1 ) );
				$init_2 = mb_strtoupper( mb_substr( $agent_2_name, 0, 1 ) );
				?>
				<div class="cqfw-popup-thumb cqfw-popup-thumb--modern-card">
					<!-- Blue Header -->
					<div class="cqfw-pthumb-header" style="background:#00a884;">
						<div class="cqfw-pthumb-icon">
							<svg width="20" height="20" viewBox="0 0 44 44" fill="none">
								<path d="M22 6C13.1634 6 6 12.7157 6 21C6 24.4124 7.21808 27.5615 9.30058 30.1009L7.54452 35.808C7.34861 36.4447 7.95529 37.0142 8.58356 36.7944L14.6295 34.6793C16.8524 35.5348 19.3496 36 22 36C30.8366 36 38 29.2843 38 21C38 12.7157 30.8366 6 22 6Z" fill="#FFFFFF"/>
								<circle cx="15" cy="21" r="2.5" fill="#00a884"/>
								<circle cx="22" cy="21" r="2.5" fill="#00a884"/>
								<circle cx="29" cy="21" r="2.5" fill="#00a884"/>
							</svg>
						</div>
						<div class="cqfw-pthumb-title-wrap">
							<span class="cqfw-pthumb-title">Chat with us</span>
							<span class="cqfw-pthumb-sub">Any questions related to Multi Agent?</span>
						</div>
						<span class="cqfw-pthumb-close">&times;</span>
					</div>

					<!-- Agent Cards Body matching Screenshot 1 -->
					<div class="cqfw-pthumb-body">
						<!-- Card 1 -->
						<div class="cqfw-pthumb-agent">
							<div class="cqfw-pthumb-avatar-wrap">
								<?php if ( ! empty( $thumb_a1_img ) ) : ?>
									<img src="<?php echo esc_url( $thumb_a1_img ); ?>" class="cqfw-pthumb-img" alt="" />
								<?php else : ?>
									<span class="cqfw-pthumb-dot s-green"><?php echo esc_html( $init_1 ); ?></span>
								<?php endif; ?>
								<span class="cqfw-pthumb-status-dot"></span>
							</div>
							<div class="cqfw-pthumb-agent-meta">
								<strong><?php echo esc_html( $agent_1_name ); ?></strong>
								<small><?php echo esc_html( $agent_1_dept ); ?></small>
							</div>
							<div class="cqfw-pthumb-wa-wrap">
								<svg width="17" height="17" viewBox="0 0 24 24" fill="#25D366">
									<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
								</svg>
							</div>
						</div>

						<!-- Card 2 (White standard) -->
						<div class="cqfw-pthumb-agent">
							<div class="cqfw-pthumb-avatar-wrap">
								<?php if ( ! empty( $thumb_a2_img ) ) : ?>
									<img src="<?php echo esc_url( $thumb_a2_img ); ?>" class="cqfw-pthumb-img" alt="" />
								<?php else : ?>
									<span class="cqfw-pthumb-dot s-blue"><?php echo esc_html( $init_2 ); ?></span>
								<?php endif; ?>
								<span class="cqfw-pthumb-status-dot"></span>
							</div>
							<div class="cqfw-pthumb-agent-meta">
								<strong><?php echo esc_html( $agent_2_name ); ?></strong>
								<small><?php echo esc_html( $agent_2_dept ); ?></small>
							</div>
							<div class="cqfw-pthumb-wa-wrap">
								<svg width="17" height="17" viewBox="0 0 24 24" fill="#25D366">
									<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
								</svg>
							</div>
						</div>
					</div>

					<!-- Footer Pill Button matching Screenshot 1 -->
					<div class="cqfw-pthumb-footer">
						<div class="cqfw-pthumb-pill">
							<span class="cqfw-pthumb-pill-icon">
								<svg width="13" height="13" viewBox="0 0 24 24" fill="none">
									<circle cx="12" cy="12" r="10" fill="#00a884"/>
									<circle cx="8" cy="12" r="1.3" fill="#FFFFFF"/>
									<circle cx="12" cy="12" r="1.3" fill="#FFFFFF"/>
									<circle cx="16" cy="12" r="1.3" fill="#FFFFFF"/>
								</svg>
							</span>
							<span>Or send message on website</span>
							<span class="cqfw-pthumb-pill-arrow">&rarr;</span>
						</div>
					</div>
				</div>
				<?php
				break;

			case 'classic_whatsapp':
				$agent_1_name = ! empty( $saved_agents[0]['name'] ) ? $saved_agents[0]['name'] : 'DEO';
				$agent_1_dept = ! empty( $saved_agents[0]['department'] ) ? $saved_agents[0]['department'] : 'Tech';
				$thumb_a1_img = ! empty( $saved_agents[0]['avatar'] ) ? $saved_agents[0]['avatar'] : ( ! empty( $settings['agent_1_avatar'] ) ? $settings['agent_1_avatar'] : '' );

				$agent_2_name = ! empty( $saved_agents[1]['name'] ) ? $saved_agents[1]['name'] : 'Prokash';
				$agent_2_dept = ! empty( $saved_agents[1]['department'] ) ? $saved_agents[1]['department'] : 'Support';
				$thumb_a2_img = ! empty( $saved_agents[1]['avatar'] ) ? $saved_agents[1]['avatar'] : ( ! empty( $settings['agent_2_avatar'] ) ? $settings['agent_2_avatar'] : '' );

				$init_1 = mb_strtoupper( mb_substr( $agent_1_name, 0, 1 ) );
				$init_2 = mb_strtoupper( mb_substr( $agent_2_name, 0, 1 ) );
				$thumb_title = ! empty( $settings['chat_popup_title'] ) ? $settings['chat_popup_title'] : __( 'WhatsApp Chat', 'chat-quote-for-woocommerce' );
				?>
				<div class="cqfw-popup-thumb cqfw-popup-thumb--classic">
					<!-- Official WhatsApp Dark Teal Header (#075e54) -->
					<div class="cqfw-pthumb-header" style="background:#075e54;">
						<div class="cqfw-pthumb-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="#ffffff">
								<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
							</svg>
						</div>
						<div class="cqfw-pthumb-title-wrap">
							<span class="cqfw-pthumb-title"><?php echo esc_html( $thumb_title ); ?></span>
							<span class="cqfw-pthumb-sub"><?php esc_html_e( 'Typically replies fast', 'chat-quote-for-woocommerce' ); ?></span>
						</div>
						<span class="cqfw-pthumb-close">&times;</span>
					</div>

					<!-- WhatsApp Beige Wallpaper Body with Green Left-Bar Accent Cards -->
					<div class="cqfw-pthumb-body" style="background:#ece5dd;">
						<!-- Card 1 -->
						<div class="cqfw-pthumb-agent" style="background:#ffffff;border-left:3.5px solid #25d366;">
							<div class="cqfw-pthumb-avatar-wrap">
								<?php if ( ! empty( $thumb_a1_img ) ) : ?>
									<img src="<?php echo esc_url( $thumb_a1_img ); ?>" class="cqfw-pthumb-img" alt="" />
								<?php else : ?>
									<span class="cqfw-pthumb-dot s-green"><?php echo esc_html( $init_1 ); ?></span>
								<?php endif; ?>
								<span class="cqfw-pthumb-status-dot"></span>
							</div>
							<div class="cqfw-pthumb-agent-meta">
								<strong><?php echo esc_html( $agent_1_name ); ?></strong>
								<small><?php echo esc_html( $agent_1_dept ); ?></small>
							</div>
							<div class="cqfw-pthumb-wa-wrap">
								<svg width="17" height="17" viewBox="0 0 24 24" fill="#25D366">
									<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
								</svg>
							</div>
						</div>

						<!-- Card 2 -->
						<div class="cqfw-pthumb-agent" style="background:#ffffff;border-left:3.5px solid #25d366;">
							<div class="cqfw-pthumb-avatar-wrap">
								<?php if ( ! empty( $thumb_a2_img ) ) : ?>
									<img src="<?php echo esc_url( $thumb_a2_img ); ?>" class="cqfw-pthumb-img" alt="" />
								<?php else : ?>
									<span class="cqfw-pthumb-dot s-blue"><?php echo esc_html( $init_2 ); ?></span>
								<?php endif; ?>
								<span class="cqfw-pthumb-status-dot"></span>
							</div>
							<div class="cqfw-pthumb-agent-meta">
								<strong><?php echo esc_html( $agent_2_name ); ?></strong>
								<small><?php echo esc_html( $agent_2_dept ); ?></small>
							</div>
							<div class="cqfw-pthumb-wa-wrap">
								<svg width="17" height="17" viewBox="0 0 24 24" fill="#25D366">
									<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
								</svg>
							</div>
						</div>
					</div>

					<!-- Footer matching Style 2 toolbar -->
					<div class="cqfw-pthumb-footer" style="background:#f0f2f5;">
						<div class="cqfw-pthumb-pill">
							<span class="cqfw-pthumb-pill-icon">
								<svg width="13" height="13" viewBox="0 0 24 24" fill="none">
									<circle cx="12" cy="12" r="10" fill="#00a884"/>
									<circle cx="8" cy="12" r="1.3" fill="#FFFFFF"/>
									<circle cx="12" cy="12" r="1.3" fill="#FFFFFF"/>
									<circle cx="16" cy="12" r="1.3" fill="#FFFFFF"/>
								</svg>
							</span>
							<span>Or send message on website</span>
							<span class="cqfw-pthumb-pill-arrow">&rarr;</span>
						</div>
					</div>
				</div>
				<?php
				break;

			case 'style_3_compact':
				$img_url_1 = CQFW_URL . 'assets/images/style-3-agent-1.png';
				$img_url_2 = CQFW_URL . 'assets/images/style-3-agent-2.png';
				$img_url_3 = CQFW_URL . 'assets/images/style-3-agent-3.png';
				?>
				<div class="cqfw-popup-thumb cqfw-popup-thumb--style-3">
					<!-- White Header -->
					<div class="cqfw-pthumb-s3-header">
						<div class="cqfw-pthumb-s3-top-row">
							<div class="cqfw-pthumb-s3-status">
								<span class="cqfw-pthumb-s3-status-dot"></span>
								<span>We're online</span>
							</div>
							<span class="cqfw-pthumb-s3-close">&times;</span>
						</div>
						<h3 class="cqfw-pthumb-s3-title">Chat with us</h3>
						<p class="cqfw-pthumb-s3-sub">Choose a team member to start a conversation</p>
					</div>

					<!-- Body with 3 Cards matching Reference Image -->
					<div class="cqfw-pthumb-s3-body">
						<!-- Card 1: Sales -->
						<div class="cqfw-pthumb-s3-card">
							<div class="cqfw-pthumb-s3-card-avatar">
								<img src="<?php echo esc_url( $img_url_1 ); ?>" alt="Sales Support" />
								<span class="s3-dot"></span>
							</div>
							<div class="cqfw-pthumb-s3-card-meta">
								<span class="cqfw-pthumb-s3-pill sales">Sales</span>
								<strong>Sales Support</strong>
								<small>Product & Pricing</small>
							</div>
							<div class="cqfw-pthumb-s3-chevron">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
							</div>
						</div>

						<!-- Card 2: Technical Support -->
						<div class="cqfw-pthumb-s3-card">
							<div class="cqfw-pthumb-s3-card-avatar">
								<img src="<?php echo esc_url( $img_url_2 ); ?>" alt="Technical Support" />
								<span class="s3-dot"></span>
							</div>
							<div class="cqfw-pthumb-s3-card-meta">
								<span class="cqfw-pthumb-s3-pill support">Support</span>
								<strong>Technical Support</strong>
								<small>Setup & Troubleshooting</small>
							</div>
							<div class="cqfw-pthumb-s3-chevron">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
							</div>
						</div>

						<!-- Card 3: General Inquiry -->
						<div class="cqfw-pthumb-s3-card">
							<div class="cqfw-pthumb-s3-card-avatar">
								<img src="<?php echo esc_url( $img_url_3 ); ?>" alt="General Inquiry" />
								<span class="s3-dot"></span>
							</div>
							<div class="cqfw-pthumb-s3-card-meta">
								<span class="cqfw-pthumb-s3-pill office">Office</span>
								<strong>General Inquiry</strong>
								<small>Orders & Other</small>
							</div>
							<div class="cqfw-pthumb-s3-chevron">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
							</div>
						</div>
					</div>

					<!-- Footer Button with Website Chat Icon & Arrow -->
					<div class="cqfw-pthumb-s3-footer">
						<div class="cqfw-pthumb-s3-btn">
							<div class="cqfw-pthumb-s3-btn-left">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none">
									<circle cx="12" cy="12" r="10" fill="#00a884"/>
									<circle cx="8" cy="12" r="1.3" fill="#FFFFFF"/>
									<circle cx="12" cy="12" r="1.3" fill="#FFFFFF"/>
									<circle cx="16" cy="12" r="1.3" fill="#FFFFFF"/>
								</svg>
								<span>Or send message on website</span>
							</div>
							<span style="font-size:8px;">&rarr;</span>
						</div>
					</div>
				</div>
				<?php
				break;

			case 'style_4_glass':
				$bot_img_url = CQFW_URL . 'assets/images/style-4-bot.png';
				?>
				<div class="cqfw-popup-thumb cqfw-popup-thumb--style-4">
					<!-- Water-sky gradient header with 3D Bot Mascot -->
					<div class="cqfw-pthumb-s4-header">
						<div class="cqfw-pthumb-s4-header-main">
							<div class="cqfw-pthumb-s4-bot-wrap">
								<span class="cqfw-pthumb-s4-sparkles">&#92; /</span>
								<img src="<?php echo esc_url( $bot_img_url ); ?>" alt="Bot" class="cqfw-pthumb-s4-bot-img" />
							</div>
							<div class="cqfw-pthumb-s4-header-text">
								<span class="cqfw-pthumb-s4-title"><?php esc_html_e( 'Chat with us', 'chat-quote-for-woocommerce' ); ?></span>
								<span class="cqfw-pthumb-s4-sub"><?php esc_html_e( 'Any questions related to Multi Agent?', 'chat-quote-for-woocommerce' ); ?></span>
							</div>
						</div>
						<span class="cqfw-pthumb-s4-close">&times;</span>
					</div>

					<!-- 3 Pastel Department Cards -->
					<div class="cqfw-pthumb-s4-body">
						<!-- Card 1: Sales Support (Mint) -->
						<div class="cqfw-pthumb-s4-card s4-sales">
							<div class="cqfw-pthumb-s4-icon" style="background:#10b981;">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
									<path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
									<path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
									<path d="M8 21v-1a4 4 0 0 1 4-4h2"></path>
								</svg>
							</div>
							<div class="cqfw-pthumb-s4-meta">
								<strong>Sales Support</strong>
								<small>Sales Inquiry</small>
							</div>
							<div class="cqfw-pthumb-s4-chevron" style="background:#dcfce7;color:#10b981;">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
							</div>
						</div>

						<!-- Card 2: Support (Sky Blue) -->
						<div class="cqfw-pthumb-s4-card s4-support">
							<div class="cqfw-pthumb-s4-icon" style="background:#0284c7;">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
									<path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
									<path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
									<path d="M8 21v-1a4 4 0 0 1 4-4h2"></path>
								</svg>
							</div>
							<div class="cqfw-pthumb-s4-meta">
								<strong>Support</strong>
								<small>Support</small>
							</div>
							<div class="cqfw-pthumb-s4-chevron" style="background:#e0f2fe;color:#0284c7;">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
							</div>
						</div>

						<!-- Card 3: Office (Lavender) -->
						<div class="cqfw-pthumb-s4-card s4-office">
							<div class="cqfw-pthumb-s4-icon" style="background:#a855f7;">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="#ffffff">
									<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16h4v2H2v-2h2zm2-2h8V5H6v14zm2-12h4v2H8V7zm0 4h4v2H8v-2zm0 4h4v2H8v-2zm10 0h2v4h-2v-4zm0-4h2v2h-2v-2z"/>
								</svg>
							</div>
							<div class="cqfw-pthumb-s4-meta">
								<strong>Office</strong>
								<small>office</small>
							</div>
							<div class="cqfw-pthumb-s4-chevron" style="background:#f3e8ff;color:#a855f7;">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
							</div>
						</div>
					</div>

					<!-- Footer Pill Button -->
					<div class="cqfw-pthumb-s4-footer">
						<div class="cqfw-pthumb-s4-btn">
							<div class="cqfw-pthumb-s4-btn-left">
								<svg width="11" height="11" viewBox="0 0 24 24" fill="#0084ff">
									<path d="M12 2C6.477 2 2 6.477 2 12c0 1.821.487 3.53 1.338 5L2 22l5.223-1.306C8.63 21.464 10.264 22 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/>
									<circle cx="8" cy="12" r="1.3" fill="#FFFFFF"/>
									<circle cx="12" cy="12" r="1.3" fill="#FFFFFF"/>
									<circle cx="16" cy="12" r="1.3" fill="#FFFFFF"/>
								</svg>
								<span>Or send message on website</span>
							</div>
							<span style="font-size:8px;">&rarr;</span>
						</div>
					</div>
				</div>
				<?php
				break;

			case 'style_5_dark':
				?>
				<div class="cqfw-popup-thumb cqfw-popup-thumb--style-5">
					<!-- Purple to Blue Gradient Header -->
					<div class="cqfw-pthumb-s5-header">
						<div class="cqfw-pthumb-s5-header-main">
							<div class="cqfw-pthumb-s5-bot-bubble">
								<svg width="24" height="24" viewBox="0 0 44 44" fill="none">
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
							<div class="cqfw-pthumb-s5-header-text">
								<span class="cqfw-pthumb-s5-title"><?php esc_html_e( 'Chat with us', 'chat-quote-for-woocommerce' ); ?></span>
								<span class="cqfw-pthumb-s5-sub"><?php esc_html_e( 'Any questions related to Multi Agent?', 'chat-quote-for-woocommerce' ); ?></span>
							</div>
						</div>
						<span class="cqfw-pthumb-s5-close">&times;</span>
					</div>

					<!-- 3 Dark Cards with Neon Accent Bars -->
					<div class="cqfw-pthumb-s5-body">
						<!-- Card 1: Sales Support (Green Accent) -->
						<div class="cqfw-pthumb-s5-card">
							<span class="cqfw-pthumb-s5-accent" style="background:#10b981;"></span>
							<div class="cqfw-pthumb-s5-avatar" style="background:#10b981;">S</div>
							<div class="cqfw-pthumb-s5-meta">
								<strong>Sales Support</strong>
								<small>Sales Inquiry</small>
							</div>
							<div class="cqfw-pthumb-s5-wa">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="#22c55e">
									<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
								</svg>
							</div>
						</div>

						<!-- Card 2: Support (Blue Accent) -->
						<div class="cqfw-pthumb-s5-card">
							<span class="cqfw-pthumb-s5-accent" style="background:#0284c7;"></span>
							<div class="cqfw-pthumb-s5-avatar" style="background:#0284c7;">S</div>
							<div class="cqfw-pthumb-s5-meta">
								<strong>Support</strong>
								<small>Support</small>
							</div>
							<div class="cqfw-pthumb-s5-wa">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="#22c55e">
									<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
								</svg>
							</div>
						</div>

						<!-- Card 3: office (Purple Accent) -->
						<div class="cqfw-pthumb-s5-card">
							<span class="cqfw-pthumb-s5-accent" style="background:#a855f7;"></span>
							<div class="cqfw-pthumb-s5-avatar" style="background:#a855f7;">O</div>
							<div class="cqfw-pthumb-s5-meta">
								<strong>office</strong>
								<small>office</small>
							</div>
							<div class="cqfw-pthumb-s5-wa">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="#22c55e">
									<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
								</svg>
							</div>
						</div>
					</div>

					<!-- Vibrant Gradient Footer Pill -->
					<div class="cqfw-pthumb-s5-footer">
						<div class="cqfw-pthumb-s5-btn">
							<div class="cqfw-pthumb-s5-btn-left">
								<svg width="11" height="11" viewBox="0 0 24 24" fill="none">
									<circle cx="12" cy="12" r="10" stroke="#ffffff" stroke-width="1.8" fill="rgba(255,255,255,0.2)"/>
									<circle cx="8" cy="12" r="1.3" fill="#FFFFFF"/>
									<circle cx="12" cy="12" r="1.3" fill="#FFFFFF"/>
									<circle cx="16" cy="12" r="1.3" fill="#FFFFFF"/>
								</svg>
								<span>Or send message on website</span>
							</div>
							<span style="font-size:8px;color:#ffffff;">&rarr;</span>
						</div>
					</div>
				</div>
				<?php
				break;

			case 'style_6_gradient':
				?>
				<div class="cqfw-popup-thumb cqfw-popup-thumb--style-6">
					<!-- Header: Green Round Chat Icon + Titles + Close Button -->
					<div class="cqfw-pthumb-s6-header">
						<div class="cqfw-pthumb-s6-header-left">
							<div class="cqfw-pthumb-s6-badge">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="#ffffff">
									<path d="M12 3C6.477 3 2 7.029 2 12c0 1.83.612 3.528 1.666 4.93L2.5 21l4.288-1.144C8.163 20.524 10.022 21 12 21c5.523 0 10-4.029 10-9s-4.477-9-10-9z"/>
									<circle cx="8" cy="12" r="1.3" fill="#059669"/>
									<circle cx="12" cy="12" r="1.3" fill="#059669"/>
									<circle cx="16" cy="12" r="1.3" fill="#059669"/>
								</svg>
							</div>
							<div class="cqfw-pthumb-s6-header-text">
								<span class="cqfw-pthumb-s6-title"><?php esc_html_e( 'Chat with us', 'chat-quote-for-woocommerce' ); ?></span>
								<span class="cqfw-pthumb-s6-sub"><?php esc_html_e( 'Any questions related to Multi Agent?', 'chat-quote-for-woocommerce' ); ?></span>
							</div>
						</div>
						<span class="cqfw-pthumb-s6-close">&times;</span>
					</div>

					<!-- Body: 3 White Cards with Online Badge, WhatsApp Icon & Chevron -->
					<div class="cqfw-pthumb-s6-body">
						<!-- Card 1: Sales Support (Green) -->
						<div class="cqfw-pthumb-s6-card">
							<div class="cqfw-pthumb-s6-avatar-wrap" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="12" cy="8" r="3.5"></circle>
									<path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
									<path d="M3 13v-2a9 9 0 0 1 18 0v2"></path>
									<path d="M21 14a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h3zM3 14a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2H3z"></path>
								</svg>
								<span class="cqfw-pthumb-s6-dot"></span>
							</div>
							<div class="cqfw-pthumb-s6-meta">
								<strong>Sales Support</strong>
								<small>Sales Inquiry</small>
								<span class="cqfw-pthumb-s6-online">Online</span>
							</div>
							<div class="cqfw-pthumb-s6-actions">
								<span class="cqfw-pthumb-s6-wa">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="#059669">
										<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
									</svg>
								</span>
								<span class="cqfw-pthumb-s6-arr">&gt;</span>
							</div>
						</div>

						<!-- Card 2: Support (Blue) -->
						<div class="cqfw-pthumb-s6-card">
							<div class="cqfw-pthumb-s6-avatar-wrap" style="background: linear-gradient(135deg, #0284c7 0%, #38bdf8 100%);">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="12" cy="8" r="3.5"></circle>
									<path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
									<path d="M3 13v-2a9 9 0 0 1 18 0v2"></path>
									<path d="M21 14a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h3zM3 14a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2H3z"></path>
								</svg>
								<span class="cqfw-pthumb-s6-dot"></span>
							</div>
							<div class="cqfw-pthumb-s6-meta">
								<strong>Support</strong>
								<small>Support</small>
								<span class="cqfw-pthumb-s6-online">Online</span>
							</div>
							<div class="cqfw-pthumb-s6-actions">
								<span class="cqfw-pthumb-s6-wa">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="#059669">
										<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
									</svg>
								</span>
								<span class="cqfw-pthumb-s6-arr">&gt;</span>
							</div>
						</div>

						<!-- Card 3: Office (Purple) -->
						<div class="cqfw-pthumb-s6-card">
							<div class="cqfw-pthumb-s6-avatar-wrap" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="12" cy="8" r="3.5"></circle>
									<path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
									<path d="M3 13v-2a9 9 0 0 1 18 0v2"></path>
									<path d="M21 14a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h3zM3 14a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2H3z"></path>
								</svg>
								<span class="cqfw-pthumb-s6-dot"></span>
							</div>
							<div class="cqfw-pthumb-s6-meta">
								<strong>Office</strong>
								<small>office</small>
								<span class="cqfw-pthumb-s6-online">Online</span>
							</div>
							<div class="cqfw-pthumb-s6-actions">
								<span class="cqfw-pthumb-s6-wa">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="#059669">
										<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/>
									</svg>
								</span>
								<span class="cqfw-pthumb-s6-arr">&gt;</span>
							</div>
						</div>
					</div>

					<!-- Footer: Green Pill Button with Icon | Text -> -->
					<div class="cqfw-pthumb-s6-footer">
						<div class="cqfw-pthumb-s6-btn">
							<div class="cqfw-pthumb-s6-btn-left">
								<svg width="10" height="10" viewBox="0 0 24 24" fill="#ffffff">
									<path d="M12 3C6.477 3 2 7.029 2 12c0 1.83.612 3.528 1.666 4.93L2.5 21l4.288-1.144C8.163 20.524 10.022 21 12 21c5.523 0 10-4.029 10-9s-4.477-9-10-9z"/>
									<circle cx="8" cy="12" r="1.3" fill="#059669"/>
									<circle cx="12" cy="12" r="1.3" fill="#059669"/>
									<circle cx="16" cy="12" r="1.3" fill="#059669"/>
								</svg>
								<span class="cqfw-pthumb-s6-sep">|</span>
								<span>Or send message on website</span>
							</div>
							<span style="font-size:8px;color:#ffffff;">&rarr;</span>
						</div>
					</div>
				</div>
				<?php
				break;

			default:
				?>
				<div class="cqfw-popup-thumb" style="background:#fafafa;border:1.5px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:5px;padding:10px;">
					<div style="font-size:24px;">✨</div>
					<strong style="font-size:10px;color:#334155;text-transform:uppercase;letter-spacing:0.5px;">PRO STYLE</strong>
					<span style="font-size:8px;color:#64748b;text-align:center;">Interactive Multi-Agent</span>
				</div>
				<?php
				break;
		}
	}
}
