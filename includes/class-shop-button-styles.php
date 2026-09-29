<?php
/**
 * Shop / product WhatsApp CTA button styles.
 * Admin preview uses the same markup + CSS classes as the frontend.
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_Shop_Button_Styles {

	/**
	 * Style definitions.
	 * Free styles always ship. Pro styles are wrapped in is__premium_only() so
	 * Freemius strips them from the WordPress.org free ZIP (Guideline 5 / no trialware).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_all_styles() {
		$styles = array(
			'stack_outline'      => array(
				'id'             => 'stack_outline',
				'name'           => __( 'Style 1', 'chat-quote-for-woocommerce' ),
				'subtitle'       => __( 'Stack Outline Green', 'chat-quote-for-woocommerce' ),
				'description'    => __( 'Add to Cart on top, outlined WhatsApp buy button below.', 'chat-quote-for-woocommerce' ),
				'layout'         => 'stack',
				'wa_variant'     => 'outline',
				'atc_bg'         => '#22c55e',
				'atc_text'       => '#ffffff',
				'wa_bg'          => '#ffffff',
				'wa_text'        => '#22c55e',
				'wa_border'      => '#22c55e',
				'radius'         => '999px',
				'label_template' => __( 'Buy {product_name} via WhatsApp', 'chat-quote-for-woocommerce' ),
				'is_pro'         => false,
			),
			'stack_outline_blue' => array(
				'id'             => 'stack_outline_blue',
				'name'           => __( 'Style 2', 'chat-quote-for-woocommerce' ),
				'subtitle'       => __( 'Stack Outline Blue', 'chat-quote-for-woocommerce' ),
				'description'    => __( 'Blue Add to Cart with green outlined WhatsApp button stacked below.', 'chat-quote-for-woocommerce' ),
				'layout'         => 'stack',
				'wa_variant'     => 'outline',
				'atc_bg'         => '#3b82f6',
				'atc_text'       => '#ffffff',
				'wa_bg'          => '#ffffff',
				'wa_text'        => '#22c55e',
				'wa_border'      => '#22c55e',
				'radius'         => '999px',
				'label_template' => __( 'Buy {product_name} via WhatsApp', 'chat-quote-for-woocommerce' ),
				'is_pro'         => false,
			),
		);

		// Premium-only shop styles — removed from free package by Freemius preprocessor.
		if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) {
			$styles['side_by_side'] = array(
				'id'             => 'side_by_side',
				'name'           => __( 'Style 3', 'chat-quote-for-woocommerce' ),
				'subtitle'       => __( 'Side by Side Card', 'chat-quote-for-woocommerce' ),
				'description'    => __( 'Product card with Add to Cart and solid Buy button side by side.', 'chat-quote-for-woocommerce' ),
				'layout'         => 'row',
				'wa_variant'     => 'solid',
				'atc_bg'         => '#3b82f6',
				'atc_text'       => '#ffffff',
				'wa_bg'          => '#22c55e',
				'wa_text'        => '#ffffff',
				'wa_border'      => '#22c55e',
				'radius'         => '10px',
				'label_template' => __( 'Buy {product_name}', 'chat-quote-for-woocommerce' ),
				'show_card'      => true,
				'is_pro'         => true,
			);
			$styles['custom'] = array(
				'id'             => 'custom',
				'name'           => __( 'Custom', 'chat-quote-for-woocommerce' ),
				'subtitle'       => __( 'Build your own', 'chat-quote-for-woocommerce' ),
				'description'    => __( 'Fully customize layout, colors, radius, and button text.', 'chat-quote-for-woocommerce' ),
				'layout'         => 'stack',
				'wa_variant'     => 'outline',
				'atc_bg'         => '#3b82f6',
				'atc_text'       => '#ffffff',
				'wa_bg'          => '#ffffff',
				'wa_text'        => '#0f766e',
				'wa_border'      => '#0f766e',
				'radius'         => '999px',
				'label_template' => __( 'Buy {product_name} via WhatsApp', 'chat-quote-for-woocommerce' ),
				'is_pro'         => true,
			);
		}

		return $styles;
	}

	/**
	 * Active style ID from settings (falls back safely for free builds).
	 *
	 * @return string
	 */
	public static function get_active_style_id() {
		$settings = CQFW_Settings::get_settings();
		$style_id = ! empty( $settings['shop_button_style'] ) ? sanitize_key( $settings['shop_button_style'] ) : 'stack_outline';
		$all      = self::get_all_styles();

		if ( ! isset( $all[ $style_id ] ) ) {
			return 'stack_outline';
		}

		// Premium style without license (premium ZIP only) → fall back to free.
		if ( ! empty( $all[ $style_id ]['is_pro'] ) && function_exists( 'cqfw_can_use_pro' ) && ! cqfw_can_use_pro() ) {
			return 'stack_outline';
		}

		return $style_id;
	}

	/**
	 * Resolved style config (merges Custom overrides when Pro).
	 *
	 * @param string|null $style_id Optional style id.
	 * @return array<string,mixed>
	 */
	public static function get_style_config( $style_id = null ) {
		$all      = self::get_all_styles();
		$style_id = $style_id ? sanitize_key( $style_id ) : self::get_active_style_id();

		if ( ! isset( $all[ $style_id ] ) ) {
			$style_id = 'stack_outline';
		}

		$config   = $all[ $style_id ];
		$settings = CQFW_Settings::get_settings();

		if ( 'custom' === $style_id && function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro() ) {
			$layout = isset( $settings['shop_custom_layout'] ) ? sanitize_key( $settings['shop_custom_layout'] ) : 'stack';
			$config['layout'] = in_array( $layout, array( 'stack', 'row' ), true ) ? $layout : 'stack';

			$variant = isset( $settings['shop_custom_wa_variant'] ) ? sanitize_key( $settings['shop_custom_wa_variant'] ) : 'outline';
			$config['wa_variant'] = in_array( $variant, array( 'solid', 'outline' ), true ) ? $variant : 'outline';

			if ( ! empty( $settings['shop_custom_atc_bg'] ) ) {
				$config['atc_bg'] = $settings['shop_custom_atc_bg'];
			}
			if ( ! empty( $settings['shop_custom_wa_bg'] ) ) {
				$config['wa_bg'] = $settings['shop_custom_wa_bg'];
			}
			if ( ! empty( $settings['shop_custom_wa_text'] ) ) {
				$config['wa_text'] = $settings['shop_custom_wa_text'];
			}
			if ( ! empty( $settings['shop_custom_wa_border'] ) ) {
				$config['wa_border'] = $settings['shop_custom_wa_border'];
			}
			if ( ! empty( $settings['shop_custom_radius'] ) ) {
				$config['radius'] = $settings['shop_custom_radius'];
			}
			if ( ! empty( $settings['shop_custom_label'] ) ) {
				$config['label_template'] = $settings['shop_custom_label'];
			}
		}

		// Optional label override for free stack presets only.
		// Side-by-side Pro style always uses its own "Buy {product_name}" template (matches admin preview).
		if (
			'custom' !== $style_id
			&& 'side_by_side' !== $style_id
			&& ! empty( $settings['shop_button_text'] )
		) {
			$config['label_template'] = $settings['shop_button_text'];
		}

		return $config;
	}

	/**
	 * Replace {product_name} in label template.
	 *
	 * @param string $template     Label template.
	 * @param string $product_name Product name.
	 * @return string
	 */
	public static function resolve_label( $template, $product_name ) {
		$name = $product_name ? $product_name : __( 'Product', 'chat-quote-for-woocommerce' );
		return str_replace( '{product_name}', $name, $template );
	}

	/**
	 * Inline style string for the WhatsApp CTA.
	 *
	 * @param array<string,mixed> $config Style config.
	 * @return string
	 */
	public static function get_wa_inline_style( $config ) {
		$bg     = ! empty( $config['wa_bg'] ) ? $config['wa_bg'] : '#ffffff';
		$text   = ! empty( $config['wa_text'] ) ? $config['wa_text'] : '#22c55e';
		$border = ! empty( $config['wa_border'] ) ? $config['wa_border'] : $text;
		$radius = ! empty( $config['radius'] ) ? $config['radius'] : '999px';

		if ( 'solid' === ( $config['wa_variant'] ?? 'outline' ) ) {
			return sprintf(
				'background-color:%1$s !important;color:%2$s !important;border:1px solid %3$s !important;border-radius:%4$s !important;',
				esc_attr( $bg ),
				esc_attr( $text ),
				esc_attr( $border ),
				esc_attr( $radius )
			);
		}

		return sprintf(
			'background-color:%1$s !important;color:%2$s !important;border:2px solid %3$s !important;border-radius:%4$s !important;box-shadow:none !important;',
			esc_attr( $bg ),
			esc_attr( $text ),
			esc_attr( $border ),
			esc_attr( $radius )
		);
	}

	/**
	 * CSS variables for ATC pairing / layout helpers.
	 *
	 * @param array<string,mixed>|null $config Style config.
	 * @return string
	 */
	public static function get_css_variables( $config = null ) {
		$config = $config ? $config : self::get_style_config();
		$vars   = array(
			'--cqfw-cta-atc-bg:' . ( $config['atc_bg'] ?? '#22c55e' ),
			'--cqfw-cta-atc-text:' . ( $config['atc_text'] ?? '#ffffff' ),
			'--cqfw-cta-wa-bg:' . ( $config['wa_bg'] ?? '#ffffff' ),
			'--cqfw-cta-wa-text:' . ( $config['wa_text'] ?? '#22c55e' ),
			'--cqfw-cta-wa-border:' . ( $config['wa_border'] ?? '#22c55e' ),
			'--cqfw-cta-radius:' . ( $config['radius'] ?? '999px' ),
		);
		return implode( ';', array_map( 'esc_attr', $vars ) );
	}

	/**
	 * Wrapper class list for a CTA block.
	 *
	 * @param array<string,mixed>|null $config Style config.
	 * @param string                   $context product|shop|preview.
	 * @return string
	 */
	public static function get_wrapper_classes( $config = null, $context = 'shop' ) {
		$config  = $config ? $config : self::get_style_config();
		$layout  = ! empty( $config['layout'] ) ? $config['layout'] : 'stack';
		$variant = ! empty( $config['wa_variant'] ) ? $config['wa_variant'] : 'outline';
		$style   = ! empty( $config['id'] ) ? $config['id'] : 'stack_outline';

		$classes = array(
			'cqfw-shop-cta',
			'cqfw-cta-wrap',
			'cqfw-cta-layout--' . sanitize_html_class( $layout ),
			'cqfw-cta-variant--' . sanitize_html_class( $variant ),
			'cqfw-cta-style--' . sanitize_html_class( $style ),
			'cqfw-cta-context--' . sanitize_html_class( $context ),
		);

		return implode( ' ', $classes );
	}

	/**
	 * Body class so themes can pair Add to Cart with our CTA layout.
	 *
	 * @param array<int,string> $classes Body classes.
	 * @return array<int,string>
	 */
	public static function filter_body_class( $classes ) {
		if ( is_admin() ) {
			return $classes;
		}

		$settings = CQFW_Settings::get_settings();
		$enabled  = ! empty( $settings['enable_shop_button'] ) || ! empty( $settings['enable_product_button'] );
		if ( ! $enabled ) {
			return $classes;
		}

		$config    = self::get_style_config();
		$classes[] = 'cqfw-cta-active';
		$classes[] = 'cqfw-cta-layout--' . sanitize_html_class( $config['layout'] ?? 'stack' );
		$classes[] = 'cqfw-cta-style--' . sanitize_html_class( $config['id'] ?? 'stack_outline' );

		return $classes;
	}

	/**
	 * Admin style picker (same preview markup as frontend).
	 *
	 * @return void
	 */
	public static function render_admin_picker() {
		$is_pro       = function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() && function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro();
		$all_styles   = self::get_all_styles();
		$active_style = self::get_active_style_id();
		$settings     = CQFW_Settings::get_settings();
		$shop_on      = ! empty( $settings['enable_shop_button'] );
		?>
		<div id="cqfw-shop-style-picker" class="cqfw-shop-style-picker" style="<?php echo $shop_on ? '' : 'display:none;'; ?>">
			<input type="hidden" name="<?php echo esc_attr( CQFW_Settings::OPTION_NAME ); ?>[shop_button_style]" id="cqfw_shop_button_style_input" value="<?php echo esc_attr( $active_style ); ?>" />

			<div class="cqfw-card-section" style="margin-top:20px;">
				<div class="cqfw-section-heading">
					<div class="cqfw-section-step">*</div>
					<div>
						<h2><?php esc_html_e( 'Select Shop Button Style', 'chat-quote-for-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Click a style. The same layout appears on your shop and product pages for customers.', 'chat-quote-for-woocommerce' ); ?></p>
					</div>
				</div>

				<div class="cqfw-styles-grid cqfw-shop-styles-grid">
					<?php foreach ( $all_styles as $s_id => $style ) :
						$is_selected = ( $s_id === $active_style );
						$is_locked   = ( ! empty( $style['is_pro'] ) && ! $is_pro );
						?>
					<div class="cqfw-style-card cqfw-shop-style-card <?php echo $is_selected ? 'is-active' : ''; ?> <?php echo $is_locked ? 'is-pro-locked' : ''; ?>"
						 data-shop-style-id="<?php echo esc_attr( $s_id ); ?>"
						 data-is-pro="<?php echo ! empty( $style['is_pro'] ) ? '1' : '0'; ?>">

						<div class="cqfw-style-card__status-check">
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
						</div>

						<?php if ( ! empty( $style['is_pro'] ) ) : ?>
							<span class="cqfw-style-badge pro"><?php esc_html_e( 'PRO', 'chat-quote-for-woocommerce' ); ?></span>
						<?php else : ?>
							<span class="cqfw-style-badge free"><?php esc_html_e( 'FREE', 'chat-quote-for-woocommerce' ); ?></span>
						<?php endif; ?>

						<div class="cqfw-style-card__preview cqfw-shop-style-preview">
							<?php self::render_preview_markup( $s_id, __( 'Headphones', 'chat-quote-for-woocommerce' ) ); ?>
						</div>

						<div class="cqfw-style-card__footer">
							<strong class="cqfw-style-name"><?php echo esc_html( $style['name'] ); ?></strong>
							<span class="cqfw-style-subtitle"><?php echo esc_html( $style['subtitle'] ); ?></span>
						</div>

						<div class="cqfw-style-card__action-bar">
							<?php if ( $is_locked ) : ?>
								<span class="cqfw-style-btn unlock">🔒 <?php esc_html_e( 'Unlock PRO', 'chat-quote-for-woocommerce' ); ?></span>
							<?php else : ?>
								<span class="cqfw-style-btn customize">✓ <?php esc_html_e( 'Select', 'chat-quote-for-woocommerce' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<p class="description" style="margin-top:14px;">
					<?php esc_html_e( 'Tip: Use {product_name} in the label to insert the product title automatically.', 'chat-quote-for-woocommerce' ); ?>
				</p>

				<table class="form-table" role="presentation" style="margin-top:8px;">
					<tr>
						<th scope="row">
							<label for="cqfw_shop_button_text"><?php esc_html_e( 'Shop Button Text', 'chat-quote-for-woocommerce' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="cqfw_shop_button_text"
								name="<?php echo esc_attr( CQFW_Settings::OPTION_NAME ); ?>[shop_button_text]"
								value="<?php echo esc_attr( isset( $settings['shop_button_text'] ) ? $settings['shop_button_text'] : '' ); ?>"
								placeholder="<?php echo esc_attr__( 'Buy {product_name} via WhatsApp', 'chat-quote-for-woocommerce' ); ?>" />
						</td>
					</tr>
				</table>

				<?php if ( function_exists( 'cqfw_fs' ) && cqfw_fs()->is__premium_only() ) : ?>
					<div id="cqfw-shop-custom-options" class="cqfw-shop-custom-options" style="<?php echo ( 'custom' === $active_style && $is_pro ) ? '' : 'display:none;'; ?>">
						<h3 style="margin:20px 0 8px;"><?php esc_html_e( 'Custom Style Options (PRO)', 'chat-quote-for-woocommerce' ); ?></h3>
						<?php if ( ! $is_pro ) : ?>
							<p class="description"><?php esc_html_e( 'Upgrade to PRO to unlock custom shop button styling.', 'chat-quote-for-woocommerce' ); ?></p>
						<?php else : ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Layout', 'chat-quote-for-woocommerce' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( CQFW_Settings::OPTION_NAME ); ?>[shop_custom_layout]">
										<option value="stack" <?php selected( $settings['shop_custom_layout'] ?? 'stack', 'stack' ); ?>><?php esc_html_e( 'Stack (vertical)', 'chat-quote-for-woocommerce' ); ?></option>
										<option value="row" <?php selected( $settings['shop_custom_layout'] ?? 'stack', 'row' ); ?>><?php esc_html_e( 'Side by Side', 'chat-quote-for-woocommerce' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'WhatsApp Button Style', 'chat-quote-for-woocommerce' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( CQFW_Settings::OPTION_NAME ); ?>[shop_custom_wa_variant]">
										<option value="outline" <?php selected( $settings['shop_custom_wa_variant'] ?? 'outline', 'outline' ); ?>><?php esc_html_e( 'Outline', 'chat-quote-for-woocommerce' ); ?></option>
										<option value="solid" <?php selected( $settings['shop_custom_wa_variant'] ?? 'outline', 'solid' ); ?>><?php esc_html_e( 'Solid', 'chat-quote-for-woocommerce' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Add to Cart Color', 'chat-quote-for-woocommerce' ); ?></th>
								<td><input type="text" class="cqfw-color-field" name="<?php echo esc_attr( CQFW_Settings::OPTION_NAME ); ?>[shop_custom_atc_bg]" value="<?php echo esc_attr( $settings['shop_custom_atc_bg'] ?? '#3b82f6' ); ?>" data-default-color="#3b82f6" /></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'WhatsApp Background', 'chat-quote-for-woocommerce' ); ?></th>
								<td><input type="text" class="cqfw-color-field" name="<?php echo esc_attr( CQFW_Settings::OPTION_NAME ); ?>[shop_custom_wa_bg]" value="<?php echo esc_attr( $settings['shop_custom_wa_bg'] ?? '#ffffff' ); ?>" data-default-color="#ffffff" /></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'WhatsApp Text Color', 'chat-quote-for-woocommerce' ); ?></th>
								<td><input type="text" class="cqfw-color-field" name="<?php echo esc_attr( CQFW_Settings::OPTION_NAME ); ?>[shop_custom_wa_text]" value="<?php echo esc_attr( $settings['shop_custom_wa_text'] ?? '#0f766e' ); ?>" data-default-color="#0f766e" /></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'WhatsApp Border Color', 'chat-quote-for-woocommerce' ); ?></th>
								<td><input type="text" class="cqfw-color-field" name="<?php echo esc_attr( CQFW_Settings::OPTION_NAME ); ?>[shop_custom_wa_border]" value="<?php echo esc_attr( $settings['shop_custom_wa_border'] ?? '#0f766e' ); ?>" data-default-color="#0f766e" /></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Border Radius', 'chat-quote-for-woocommerce' ); ?></th>
								<td><input type="text" class="regular-text" name="<?php echo esc_attr( CQFW_Settings::OPTION_NAME ); ?>[shop_custom_radius]" value="<?php echo esc_attr( $settings['shop_custom_radius'] ?? '999px' ); ?>" placeholder="999px" /></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Custom Label', 'chat-quote-for-woocommerce' ); ?></th>
								<td>
									<input type="text" class="regular-text" name="<?php echo esc_attr( CQFW_Settings::OPTION_NAME ); ?>[shop_custom_label]" value="<?php echo esc_attr( $settings['shop_custom_label'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Buy {product_name} via WhatsApp', 'chat-quote-for-woocommerce' ); ?>" />
								</td>
							</tr>
						</table>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Preview markup — identical structure/classes to frontend CTA.
	 *
	 * @param string $style_id     Style ID.
	 * @param string $product_name Sample product name.
	 * @return void
	 */
	public static function render_preview_markup( $style_id, $product_name = 'Headphones' ) {
		$config = self::get_style_config( $style_id );
		// For card thumbnails, always use the preset defaults (ignore global overrides / live custom save).
		$all = self::get_all_styles();
		if ( isset( $all[ $style_id ] ) ) {
			$config = $all[ $style_id ];
		}

		if ( 'side_by_side' === $style_id && ( ! $product_name || 'Headphones' === $product_name ) ) {
			$product_name = 'Lambdoll';
		}

		$label    = self::resolve_label( $config['label_template'], $product_name );
		$classes  = self::get_wrapper_classes( $config, 'preview' );
		$vars     = self::get_css_variables( $config );
		$wa_style = self::get_wa_inline_style( $config );
		$atc_style = sprintf(
			'background-color:%1$s;color:%2$s;border-radius:%3$s;',
			esc_attr( $config['atc_bg'] ),
			esc_attr( $config['atc_text'] ),
			esc_attr( $config['radius'] )
		);
		$show_card = ! empty( $config['show_card'] ) || 'side_by_side' === $style_id;
		?>
		<?php if ( $show_card ) : ?>
		<div class="cqfw-cta-card-preview">
			<div class="cqfw-cta-card-image" aria-hidden="true">
				<svg width="36" height="36" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
					<ellipse cx="32" cy="40" rx="16" ry="14" fill="#c4a484"/>
					<circle cx="32" cy="24" r="12" fill="#d4b896"/>
					<circle cx="20" cy="18" r="7" fill="#c4a484"/>
					<circle cx="44" cy="18" r="7" fill="#c4a484"/>
					<circle cx="28" cy="23" r="1.5" fill="#5c4033"/>
					<circle cx="36" cy="23" r="1.5" fill="#5c4033"/>
					<ellipse cx="32" cy="28" rx="2" ry="1.5" fill="#a67c52"/>
				</svg>
			</div>
		<?php endif; ?>
		<div class="<?php echo esc_attr( $classes ); ?>" style="<?php echo esc_attr( $vars ); ?>">
			<span class="cqfw-cta-atc-mock" style="<?php echo esc_attr( $atc_style ); ?>">
				<span><?php esc_html_e( 'Add to Cart', 'chat-quote-for-woocommerce' ); ?></span>
			</span>
			<a class="cqfw-inline-btn cqfw-cta-wa-btn" href="#" onclick="return false;" style="<?php echo esc_attr( $wa_style ); ?>">
				<span class="cqfw-floating-label"><?php echo esc_html( $label ); ?></span>
			</a>
		</div>
		<?php if ( $show_card ) : ?>
		</div>
		<?php endif; ?>
		<?php
	}
}
