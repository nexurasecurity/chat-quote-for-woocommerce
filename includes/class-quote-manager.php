<?php
/**
 * Admin Quote Management page.
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CQFW_Quote_Manager {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_cqfw_update_quote', array( $this, 'handle_quote_update' ) );
		add_action( 'admin_post_cqfw_delete_quote', array( $this, 'handle_quote_delete' ) );
	}

	/**
	 * Register the Quotes menu page.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		$pages = class_exists( 'CQFW_Settings' ) ? CQFW_Settings::get_admin_pages() : array();
		add_submenu_page(
			'cqfw-settings',
			isset( $pages['cqfw-quotes']['title'] ) ? $pages['cqfw-quotes']['title'] : __( 'Quote Requests', 'chat-quote-for-woocommerce' ),
			isset( $pages['cqfw-quotes']['menu'] ) ? $pages['cqfw-quotes']['menu'] : __( 'Quote Requests', 'chat-quote-for-woocommerce' ),
			cqfw_get_admin_capability(),
			'cqfw-quotes',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the page content.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action   = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$quote_id = isset( $_GET['quote_id'] ) ? absint( wp_unslash( $_GET['quote_id'] ) ) : 0;

		echo '<div class="wrap">';

		if ( 'view' === $action && $quote_id ) {
			$this->render_view_quote( $quote_id );
		} else {
			if ( class_exists( 'CQFW_Settings' ) ) {
				CQFW_Settings::render_page_header( 'cqfw-quotes' );
			}
			echo '<div class="cqfw-quotes-toolbar"><h2 class="cqfw-section-title">' . esc_html__( 'Quote Requests', 'chat-quote-for-woocommerce' ) . '</h2>';
			if ( current_user_can( 'manage_options' ) ) {
				$export_url = wp_nonce_url( admin_url( 'admin.php?cqfw_action=export_csv&type=quotes' ), 'cqfw_export_nonce', 'nonce' );
				echo '<a href="' . esc_url( $export_url ) . '" class="button button-secondary">' . esc_html__( 'Export quotes (CSV)', 'chat-quote-for-woocommerce' ) . '</a>';
			}
			echo '</div>';
			echo '<hr class="wp-header-end" />';
			
			echo '<div class="notice notice-info inline"><p>';
			echo '<strong>' . esc_html__( 'Tip:', 'chat-quote-for-woocommerce' ) . '</strong> ';
			echo esc_html__( 'To receive Quote Requests here, place the shortcode', 'chat-quote-for-woocommerce' );
			echo ' <code>[cqfw_quote_form]</code> ';
			echo esc_html__( 'on any page (e.g., a "Request a Quote" page).', 'chat-quote-for-woocommerce' );
			echo '<br>' . esc_html__( 'Optional: You can link the form to a specific product by adding the product ID like this:', 'chat-quote-for-woocommerce' );
			echo ' <code>[cqfw_quote_form product_id="123"]</code>';
			echo '</p></div>';

			$list_table = new CQFW_Quotes_List_Table();
			$list_table->prepare_items();
			echo '<form method="get">';
			echo '<input type="hidden" name="page" value="cqfw-quotes" />';
			$list_table->search_box( __( 'Search Quotes', 'chat-quote-for-woocommerce' ), 'quote_search' );
			$list_table->display();
			echo '</form>';
			if ( class_exists( 'CQFW_Settings' ) ) {
				CQFW_Settings::render_page_footer();
			}
		}

		echo '</div>';
	}

	/**
	 * Render single quote view.
	 *
	 * @param int $quote_id Quote ID.
	 * @return void
	 */
	private function render_view_quote( $quote_id ) {
		global $wpdb;
		$table = CQFW_Analytics::get_table( 'quotes' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$quote = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE id = %d", $quote_id ) );

		if ( ! $quote ) {
			echo '<p>' . esc_html__( 'Quote not found.', 'chat-quote-for-woocommerce' ) . '</p>';
			return;
		}

		$status_labels = array(
			'new'         => __( 'New', 'chat-quote-for-woocommerce' ),
			'reviewing'   => __( 'Reviewing', 'chat-quote-for-woocommerce' ),
			'in_progress' => __( 'In Progress', 'chat-quote-for-woocommerce' ),
			'quoted'      => __( 'Quoted', 'chat-quote-for-woocommerce' ),
			'negotiating' => __( 'Negotiating', 'chat-quote-for-woocommerce' ),
			'accepted'    => __( 'Accepted', 'chat-quote-for-woocommerce' ),
			'rejected'    => __( 'Rejected', 'chat-quote-for-woocommerce' ),
			'expired'     => __( 'Expired', 'chat-quote-for-woocommerce' ),
		);

		?>
		<h1 class="wp-heading-inline">
			<?php
			/* translators: %d: quote ID */
			printf( esc_html__( 'Quote #%d', 'chat-quote-for-woocommerce' ), esc_html( $quote->id ) );
			?>
		</h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cqfw-quotes' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to List', 'chat-quote-for-woocommerce' ); ?></a>
		<?php do_action( 'cqfw_quote_action_buttons', $quote->id ); ?>
		<hr class="wp-header-end" />

		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="post-body-content">
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Customer Request Details', 'chat-quote-for-woocommerce' ); ?></span></h2>
						<div class="inside">
							<table class="form-table">
								<tr>
									<th><?php esc_html_e( 'Customer Name', 'chat-quote-for-woocommerce' ); ?></th>
									<td><strong><?php echo esc_html( $quote->customer_name ); ?></strong></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Email', 'chat-quote-for-woocommerce' ); ?></th>
									<td><a href="mailto:<?php echo esc_attr( $quote->email ); ?>"><?php echo esc_html( $quote->email ); ?></a></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Phone', 'chat-quote-for-woocommerce' ); ?></th>
									<td><?php echo esc_html( $quote->phone ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Quantity', 'chat-quote-for-woocommerce' ); ?></th>
									<td><?php echo esc_html( $quote->quantity ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Target Budget', 'chat-quote-for-woocommerce' ); ?></th>
									<td><?php echo esc_html( $quote->budget ? $quote->budget : '-' ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Message', 'chat-quote-for-woocommerce' ); ?></th>
									<td><?php echo nl2br( esc_html( $quote->message ) ); ?></td>
								</tr>
								<?php
								// Display Custom Form Fields if present
								if ( ! empty( $quote->custom_fields ) ) {
									$c_fields = json_decode( $quote->custom_fields, true );
									if ( is_array( $c_fields ) && ! empty( $c_fields ) ) {
										foreach ( $c_fields as $cf_key => $cf_val ) {
											$cf_label = ucwords( str_replace( array( '_', '-' ), ' ', $cf_key ) );
											?>
											<tr>
												<th><?php echo esc_html( $cf_label ); ?></th>
												<td><?php echo esc_html( $cf_val ); ?></td>
											</tr>
											<?php
										}
									}
								}

								// Display Attachments (e.g. product image, files) if present
								if ( ! empty( $quote->attachments ) ) {
									$attachments = json_decode( $quote->attachments, true );
									if ( is_array( $attachments ) && ! empty( $attachments ) ) {
										?>
										<tr>
											<th><?php esc_html_e( 'Attached Files / Product Image', 'chat-quote-for-woocommerce' ); ?></th>
											<td>
												<?php foreach ( $attachments as $att ) : 
													$att_url  = isset( $att['url'] ) ? esc_url( $att['url'] ) : '';
													$att_name = isset( $att['name'] ) ? esc_html( $att['name'] ) : basename( $att_url );
													$is_img   = preg_match( '/\.(jpg|jpeg|png|gif|webp)$/i', $att_url );
												?>
													<div style="margin-bottom:12px; background:#f8fafc; padding:10px; border-radius:6px; border:1px solid #e2e8f0; display:inline-block;">
														<?php if ( $is_img ) : ?>
															<a href="<?php echo esc_url( $att_url ); ?>" target="_blank" style="display:block; margin-bottom:8px;">
																<img src="<?php echo esc_url( $att_url ); ?>" alt="<?php echo esc_attr( $att_name ); ?>" style="max-width:260px; max-height:220px; border-radius:4px; display:block; object-fit:contain; background:#fff; border:1px solid #ddd;" />
															</a>
														<?php endif; ?>
														<a href="<?php echo esc_url( $att_url ); ?>" target="_blank" class="button button-secondary" download>
															📥 <?php echo esc_html( $att_name ); ?> (<?php esc_html_e( 'View / Download', 'chat-quote-for-woocommerce' ); ?>)
														</a>
													</div>
												<?php endforeach; ?>
											</td>
										</tr>
										<?php
									}
								}
								?>
							</table>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Admin Response', 'chat-quote-for-woocommerce' ); ?></span></h2>
						<div class="inside">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="cqfw_update_quote" />
								<input type="hidden" name="quote_id" value="<?php echo absint( $quote->id ); ?>" />
								<?php wp_nonce_field( 'cqfw_update_quote_' . $quote->id ); ?>

								<table class="form-table">
									<tr>
										<th><label for="cqfw_q_status"><?php esc_html_e( 'Status', 'chat-quote-for-woocommerce' ); ?></label></th>
										<td>
											<select name="status" id="cqfw_q_status">
												<?php foreach ( $status_labels as $val => $label ) : ?>
													<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $quote->status, $val ); ?>><?php echo esc_html( $label ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th><label for="cqfw_q_price"><?php esc_html_e( 'Quoted Price', 'chat-quote-for-woocommerce' ); ?></label></th>
										<td>
											<input type="text" name="quoted_price" id="cqfw_q_price" class="regular-text" value="<?php echo esc_attr( $quote->quoted_price ); ?>" />
										</td>
									</tr>
									<tr>
										<th><label for="cqfw_q_response"><?php esc_html_e( 'Response Message', 'chat-quote-for-woocommerce' ); ?></label></th>
										<td>
											<textarea name="admin_response" id="cqfw_q_response" rows="5" class="large-text"><?php echo esc_textarea( $quote->admin_response ); ?></textarea>
											<p class="description"><?php esc_html_e( 'This will be saved internally. To email the customer, check the box below.', 'chat-quote-for-woocommerce' ); ?></p>
										</td>
									</tr>
									<tr>
										<th></th>
										<td>
											<label>
												<input type="checkbox" name="send_email" value="1" />
												<?php esc_html_e( 'Email this response to the customer', 'chat-quote-for-woocommerce' ); ?>
											</label>
										</td>
									</tr>
								</table>
								
								<p class="submit">
									<?php submit_button( __( 'Update Quote', 'chat-quote-for-woocommerce' ), 'primary', 'submit', false ); ?>
								</p>
							</form>
						</div>
					</div>
				</div>

				<div id="postbox-container-1" class="postbox-container">
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Quote Meta', 'chat-quote-for-woocommerce' ); ?></span></h2>
						<div class="inside">
							<p><strong><?php esc_html_e( 'Submitted:', 'chat-quote-for-woocommerce' ); ?></strong><br /><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $quote->created_at ) ); ?></p>
							<p><strong><?php esc_html_e( 'Last Updated:', 'chat-quote-for-woocommerce' ); ?></strong><br /><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $quote->updated_at ) ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle admin post update.
	 *
	 * @return void
	 */
	public function handle_quote_update() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			wp_die( esc_html__( 'Access denied.', 'chat-quote-for-woocommerce' ) );
		}

		$quote_id = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
		if ( ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'cqfw_update_quote_' . $quote_id ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'chat-quote-for-woocommerce' ) );
		}

		global $wpdb;
		$table = CQFW_Analytics::get_table( 'quotes' );

		$status       = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'new';
		$price        = isset( $_POST['quoted_price'] ) ? sanitize_text_field( wp_unslash( $_POST['quoted_price'] ) ) : '';
		$response     = isset( $_POST['admin_response'] ) ? wp_kses_post( wp_unslash( $_POST['admin_response'] ) ) : '';
		$send_email   = isset( $_POST['send_email'] ) ? 1 : 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'         => $status,
				'quoted_price'   => $price,
				'admin_response' => $response,
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => $quote_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$quote_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE id = %d", $quote_id ) );

		if ( $quote_row ) {
			do_action( 'cqfw_quote_status_updated', $quote_id, $status, $quote_row );
		}

		if ( $send_email && $quote_row && is_email( $quote_row->email ) ) {
			// Free path when Pro HTML mail is not available.
			$pro_sent = function_exists( 'cqfw_can_use_pro' ) && cqfw_can_use_pro() && class_exists( 'CQFW_Pro_Notifications' );
			if ( ! $pro_sent ) {
				$subject = sprintf(
					/* translators: %d: quote ID */
					__( 'Update on your Quote Request #%d', 'chat-quote-for-woocommerce' ),
					$quote_id
				);
				$body = sprintf(
					/* translators: 1: customer name, 2: status, 3: quoted price, 4: response message */
					__( "Hello %1\$s,\n\nYour quote request has been updated.\n\nStatus: %2\$s\nQuoted Price: %3\$s\n\nMessage from our team:\n%4\$s\n\nThank you!", 'chat-quote-for-woocommerce' ),
					$quote_row->customer_name,
					$status,
					$price,
					$response
				);
				if ( class_exists( 'CQFW_Email_Notify' ) ) {
					CQFW_Email_Notify::send_mail( $quote_row->email, $subject, $body );
				} else {
					wp_mail( $quote_row->email, $subject, $body );
				}
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=cqfw-quotes&action=view&quote_id=' . $quote_id . '&updated=1' ) );
		exit;
	}

	/**
	 * Handle deletion.
	 *
	 * @return void
	 */
	public function handle_quote_delete() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			wp_die( esc_html__( 'Access denied.', 'chat-quote-for-woocommerce' ) );
		}

		$quote_id = isset( $_GET['quote_id'] ) ? absint( wp_unslash( $_GET['quote_id'] ) ) : 0;
		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'cqfw_delete_quote_' . $quote_id ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'chat-quote-for-woocommerce' ) );
		}

		global $wpdb;
		$table = CQFW_Analytics::get_table( 'quotes' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'id' => $quote_id ), array( '%d' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=cqfw-quotes' ) );
		exit;
	}
}

/**
 * List table for Quotes.
 */
class CQFW_Quotes_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'cqfw_quote',
				'plural'   => 'cqfw_quotes',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'customer_name' => __( 'Customer', 'chat-quote-for-woocommerce' ),
			'email'         => __( 'Email', 'chat-quote-for-woocommerce' ),
			'product'       => __( 'Product (ID)', 'chat-quote-for-woocommerce' ),
			'quantity'      => __( 'Qty', 'chat-quote-for-woocommerce' ),
			'budget'        => __( 'Budget', 'chat-quote-for-woocommerce' ),
			'status'        => __( 'Status', 'chat-quote-for-woocommerce' ),
			'created_at'    => __( 'Date', 'chat-quote-for-woocommerce' ),
		);
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;
		$table = CQFW_Analytics::get_table( 'quotes' );

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$where = '1=1';
		if ( $search ) {
			$where = $wpdb->prepare( '(customer_name LIKE %s OR email LIKE %s OR message LIKE %s)', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE $where" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$this->items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Default column renderer.
	 *
	 * @param array<string,mixed> $item Item row.
	 * @param string              $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( 'created_at' === $column_name ) {
			return esc_html( mysql2date( get_option( 'date_format' ), $item['created_at'] ) );
		}

		if ( 'product' === $column_name ) {
			return $item['product_id'] ? esc_html( $item['product_id'] ) : '-';
		}

		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Status column.
	 *
	 * @param array<string,mixed> $item Item row.
	 * @return string
	 */
	public function column_status( $item ) {
		$colors = array(
			'new'         => 'background:#e2e8f0;color:#1e293b;',
			'in_progress' => 'background:#dbeafe;color:#1e40af;',
			'quoted'      => 'background:#fef3c7;color:#92400e;',
			'accepted'    => 'background:#dcfce7;color:#166534;',
			'rejected'    => 'background:#fee2e2;color:#991b1b;',
		);
		$style = isset( $colors[ $item['status'] ] ) ? $colors[ $item['status'] ] : $colors['new'];
		$label = ucwords( str_replace( '_', ' ', $item['status'] ) );
		
		return sprintf( '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600;%s">%s</span>', esc_attr( $style ), esc_html( $label ) );
	}

	/**
	 * Name column with actions.
	 *
	 * @param array<string,mixed> $item Item row.
	 * @return string
	 */
	public function column_customer_name( $item ) {
		$view_url = admin_url( 'admin.php?page=cqfw-quotes&action=view&quote_id=' . absint( $item['id'] ) );

		$actions = array(
			'view'   => sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), esc_html__( 'Manage', 'chat-quote-for-woocommerce' ) ),
			'delete' => sprintf(
				'<a class="submitdelete" href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cqfw_delete_quote&quote_id=' . absint( $item['id'] ) ), 'cqfw_delete_quote_' . absint( $item['id'] ) ) ),
				esc_js( __( 'Delete this quote?', 'chat-quote-for-woocommerce' ) ),
				esc_html__( 'Delete', 'chat-quote-for-woocommerce' )
			),
		);

		return sprintf(
			'<strong><a href="%1$s" class="row-title">%2$s</a></strong><br />%3$s',
			esc_url( $view_url ),
			esc_html( $item['customer_name'] ),
			$this->row_actions( $actions )
		);
	}
}
