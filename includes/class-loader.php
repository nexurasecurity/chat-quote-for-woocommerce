<?php
/**
 * Loader class.
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_Loader {

	/**
	 * Component instances.
	 *
	 * @var array<string,object>
	 */
	private $components = array();

	/**
	 * Register hooks for all plugin components.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$this->load_components();

		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'register_hooks' ) ) {
				$component->register_hooks();
			}
		}
	}

	/**
	 * Load and instantiate plugin components.
	 *
	 * @return void
	 */
	private function load_components() {
		$this->components['settings']      = new CQFW_Settings();
		$this->components['analytics']     = new CQFW_Analytics();
		$this->components['whatsapp']      = new CQFW_WhatsApp();
		$this->components['cart']          = new CQFW_Cart();
		$this->components['dashboard']     = new CQFW_Dashboard();
		$this->components['quote_form']    = new CQFW_Quote_Form();
		$this->components['quote_manager'] = new CQFW_Quote_Manager();
		$this->components['chat_styles']      = new CQFW_Chat_Styles();
		$this->components['widget_controls']  = new CQFW_Widget_Controls();
		$this->components['email_notify']     = new CQFW_Email_Notify();
		// Upgrade class is static helpers; register AJAX dismiss.
		add_action( 'wp_ajax_cqfw_dismiss_upsell', array( 'CQFW_Upgrade', 'ajax_dismiss_banner' ) );
	}
}
