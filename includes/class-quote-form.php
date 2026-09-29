<?php
/**
 * Quote Request Form.
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_Quote_Form {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'cqfw_quote_form', array( $this, 'render_shortcode' ) );
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		
		add_action( 'wp_ajax_cqfw_submit_quote', array( $this, 'handle_submit_quote' ) );
		add_action( 'wp_ajax_nopriv_cqfw_submit_quote', array( $this, 'handle_submit_quote' ) );
	}

	/**
	 * Enqueue assets for the quote form.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		global $post;
		
		// Only enqueue if the shortcode is on the page.
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'cqfw_quote_form' ) ) {
			$css_ver = file_exists( CQFW_PATH . 'assets/css/quote-form.css' ) ? (string) filemtime( CQFW_PATH . 'assets/css/quote-form.css' ) : CQFW_VERSION;
			$js_ver  = file_exists( CQFW_PATH . 'assets/js/quote-form.js' ) ? (string) filemtime( CQFW_PATH . 'assets/js/quote-form.js' ) : CQFW_VERSION;

			wp_enqueue_style(
				'cqfw-quote-form',
				CQFW_URL . 'assets/css/quote-form.css',
				array(),
				$css_ver
			);

			wp_enqueue_script(
				'cqfw-quote-form',
				CQFW_URL . 'assets/js/quote-form.js',
				array( 'jquery' ),
				$js_ver,
				true
			);

			wp_localize_script(
				'cqfw-quote-form',
				'cqfwQuoteData',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'cqfw_quote_nonce' ),
				)
			);
		}
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'product_id' => 0,
		), (array) $atts, 'cqfw_quote_form' );

		ob_start();
		?>
		<div class="cqfw-quote-form-wrapper" id="cqfw-quote-form-container">
			<form id="cqfw-quote-form" class="cqfw-quote-form" novalidate>
				<input type="hidden" name="product_id" value="<?php echo absint( $atts['product_id'] ); ?>" />
				
				<!-- Progress Bar -->
				<div class="cqfw-form-progress">
					<div class="cqfw-progress-step is-active" data-step="1">1</div>
					<div class="cqfw-progress-line"></div>
					<div class="cqfw-progress-step" data-step="2">2</div>
					<div class="cqfw-progress-line"></div>
					<div class="cqfw-progress-step" data-step="3">3</div>
				</div>

				<!-- Step 1: Contact Details -->
				<div class="cqfw-form-step is-active" data-step="1">
					<h3 class="cqfw-step-title"><?php esc_html_e( 'Contact Information', 'chat-quote-for-woocommerce' ); ?></h3>
					<p class="cqfw-step-desc"><?php esc_html_e( 'How can we reach you with your quote?', 'chat-quote-for-woocommerce' ); ?></p>
					
					<div class="cqfw-form-group">
						<label for="cqfw_q_name"><?php esc_html_e( 'Full Name *', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" id="cqfw_q_name" name="customer_name" required />
						<span class="cqfw-error-msg"></span>
					</div>
					
					<div class="cqfw-form-group">
						<label for="cqfw_q_email"><?php esc_html_e( 'Email Address *', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="email" id="cqfw_q_email" name="email" required />
						<span class="cqfw-error-msg"></span>
					</div>
					
					<div class="cqfw-form-group">
						<label for="cqfw_q_phone"><?php esc_html_e( 'Phone / WhatsApp *', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="tel" id="cqfw_q_phone" name="phone" required />
						<span class="cqfw-error-msg"></span>
					</div>

					<?php /* Honeypot — leave empty (bots fill this). */ ?>
					<div class="cqfw-hp-field" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
						<label for="cqfw_hp_check"><?php esc_html_e( 'Leave blank', 'chat-quote-for-woocommerce' ); ?></label>
						<input type="text" id="cqfw_hp_check" name="cqfw_hp_check" value="" tabindex="-1" autocomplete="off" />
					</div>
					
					<div class="cqfw-form-actions">
						<button type="button" class="cqfw-btn cqfw-btn-next" data-next="2"><?php esc_html_e( 'Next Step', 'chat-quote-for-woocommerce' ); ?> &rarr;</button>
					</div>
				</div>

				<!-- Step 2: Request Details -->
				<div class="cqfw-form-step" data-step="2">
					<h3 class="cqfw-step-title"><?php esc_html_e( 'Quote Details', 'chat-quote-for-woocommerce' ); ?></h3>
					<p class="cqfw-step-desc"><?php esc_html_e( 'Tell us what you are looking for.', 'chat-quote-for-woocommerce' ); ?></p>
					
					<div class="cqfw-form-row">
						<div class="cqfw-form-group">
							<label for="cqfw_q_qty"><?php esc_html_e( 'Quantity *', 'chat-quote-for-woocommerce' ); ?></label>
							<input type="number" id="cqfw_q_qty" name="quantity" value="1" min="1" required />
						</div>
						<div class="cqfw-form-group">
							<label for="cqfw_q_budget"><?php esc_html_e( 'Target Budget', 'chat-quote-for-woocommerce' ); ?></label>
							<?php $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$'; ?>
							<input type="text" id="cqfw_q_budget" name="budget" placeholder="<?php echo esc_attr( $currency_symbol . '0.00' ); ?>" />
						</div>
					</div>
					
					<div class="cqfw-form-group">
						<label for="cqfw_q_message"><?php esc_html_e( 'Additional Details *', 'chat-quote-for-woocommerce' ); ?></label>
						<textarea id="cqfw_q_message" name="message" rows="4" required placeholder="<?php esc_attr_e( 'Describe your project, variations, or requirements...', 'chat-quote-for-woocommerce' ); ?>"></textarea>
						<span class="cqfw-error-msg"></span>
					</div>

					<?php do_action( 'cqfw_quote_form_after_fields' ); ?>
					
					<div class="cqfw-form-actions cqfw-form-actions-split">
						<button type="button" class="cqfw-btn cqfw-btn-secondary cqfw-btn-prev" data-prev="1">&larr; <?php esc_html_e( 'Back', 'chat-quote-for-woocommerce' ); ?></button>
						<button type="button" class="cqfw-btn cqfw-btn-next" data-next="3"><?php esc_html_e( 'Review Request', 'chat-quote-for-woocommerce' ); ?> &rarr;</button>
					</div>
				</div>

				<!-- Step 3: Review & Submit -->
				<div class="cqfw-form-step" data-step="3">
					<h3 class="cqfw-step-title"><?php esc_html_e( 'Review & Submit', 'chat-quote-for-woocommerce' ); ?></h3>
					<p class="cqfw-step-desc"><?php esc_html_e( 'Please confirm your details before submitting.', 'chat-quote-for-woocommerce' ); ?></p>
					
					<div class="cqfw-quote-summary">
						<p><strong><?php esc_html_e( 'Name:', 'chat-quote-for-woocommerce' ); ?></strong> <span id="cqfw-sum-name"></span></p>
						<p><strong><?php esc_html_e( 'Email:', 'chat-quote-for-woocommerce' ); ?></strong> <span id="cqfw-sum-email"></span></p>
						<p><strong><?php esc_html_e( 'Phone:', 'chat-quote-for-woocommerce' ); ?></strong> <span id="cqfw-sum-phone"></span></p>
						<p><strong><?php esc_html_e( 'Quantity:', 'chat-quote-for-woocommerce' ); ?></strong> <span id="cqfw-sum-qty"></span></p>
						<p><strong><?php esc_html_e( 'Details:', 'chat-quote-for-woocommerce' ); ?></strong></p>
						<div class="cqfw-sum-msg-box" id="cqfw-sum-msg"></div>
						<div id="cqfw-sum-custom-fields" style="margin-top:10px;"></div>
					</div>
					
					<div class="cqfw-form-actions cqfw-form-actions-split">
						<button type="button" class="cqfw-btn cqfw-btn-secondary cqfw-btn-prev" data-prev="2">&larr; <?php esc_html_e( 'Back', 'chat-quote-for-woocommerce' ); ?></button>
						<button type="submit" class="cqfw-btn cqfw-btn-submit" id="cqfw-submit-quote-btn">
							<span class="cqfw-btn-text"><?php esc_html_e( 'Submit Quote Request', 'chat-quote-for-woocommerce' ); ?></span>
							<span class="cqfw-spinner" style="display:none;"></span>
						</button>
					</div>
				</div>
			</form>

			<!-- Success Message -->
			<div class="cqfw-quote-success" id="cqfw-quote-success" style="display:none;">
				<div class="cqfw-success-icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
					</svg>
				</div>
				<h3><?php esc_html_e( 'Request Sent Successfully!', 'chat-quote-for-woocommerce' ); ?></h3>
				<p><?php esc_html_e( 'We have received your quote request and will get back to you shortly.', 'chat-quote-for-woocommerce' ); ?></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle AJAX quote submission.
	 *
	 * @return void
	 */
	public function handle_submit_quote() {
		check_ajax_referer( 'cqfw_quote_nonce', 'nonce' );

		/**
		 * Pro anti-spam / rate limit (and other pre-checks) run here.
		 * Handlers may call wp_send_json_error() and exit.
		 */
		do_action( 'cqfw_before_process_quote_submission' );

		$name     = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$qty      = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 1;
		$budget   = isset( $_POST['budget'] ) ? sanitize_text_field( wp_unslash( $_POST['budget'] ) ) : '';
		$message  = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;

		if ( empty( $name ) || empty( $email ) || empty( $phone ) || empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill out all required fields.', 'chat-quote-for-woocommerce' ) ) );
		}

		$data = array(
			'customer_name' => $name,
			'email'         => $email,
			'phone'         => $phone,
			'quantity'      => $qty,
			'budget'        => $budget,
			'message'       => $message,
			'product_id'    => $product_id ? $product_id : null,
			'status'        => 'new',
			'created_at'    => current_time( 'mysql', true ),
			'updated_at'    => current_time( 'mysql', true ),
		);

		// Allow Pro modules to append custom_fields and attachments
		$data = apply_filters( 'cqfw_process_quote_submission_data', $data, $_FILES );

		global $wpdb;
		$table = CQFW_Analytics::get_table( 'quotes' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, $data );

		if ( $inserted ) {
			$quote_id = $wpdb->insert_id;
			// Free admin email only when Pro notifications are not handling it (avoid duplicates).
			$pro_handles_mail = function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro() && class_exists( 'CQFW_Pro_Notifications' );
			if ( ! $pro_handles_mail ) {
				$this->send_admin_notification( $quote_id, $name, $email, $message );
			}
			do_action( 'cqfw_quote_created', $quote_id, $data );

			wp_send_json_success( array( 'message' => __( 'Quote request submitted.', 'chat-quote-for-woocommerce' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Database error. Please try again.', 'chat-quote-for-woocommerce' ) ) );
		}
	}

	/**
	 * Send email notification to admin.
	 *
	 * @param int    $quote_id The quote ID.
	 * @param string $name     Customer name.
	 * @param string $email    Customer email.
	 * @param string $message  Customer message.
	 * @return void
	 */
	private function send_admin_notification( $quote_id, $name, $email, $message ) {
		$admin_email = get_option( 'admin_email' );
		$subject     = sprintf(
			/* translators: %d: quote ID */
			__( 'New Quote Request #%d', 'chat-quote-for-woocommerce' ),
			$quote_id
		);
		$body        = sprintf(
			/* translators: 1: customer name, 2: customer email, 3: message, 4: quotes admin url */
			__( "You have received a new quote request.\n\nName: %1\$s\nEmail: %2\$s\nMessage:\n%3\$s\n\nManage quotes here: %4\$s", 'chat-quote-for-woocommerce' ),
			$name,
			$email,
			$message,
			admin_url( 'admin.php?page=cqfw-quotes' )
		);
		$headers     = array( 'Content-Type: text/plain; charset=UTF-8' );

		if ( class_exists( 'CQFW_Email_Notify' ) ) {
			CQFW_Email_Notify::send_mail( $admin_email, $subject, $body, $headers );
		} else {
			wp_mail( $admin_email, $subject, $body, $headers );
		}
	}
}
