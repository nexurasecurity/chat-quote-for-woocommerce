<?php
/**
 * Business model / Go Pro upgrade experience (conversion-focused, WP.org-safe).
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_Upgrade {

	/**
	 * Freemius upgrade / pricing URL.
	 *
	 * @return string
	 */
	public static function get_upgrade_url() {
		if ( function_exists( 'cqfw_fs' ) ) {
			return cqfw_fs()->get_upgrade_url();
		}
		return 'https://wpchatquote.com/pro';
	}

	/**
	 * Trial URL when available.
	 *
	 * @return string
	 */
	public static function get_trial_url() {
		if ( function_exists( 'cqfw_fs' ) && method_exists( cqfw_fs(), 'get_trial_url' ) ) {
			return cqfw_fs()->get_trial_url();
		}
		return self::get_upgrade_url();
	}

	/**
	 * Whether current install may use Pro (premium package + valid license/trial).
	 * Free WordPress.org builds never return true (no premium code / license).
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return function_exists( 'cqfw_fs' )
			&& cqfw_fs()->is__premium_only()
			&& function_exists( 'cqfw_can_use_pro' )
			&& cqfw_can_use_pro();
	}

	/**
	 * Feature matrix rows for Free vs Pro (Click to Chat“style clarity).
	 *
	 * @return array<int,array{section?:string,name:string,free:bool|string,pro:bool|string,tip?:string}>
	 */
	public static function get_feature_matrix() {
		return array(
			array( 'section' => __( 'Basic Features', 'chat-quote-for-woocommerce' ) ),
			array( 'name' => __( 'WhatsApp Number', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true, 'tip' => __( 'Primary store WhatsApp number', 'chat-quote-for-woocommerce' ) ),
			array( 'name' => __( 'Pre-filled Message', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
			array( 'name' => __( 'Web WhatsApp', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
			array( 'name' => __( 'Shortcodes', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
			array( 'name' => __( 'Call to Action / Buy buttons', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
			array( 'name' => __( 'Notification Badge', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),

			array( 'section' => __( 'Styles & Design', 'chat-quote-for-woocommerce' ) ),
			array( 'name' => __( 'Pre-defined Styles', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
			array( 'name' => __( 'Custom Image Button', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Position: Fixed', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
			array( 'name' => __( 'Position: Absolute', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Shop Side-by-Side Buttons', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),

			array( 'section' => __( 'Display & Triggers', 'chat-quote-for-woocommerce' ) ),
			array( 'name' => __( 'Show/Hide by Post type', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
			array( 'name' => __( 'Page-ID & Category rules', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
			array( 'name' => __( 'Device (Desktop / Mobile)', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
			array( 'name' => __( 'Time Delay', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Scroll Delay', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Click / Viewport triggers', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Visitor Country', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Login Status', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),

			array( 'section' => __( 'Greetings & Team', 'chat-quote-for-woocommerce' ) ),
			array( 'name' => __( 'Greeting templates 1 & 2', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
			array( 'name' => __( 'Multi Agent', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Business Hours', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Offline number & CTA', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Random Numbers', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Product-page greeting override', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Form fields / attachments', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),

			array( 'section' => __( 'Analytics & Tracking', 'chat-quote-for-woocommerce' ) ),
			array( 'name' => __( 'Google Analytics', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => __( 'Advanced vars', 'chat-quote-for-woocommerce' ) ),
			array( 'name' => __( 'Facebook Pixel', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => __( 'Advanced vars', 'chat-quote-for-woocommerce' ) ),
			array( 'name' => __( 'Webhooks', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => __( 'Advanced vars', 'chat-quote-for-woocommerce' ) ),
			array( 'name' => __( 'Google Ads Conversion', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),

			array( 'section' => __( 'WooCommerce', 'chat-quote-for-woocommerce' ) ),
			array( 'name' => __( 'Single product & Shop buttons', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
			array( 'name' => __( 'Product Inquiry Button & Form Builder', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Checkout → Place order via WhatsApp', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Quotes → Order / PDF / Export', 'chat-quote-for-woocommerce' ), 'free' => false, 'pro' => true ),
			array( 'name' => __( 'Share & Group chat links', 'chat-quote-for-woocommerce' ), 'free' => true, 'pro' => true ),
		);
	}

	/**
	 * Soft dismissible tip — only on the dedicated Go Pro page (Guideline 11: sparingly).
	 *
	 * @param string $context Page context slug.
	 * @return void
	 */
	public static function render_soft_banner( $context = 'settings' ) {
		// Kept for optional contextual use; default Go Pro flow uses the dedicated page only.
		if ( self::is_pro() ) {
			return;
		}
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$dismiss = get_user_meta( $user_id, 'cqfw_dismiss_upsell_' . sanitize_key( $context ), true );
		if ( '1' === (string) $dismiss ) {
			return;
		}

		$upgrade = self::get_upgrade_url();
		?>
		<div class="cqfw-go-pro-banner notice notice-info is-dismissible" data-cqfw-dismiss="<?php echo esc_attr( $context ); ?>">
			<div class="cqfw-go-pro-banner__inner">
				<div class="cqfw-go-pro-banner__copy">
					<strong><?php esc_html_e( 'Want more WhatsApp tools?', 'chat-quote-for-woocommerce' ); ?></strong>
					<span><?php esc_html_e( 'Pro is an optional separate package (agents, hours, delays, Ads). Your Free features keep working without a license.', 'chat-quote-for-woocommerce' ); ?></span>
				</div>
				<div class="cqfw-go-pro-banner__actions">
					<a class="button button-primary" href="<?php echo esc_url( $upgrade ); ?>"><?php esc_html_e( 'View Pro plans', 'chat-quote-for-woocommerce' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cqfw-go-pro' ) ); ?>"><?php esc_html_e( 'Compare features', 'chat-quote-for-woocommerce' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Full Go Pro page — lives only under plugin settings (Guideline 11).
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			return;
		}

		if ( self::is_pro() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=cqfw-pro' ) );
			exit;
		}

		CQFW_Settings::render_page_header( 'cqfw-go-pro' );

		$upgrade = self::get_upgrade_url();
		$trial   = self::get_trial_url();
		$matrix  = self::get_feature_matrix();
		?>
		<section class="cqfw-panel cqfw-panel--flush">

				<div class="cqfw-toast is-info">
					<p>
						<?php
						echo esc_html__(
							'WordPress.org compliance: every Free feature works without payment. Pro features ship in a separate premium package via Freemius — they are not locked inside the Free WordPress.org build.',
							'chat-quote-for-woocommerce'
						);
						?>
					</p>
				</div>

				<div class="cqfw-go-pro-hero">
					<div class="cqfw-go-pro-hero__text">
						<span class="cqfw-go-pro-pill"><?php esc_html_e( 'Optional Pro add-on', 'chat-quote-for-woocommerce' ); ?></span>
						<h2><?php esc_html_e( 'Upgrade when you need advanced WhatsApp tools', 'chat-quote-for-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Keep using Free for numbers, buttons, greetings, and basic tracking. Choose Pro only if you need multi-agent routing, business hours, smart triggers, Google Ads conversion, and richer WooCommerce chat.', 'chat-quote-for-woocommerce' ); ?></p>
						<div class="cqfw-go-pro-hero__cta">
							<a class="button button-primary button-hero cqfw-btn-buy" href="<?php echo esc_url( $upgrade ); ?>">
								<?php esc_html_e( 'View Pro plans', 'chat-quote-for-woocommerce' ); ?>
							</a>
							<?php if ( $trial && $trial !== $upgrade ) : ?>
								<a class="button button-hero" href="<?php echo esc_url( $trial ); ?>">
									<?php esc_html_e( 'Start free trial', 'chat-quote-for-woocommerce' ); ?>
								</a>
							<?php endif; ?>
						</div>
						<ul class="cqfw-go-pro-trust">
							<li><?php esc_html_e( 'Checkout handled by Freemius (in-dashboard)', 'chat-quote-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Free features never expire or get locked', 'chat-quote-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'No site-wide upgrade nags', 'chat-quote-for-woocommerce' ); ?></li>
						</ul>
					</div>
					<div class="cqfw-go-pro-hero__card">
						<h3><?php esc_html_e( 'Pro highlights', 'chat-quote-for-woocommerce' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Multi-agent WhatsApp routing', 'chat-quote-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Business hours & offline CTA/number', 'chat-quote-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Time / scroll / click / viewport triggers', 'chat-quote-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Country & login display rules', 'chat-quote-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Google Ads + advanced tracking variables', 'chat-quote-for-woocommerce' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="cqfw-go-pro-matrix-wrap">
					<table class="cqfw-go-pro-matrix" role="table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Feature', 'chat-quote-for-woocommerce' ); ?></th>
								<th scope="col" class="is-free"><?php esc_html_e( 'Free', 'chat-quote-for-woocommerce' ); ?></th>
								<th scope="col" class="is-pro"><?php esc_html_e( 'Pro', 'chat-quote-for-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $matrix as $row ) : ?>
							<?php if ( ! empty( $row['section'] ) ) : ?>
								<tr class="cqfw-go-pro-section">
									<td colspan="3"><?php echo esc_html( $row['section'] ); ?></td>
								</tr>
							<?php else : ?>
								<tr>
									<td>
										<span class="cqfw-go-pro-fname"><?php echo esc_html( $row['name'] ); ?></span>
										<?php if ( ! empty( $row['tip'] ) ) : ?>
											<span class="cqfw-go-pro-tip" title="<?php echo esc_attr( $row['tip'] ); ?>">i</span>
										<?php endif; ?>
									</td>
									<td class="is-free"><?php self::render_cell( $row['free'] ); ?></td>
									<td class="is-pro"><?php self::render_cell( $row['pro'] ); ?></td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="cqfw-go-pro-footer-cta">
					<p><?php esc_html_e( 'Optional upgrade — only if you need Pro tools.', 'chat-quote-for-woocommerce' ); ?></p>
					<a class="button button-primary button-hero cqfw-btn-buy" href="<?php echo esc_url( $upgrade ); ?>">
						<?php esc_html_e( 'View Pro plans', 'chat-quote-for-woocommerce' ); ?>
					</a>
				</div>
		</section>
		<?php
		CQFW_Settings::render_page_footer();
	}

	/**
	 * Matrix cell.
	 *
	 * @param bool|string $val Value.
	 * @return void
	 */
	private static function render_cell( $val ) {
		if ( true === $val ) {
			echo '<span class="cqfw-cell-yes" aria-label="' . esc_attr__( 'Included', 'chat-quote-for-woocommerce' ) . '">✓</span>';
			return;
		}
		if ( false === $val ) {
			echo '<span class="cqfw-cell-no" aria-label="' . esc_attr__( 'Not included', 'chat-quote-for-woocommerce' ) . '">✕</span>';
			return;
		}
		echo '<span class="cqfw-cell-yes">✓</span><span class="cqfw-cell-note">' . esc_html( (string) $val ) . '</span>';
	}

	/**
	 * AJAX dismiss soft banner.
	 *
	 * @return void
	 */
	public static function ajax_dismiss_banner() {
		check_ajax_referer( 'cqfw_nonce', 'nonce' );
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			wp_send_json_error( null, 403 );
		}
		$context = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'settings';
		update_user_meta( get_current_user_id(), 'cqfw_dismiss_upsell_' . $context, '1' );
		wp_send_json_success();
	}
}
