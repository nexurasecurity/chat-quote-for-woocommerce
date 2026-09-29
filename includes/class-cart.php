<?php
/**
 * Cart button and cart message handling.
 *
 * Supports classic shortcode cart hooks and the WooCommerce Cart block
 * (default cart page in modern WooCommerce / block themes).
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_Cart {

	/**
	 * Prevent duplicate output when multiple hooks fire on one request.
	 *
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( ! function_exists( 'cqfw_woocommerce_active' ) || ! cqfw_woocommerce_active() ) {
			return;
		}

		// Classic [woocommerce_cart] / shortcode cart.
		add_action( 'woocommerce_after_cart_totals', array( $this, 'render_cart_button' ), 20 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'render_cart_button' ), 25 );

		// Block-based Cart page (woocommerce/cart) — classic PHP hooks do not run.
		add_filter( 'render_block_woocommerce/proceed-to-checkout-block', array( $this, 'inject_after_block' ), 20 );
		add_filter( 'render_block_woocommerce/cart-totals-block', array( $this, 'inject_after_totals_fallback' ), 20 );
	}

	/**
	 * Echo cart WhatsApp button (classic cart).
	 *
	 * @return void
	 */
	public function render_cart_button() {
		$html = $this->get_cart_button_html();
		if ( '' === $html ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_cart_button_html via wp_kses.
		echo $html;
	}

	/**
	 * Append button after Proceed to Checkout (Cart block).
	 *
	 * @param string $block_content Block HTML.
	 * @return string
	 */
	public function inject_after_block( $block_content ) {
		$html = $this->get_cart_button_html();
		if ( '' === $html ) {
			return $block_content;
		}

		return $block_content . $html;
	}

	/**
	 * Fallback when Proceed to Checkout block is missing/replaced.
	 *
	 * @param string $block_content Block HTML.
	 * @return string
	 */
	public function inject_after_totals_fallback( $block_content ) {
		if ( self::$rendered ) {
			return $block_content;
		}

		$html = $this->get_cart_button_html();
		if ( '' === $html ) {
			return $block_content;
		}

		return $block_content . $html;
	}

	/**
	 * Build escaped cart CTA HTML, or empty string when it should not show.
	 *
	 * @return string
	 */
	private function get_cart_button_html() {
		if ( self::$rendered ) {
			return '';
		}

		$settings = CQFW_Settings::get_settings();

		if ( empty( $settings['enable_cart_button'] ) || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return '';
		}

		$message = $this->build_cart_message();
		$url     = ( new CQFW_Analytics() )->build_whatsapp_url( $message );

		if ( '' === $url ) {
			return '';
		}

		$allowed = array(
			'div'  => array(
				'class' => true,
			),
			'a'    => array(
				'class'             => true,
				'style'             => true,
				'href'              => true,
				'data-cqfw-track'   => true,
				'data-page-type'    => true,
				'data-product-id'   => true,
				'data-whatsapp-url' => true,
			),
			'span' => array(
				'class'       => true,
				'aria-hidden' => true,
				'style'       => true,
			),
			'svg'  => array(
				'class'   => true,
				'width'   => true,
				'height'  => true,
				'viewBox' => true,
				'fill'    => true,
				'xmlns'   => true,
			),
			'path' => array(
				'd' => true,
			),
		);

		$html = sprintf(
			'<div class="cqfw-cart-cta"><a class="button alt cqfw-wa-button cqfw-cta-wa-btn" style="display:flex;justify-content:center;align-items:center;width:100%%;%1$s" href="%2$s" data-cqfw-track="1" data-page-type="cart" data-product-id="" data-whatsapp-url="%2$s">%3$s%4$s</a></div>',
			esc_attr( CQFW_Settings::get_button_inline_style() ),
			esc_url( $url ),
			$this->render_button_icon(),
			$this->render_button_label( __( 'Send Cart to WhatsApp', 'chat-quote-for-woocommerce' ) )
		);

		self::$rendered = true;

		return wp_kses( $html, $allowed );
	}

	/**
	 * Build a cart summary message.
	 *
	 * @return string
	 */
	public function build_cart_message() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		$lines   = array();
		$lines[] = __( 'Hello,', 'chat-quote-for-woocommerce' );
		$lines[] = '';
		$lines[] = __( 'I am interested in the following cart items:', 'chat-quote-for-woocommerce' );

		$index = 1;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			if ( ! $product ) {
				continue;
			}

			$product_id    = $product->get_id();
			$product_name  = $product->get_name();
			$product_price = CQFW_Analytics::format_price_text( $product->get_price() );
			$product_qty   = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;
			$product_sku   = $product->get_sku();
			$product_url   = get_permalink( $product_id );

			$lines[] = sprintf( '%1$d. %2$s', $index, $product_name );
			$lines[] = 'Product: ' . $product_name;
			$lines[] = 'Price: ' . $product_price;
			$lines[] = 'SKU: ' . $product_sku;
			$lines[] = 'Quantity: ' . $product_qty;
			$lines[] = 'URL: ' . $product_url;
			$lines[] = '';
			$index++;
		}

		$lines[] = '';
		$lines[] = __( 'Please provide a quote.', 'chat-quote-for-woocommerce' );

		return trim( implode( "\n", $lines ) );
	}

	/**
	 * Render the configured icon HTML.
	 *
	 * @return string
	 */
	private function render_button_icon() {
		$icon_url = CQFW_Settings::get_button_icon_url();
		if ( '' === $icon_url ) {
			return '<svg class="cqfw-button-svg-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.888-.788-1.489-1.761-1.663-2.06-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.81 11.81 0 0 0 12.05 0C5.495 0 .16 5.333.158 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.332 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z"/></svg>';
		}

		$style = 'background-image:url(' . esc_url_raw( $icon_url ) . ')';

		return sprintf(
			'<span class="cqfw-button-icon" aria-hidden="true" style="%s"></span>',
			esc_attr( $style )
		);
	}

	/**
	 * Render label or screen-reader-only label when an icon is set.
	 *
	 * @param string $label Label text.
	 * @return string
	 */
	private function render_button_label( $label ) {
		if ( '' !== CQFW_Settings::get_button_icon_url() ) {
			return '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
		}

		return '<span class="cqfw-floating-label">' . esc_html( $label ) . '</span>';
	}
}
