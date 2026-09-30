<?php
/**
 * Analytics and database interactions.
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_Analytics {

	/**
	 * Get a table name.
	 *
	 * @param string $type Table type (analytics or messages).
	 * @return string
	 */
	public static function get_table( $type ) {
		global $wpdb;

		$tables = array(
			'analytics'  => $wpdb->prefix . 'cqfw_analytics',
			'messages'   => $wpdb->prefix . 'cqfw_chat_messages',
			'quotes'     => $wpdb->prefix . 'cqfw_quotes',
			'inquiries'  => $wpdb->prefix . 'cqfw_inquiries',
		);

		return isset( $tables[ $type ] ) ? $tables[ $type ] : '';
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_cqfw_log_click', array( $this, 'log_click' ) );
		add_action( 'wp_ajax_nopriv_cqfw_log_click', array( $this, 'log_click' ) );

		add_action( 'wp_ajax_cqfw_send_chat_message', array( $this, 'send_chat_message' ) );
		add_action( 'wp_ajax_nopriv_cqfw_send_chat_message', array( $this, 'send_chat_message' ) );

		// V2 AJAX Actions
		add_action( 'wp_ajax_cqfw_get_chat_history', array( $this, 'get_chat_history' ) );
		add_action( 'wp_ajax_nopriv_cqfw_get_chat_history', array( $this, 'get_chat_history' ) );

		add_action( 'wp_ajax_cqfw_poll_messages', array( $this, 'poll_messages' ) );
		add_action( 'wp_ajax_nopriv_cqfw_poll_messages', array( $this, 'poll_messages' ) );

		add_action( 'wp_ajax_cqfw_admin_get_conversations', array( $this, 'admin_get_conversations' ) );
		add_action( 'wp_ajax_cqfw_admin_send_reply', array( $this, 'admin_send_reply' ) );
		add_action( 'wp_ajax_cqfw_admin_delete_conversation', array( $this, 'admin_delete_conversation' ) );
	}

	/**
	 * Log click.
	 */
	public function log_click() {
		check_ajax_referer( 'cqfw_nonce', 'nonce' );

		global $wpdb;

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$page_type  = isset( $_POST['page_type'] ) ? sanitize_text_field( wp_unslash( $_POST['page_type'] ) ) : '';
		$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip_hash    = $ip_address ? hash( 'sha256', $ip_address ) : '';

		$table = self::get_table( 'analytics' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'product_id' => $product_id,
				'page_type'  => $page_type,
				'click_date' => current_time( 'mysql' ),
				'ip_hash'    => $ip_hash,
			),
			array( '%d', '%s', '%s', '%s' )
		);

		wp_send_json_success();
	}

	/**
	 * Total clicks (cached).
	 */
	public function get_total_clicks() {
		global $wpdb;

		$cache_key = 'cqfw_total_clicks';
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table = self::get_table( 'analytics' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );

		wp_cache_set( $cache_key, $total, '', 300 );

		return $total;
	}

	/**
	 * Top products.
	 */
	public function get_top_products( $limit = 10 ) {
		global $wpdb;

		$limit     = max( 1, absint( $limit ) );
		$cache_key = 'cqfw_top_products_' . $limit;
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return (array) $cached;
		}

		$limit = max( 1, absint( $limit ) );
		$table = self::get_table( 'analytics' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT a.product_id, p.post_title AS product_name, COUNT(a.id) AS clicks
			FROM `$table` a
			LEFT JOIN {$wpdb->posts} p ON p.ID = a.product_id
			WHERE a.product_id > %d
			GROUP BY a.product_id
			ORDER BY clicks DESC
			LIMIT %d",
			0,
			$limit
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = (array) $wpdb->get_results( $sql, ARRAY_A );
		wp_cache_set( $cache_key, $results, '', 300 );

		return $results;
	}

	/**
	 * Last 7 days clicks.
	 */
	public function get_last_seven_days_clicks() {
		global $wpdb;

		$cache_key = 'cqfw_last_seven_days_clicks';
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return (array) $cached;
		}

		$table = self::get_table( 'analytics' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT DATE(click_date) AS day, COUNT(id) AS clicks
			FROM `$table`
			WHERE click_date >= %s
			GROUP BY DATE(click_date)
			ORDER BY day ASC",
			gmdate( 'Y-m-d', strtotime( '-7 days' ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = (array) $wpdb->get_results( $sql, ARRAY_A );
		wp_cache_set( $cache_key, $results, '', 300 );

		return $results;
	}

	/**
	 * Whitelist allowed file extensions and MIME types.
	 */
	public static function get_allowed_attachment_mimes() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'pdf'          => 'application/pdf',
			'doc'          => 'application/msword',
			'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'txt'          => 'text/plain',
			'xls'          => 'application/vnd.ms-excel',
			'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		);
	}

	/**
	 * Validate and process an uploaded attachment.
	 * Max size: 5MB. Strictly blocks executable/php scripts.
	 *
	 * @param array $file_array Single element of $_FILES.
	 * @return array
	 */
	public static function validate_and_upload_attachment( $file_array ) {
		if ( empty( $file_array['name'] ) || empty( $file_array['tmp_name'] ) ) {
			return array( 'success' => false, 'error' => __( 'No file selected.', 'chat-quote-for-woocommerce' ) );
		}

		$filename = sanitize_file_name( $file_array['name'] );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		// Strict extension blacklist check to prevent any malicious scripts/PHP execution
		$disallowed_exts = array(
			'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'phps',
			'exe', 'bat', 'cmd', 'sh', 'bash', 'bin', 'pl', 'py', 'cgi',
			'js', 'jsp', 'asp', 'aspx', 'vbs', 'scr', 'hta', 'msi', 'jar', 'svg'
		);

		if ( in_array( $ext, $disallowed_exts, true ) || preg_match( '/\.(php[0-9]?|phtml|phar)/i', $filename ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Disallowed file type for security. PHP and executable script files are strictly blocked.', 'chat-quote-for-woocommerce' ),
			);
		}

		// File size limit: 5MB (5 * 1024 * 1024 bytes)
		$max_size = 5 * 1024 * 1024;
		if ( ! empty( $file_array['size'] ) && $file_array['size'] > $max_size ) {
			return array(
				'success' => false,
				'error'   => __( 'File size exceeds the 5MB limit. Please upload a file smaller than 5MB.', 'chat-quote-for-woocommerce' ),
			);
		}

		$allowed_mimes = self::get_allowed_attachment_mimes();
		$wp_filetype   = wp_check_filetype_and_ext( $file_array['tmp_name'], $filename, $allowed_mimes );

		if ( empty( $wp_filetype['ext'] ) || empty( $wp_filetype['type'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid file type. Only images (JPG, PNG, GIF, WebP) and documents (PDF, DOC, DOCX, TXT, XLS, XLSX) are allowed.', 'chat-quote-for-woocommerce' ),
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload_overrides = array(
			'test_form' => false,
			'mimes'     => $allowed_mimes,
		);

		$uploaded = wp_handle_upload( $file_array, $upload_overrides );

		if ( ! empty( $uploaded['error'] ) ) {
			return array(
				'success' => false,
				'error'   => $uploaded['error'],
			);
		}

		return array(
			'success' => true,
			'url'     => esc_url_raw( $uploaded['url'] ),
			'name'    => $filename,
		);
	}

	/**
	 * Save chat message.
	 */
	public function send_chat_message() {
		check_ajax_referer( 'cqfw_nonce', 'nonce' );

		global $wpdb;

		$table = self::get_table( 'messages' );
		$message = self::normalize_plain_text( sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ) );

		// Handle PRO file/screenshot attachment with security validation
		$attachment_url = '';
		$file_name      = '';
		if ( ! empty( $_FILES['attachment']['name'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$upload_result = self::validate_and_upload_attachment( $_FILES['attachment'] );
			if ( empty( $upload_result['success'] ) ) {
				wp_send_json_error( array( 'message' => $upload_result['error'] ) );
			}
			$attachment_url = $upload_result['url'];
			$file_name      = $upload_result['name'];
			$message       .= ( $message ? "\n" : '' ) . '📎 ' . $file_name . ': ' . $attachment_url;
		}

		if ( empty( trim( $message ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a message or attach a file.', 'chat-quote-for-woocommerce' ) ) );
		}

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		if ( empty( $session_id ) ) {
			// Try to find an existing session for this phone number
			if ( ! empty( $phone ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$existing_session = $wpdb->get_var( $wpdb->prepare( "SELECT session_id FROM `$table` WHERE phone = %s AND phone != '' ORDER BY id DESC LIMIT 1", $phone ) );
				if ( ! empty( $existing_session ) ) {
					$session_id = $existing_session;
				}
			}

			if ( empty( $session_id ) ) {
				$session_id = wp_generate_uuid4();
			}
		}

		// If name or phone is empty, look up the last message in this session to populate them
		if ( ( empty( $name ) || empty( $phone ) ) && ! empty( $session_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$last_msg = $wpdb->get_row( $wpdb->prepare( "SELECT name, phone FROM `$table` WHERE session_id = %s AND name != '' ORDER BY id DESC LIMIT 1", $session_id ), ARRAY_A );
			if ( $last_msg ) {
				if ( empty( $name ) ) {
					$name = $last_msg['name'];
				}
				if ( empty( $phone ) ) {
					$phone = $last_msg['phone'];
				}
			}
		}

		$product_id = absint( wp_unslash( $_POST['product_id'] ?? 0 ) );
		$page_url   = esc_url_raw( wp_unslash( $_POST['page_url'] ?? '' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'session_id' => $session_id,
				'sender'     => 'visitor',
				'name'       => $name,
				'phone'      => $phone,
				'message'    => $message,
				'product_id' => $product_id,
				'page_url'   => $page_url,
				'status'     => 'pending',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		$message_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a visitor chat message is saved (instant email hook).
		 *
		 * @param int    $message_id Message ID.
		 * @param string $session_id Session ID.
		 * @param string $name       Visitor name.
		 * @param string $message    Message text.
		 */
		do_action( 'cqfw_new_live_message', $message_id, $session_id, $name, $message );

		// Also log as analytics engagement
		$analytics_table = self::get_table( 'analytics' );
		$ip_address      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip_hash         = $ip_address ? hash( 'sha256', $ip_address ) : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$analytics_table,
			array(
				'product_id' => $product_id,
				'page_type'  => 'chat_panel',
				'click_date' => current_time( 'mysql' ),
				'ip_hash'    => $ip_hash,
			),
			array( '%d', '%s', '%s', '%s' )
		);

		// Clean cache
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'cqfw' );
		}

		$whatsapp_message = $message;

		if ( $product_id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$template = CQFW_Settings::get_setting( 'default_message_template', self::get_default_template() );
				$product_context = str_replace(
					array( '{product_name}', '{product_price}', '{product_sku}', '{product_qty}', '{product_url}' ),
					array(
						$product->get_name(),
						self::format_price_text( $product->get_price() ),
						$product->get_sku(),
						'1',
						$page_url ? $page_url : get_permalink( $product->get_id() ),
					),
					$template
				);
				$whatsapp_message .= "\n\n" . $product_context;
			}
		}

		wp_send_json_success(
			array(
				'session_id'    => $session_id,
				'message_id'    => $message_id,
				'visitor_name'  => $name,
				'visitor_phone' => $phone,
				'message'        => __( 'Message sent.', 'chat-quote-for-woocommerce' ),
				'saved_message' => $message,
				'attachment_url'=> $attachment_url,
				'file_name'     => $file_name,
				'whatsapp_url'  => $this->build_whatsapp_url( $whatsapp_message ),
				'auto_redirect' => false,
			)
		);
	}

	/**
	 * Get chat history for a session.
	 */
	public function get_chat_history() {
		check_ajax_referer( 'cqfw_nonce', 'nonce' );

		global $wpdb;
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		if ( empty( $session_id ) ) {
			wp_send_json_error( __( 'Session ID required.', 'chat-quote-for-woocommerce' ) );
		}

		$table = self::get_table( 'messages' );
		
		// If an admin is fetching the history (i.e. they clicked on the chat), mark visitor messages as read.
		if ( current_user_can( cqfw_get_admin_capability() ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array( 'status' => 'read' ),
				array(
					'session_id' => $session_id,
					'sender'     => 'visitor',
					'status'     => 'pending',
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT id, sender, name, message, created_at FROM `$table` WHERE session_id = %s ORDER BY id ASC", $session_id ), ARRAY_A );

		foreach ( $results as &$msg ) {
			$msg['created_at_gmt'] = get_gmt_from_date( $msg['created_at'] );
		}

		wp_send_json_success( array( 'messages' => $results ) );
	}

	/**
	 * Poll new messages for a session.
	 */
	public function poll_messages() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cqfw_nonce' ) && ! wp_verify_nonce( $nonce, 'cqfw_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
		}

		global $wpdb;
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$last_id    = isset( $_POST['last_id'] ) ? absint( wp_unslash( $_POST['last_id'] ) ) : 0;

		if ( empty( $session_id ) ) {
			wp_send_json_error( __( 'Session ID required.', 'chat-quote-for-woocommerce' ) );
		}

		$table = self::get_table( 'messages' );

		// If admin is actively polling this thread, mark any pending visitor messages as read
		if ( current_user_can( cqfw_get_admin_capability() ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array( 'status' => 'read' ),
				array(
					'session_id' => $session_id,
					'sender'     => 'visitor',
					'status'     => 'pending',
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT id, sender, name, message, created_at FROM `$table` WHERE session_id = %s AND id > %d ORDER BY id ASC", $session_id, $last_id ), ARRAY_A );

		foreach ( $results as &$msg ) {
			$msg['created_at_gmt'] = get_gmt_from_date( $msg['created_at'] );
		}

		wp_send_json_success( array( 'messages' => $results ) );
	}

	/**
	 * Admin: Get conversation list.
	 */
	public function admin_get_conversations() {
		check_ajax_referer( 'cqfw_admin_nonce', 'nonce' );

		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'chat-quote-for-woocommerce' ) );
		}

		global $wpdb;
		$table = self::get_table( 'messages' );

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$where = '';
		$params = array();

		if ( ! empty( $search ) ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where = " AND (m1.name LIKE %s OR m1.phone LIKE %s OR m1.message LIKE %s)";
			$params = array( $like, $like, $like );
		}

		$sql = "SELECT m1.*
			FROM `$table` m1
			INNER JOIN (
				SELECT session_id, MAX(id) as max_id
				FROM `$table`
				WHERE session_id != ''
				GROUP BY session_id
			) m2 ON m1.id = m2.max_id
			WHERE 1=1 {$where}
			ORDER BY m1.created_at DESC";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $params );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results( $sql, ARRAY_A );

		if ( function_exists( 'wc_get_product' ) ) {
			foreach ( $results as &$row ) {
				$product_id = absint( $row['product_id'] );
				if ( $product_id ) {
					$product = wc_get_product( $product_id );
					if ( $product ) {
						$row['product_name']  = $product->get_name();
						$row['product_price'] = self::format_price_text( $product->get_price() );
						$row['product_url']   = get_permalink( $product_id );
						$image_id             = $product->get_image_id();
						if ( $image_id ) {
							$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
							if ( $image_url ) {
								$row['product_image'] = $image_url;
							}
						}
					}
				}
				$row['created_at_gmt'] = get_gmt_from_date( $row['created_at'] );
			}
		} else {
			// Even if WooCommerce isn't loaded, still format GMT time
			foreach ( $results as &$row ) {
				$row['created_at_gmt'] = get_gmt_from_date( $row['created_at'] );
			}
		}

		wp_send_json_success( array( 'conversations' => $results ) );
	}

	/**
	 * Admin: Send a chat reply.
	 */
	public function admin_send_reply() {
		check_ajax_referer( 'cqfw_admin_nonce', 'nonce' );

		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'chat-quote-for-woocommerce' ) );
		}

		global $wpdb;
		$table = self::get_table( 'messages' );

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$message    = self::normalize_plain_text( sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ) );

		// Handle admin file/screenshot attachment with security validation
		$attachment_url = '';
		$file_name      = '';
		if ( ! empty( $_FILES['attachment']['name'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$upload_result = self::validate_and_upload_attachment( $_FILES['attachment'] );
			if ( empty( $upload_result['success'] ) ) {
				wp_send_json_error( array( 'message' => $upload_result['error'] ) );
			}
			$attachment_url = $upload_result['url'];
			$file_name      = $upload_result['name'];
			$message       .= ( $message ? "\n" : '' ) . '📎 ' . $file_name . ': ' . $attachment_url;
		}

		if ( empty( $session_id ) || empty( trim( $message ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Session ID and message required.', 'chat-quote-for-woocommerce' ) ) );
		}

		// Get the last customer name/phone in this session
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$last_customer = $wpdb->get_row( $wpdb->prepare( "SELECT name, phone, product_id, page_url FROM `$table` WHERE session_id = %s AND sender = 'visitor' ORDER BY id DESC LIMIT 1", $session_id ), ARRAY_A );

		$name       = $last_customer ? $last_customer['name'] : __( 'Admin', 'chat-quote-for-woocommerce' );
		$phone      = $last_customer ? $last_customer['phone'] : '';
		$product_id = $last_customer ? absint( $last_customer['product_id'] ) : 0;
		$page_url   = $last_customer ? esc_url_raw( $last_customer['page_url'] ) : '';

		// Insert admin reply
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'session_id' => $session_id,
				'sender'     => 'admin',
				'name'       => $name,
				'phone'      => $phone,
				'message'    => $message,
				'product_id' => $product_id,
				'page_url'   => $page_url,
				'status'     => 'replied',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		$reply_message_id = (int) $wpdb->insert_id;

		// Update all visitor messages in this session to 'replied'
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$table,
			array( 'status' => 'replied' ),
			array( 'session_id' => $session_id, 'sender' => 'visitor' ),
			array( '%s' ),
			array( '%s', '%s' )
		);

		// Delete cache for messages
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'cqfw' );
		}

		wp_send_json_success( array( 
			'message'        => __( 'Reply sent.', 'chat-quote-for-woocommerce' ),
			'message_id'     => $reply_message_id,
			'saved_message'  => $message,
			'attachment_url' => $attachment_url,
			'file_name'      => $file_name,
		) );
	}

	/**
	 * Admin: Delete entire conversation thread.
	 */
	public function admin_delete_conversation() {
		check_ajax_referer( 'cqfw_admin_nonce', 'nonce' );

		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'chat-quote-for-woocommerce' ) );
		}

		global $wpdb;
		$table = self::get_table( 'messages' );
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		if ( empty( $session_id ) ) {
			wp_send_json_error( __( 'Session ID required.', 'chat-quote-for-woocommerce' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete(
			$table,
			array( 'session_id' => $session_id ),
			array( '%s' )
		);

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'cqfw' );
		}

		wp_send_json_success( array( 'message' => __( 'Conversation deleted.', 'chat-quote-for-woocommerce' ) ) );
	}

	/**
	 * Message count.
	 */
	public function get_message_count( $status = 'pending' ) {
		global $wpdb;

		$cache_key = 'cqfw_message_count_' . $status;
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table = self::get_table( 'messages' );

		if ( 'all' === $status ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
			wp_cache_set( $cache_key, $count, '', 300 );
			return $count;
		}

		$allowed = array( 'pending', 'read', 'replied', 'spam' );

		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'pending';
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM `$table` WHERE status = %s",
			$status
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$count = (int) $wpdb->get_var( $sql );
		wp_cache_set( $cache_key, $count, '', 300 );

		return $count;
	}

	/**
	 * Get messages.
	 */
	public function get_messages( $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'per_page' => 20,
				'paged'    => 1,
				'search'   => '',
			)
		);

		$cache_key = 'cqfw_messages_' . md5( wp_json_encode( $args ) );
		$cached    = wp_cache_get( $cache_key, 'cqfw' );

		if ( false !== $cached ) {
			return $cached;
		}

		$table = self::get_table( 'messages' );

		$where  = '';
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   = " WHERE name LIKE %s OR phone LIKE %s OR message LIKE %s";
			$params  = array( $like, $like, $like );
		}

		$count_sql = "SELECT COUNT(*) FROM `$table` {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // @phpstan-ignore-line

		$offset = ( $args['paged'] - 1 ) * $args['per_page'];

		$list_sql = "SELECT * FROM `$table` {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		$list_params   = $params;
		$list_params[] = $args['per_page'];
		$list_params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$items = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A ); // @phpstan-ignore-line

		$result = array(
			'items' => (array) $items,
			'total' => $total,
		);

		wp_cache_set( $cache_key, $result, 'cqfw', 300 );

		return $result;
	}

	/**
	 * Single message.
	 */
	public function get_message( $id ) {
		global $wpdb;

		$id        = absint( $id );
		$cache_key = 'cqfw_message_' . $id;
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return (array) $cached;
		}

		$table = self::get_table( 'messages' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT * FROM `$table` WHERE id = %d",
			absint( $id )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$message = $wpdb->get_row( $sql, ARRAY_A );
		wp_cache_set( $cache_key, $message, '', 300 );

		return $message;
	}

	/**
	 * Update status.
	 */
	public function update_message_status( $id, $status ) {
		global $wpdb;

		wp_cache_delete( 'cqfw_message_' . absint( $id ) );

		$table = self::get_table( 'messages' );

		$allowed = array( 'pending', 'read', 'replied', 'spam' );

		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'id' => absint( $id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete message.
	 */
	public function delete_message( $id ) {
		global $wpdb;

		wp_cache_delete( 'cqfw_message_' . absint( $id ) );

		$table = self::get_table( 'messages' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->delete(
			$table,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	/**
	 * WhatsApp URL.
	 */
	public function build_whatsapp_url( $message ) {
		$number = CQFW_Settings::get_setting( 'whatsapp_number' );
		$number = apply_filters( 'cqfw_whatsapp_number', $number );

		if ( empty( $number ) ) {
			$fallback = CQFW_Settings::get_setting( 'whatsapp_fallback_number' );
			$number   = apply_filters( 'cqfw_whatsapp_number', $fallback );
		}

		if ( empty( $number ) ) {
			return '';
		}

		return 'https://wa.me/' . preg_replace( '/\D/', '', $number ) .
			'?text=' . rawurlencode( self::normalize_plain_text( $message ) );
	}

	/**
	 * Default template.
	 */
	public static function get_default_template() {
		return "Hello,\n\nI am interested in:\n\nProduct: {product_name}\nPrice: {product_price}\nQuantity: {product_qty}\n\nPlease provide a quote.";
	}

	/**
	 * Format price.
	 */
	public static function format_price_text( $price ) {
		$formatted = function_exists( 'wc_price' )
			? wp_strip_all_tags( wc_price( $price ) )
			: (string) $price;

		return self::normalize_plain_text( $formatted );
	}

	/**
	 * Decode HTML entities and normalize whitespace for plain text messages.
	 *
	 * @param string $text Text to normalize.
	 * @return string
	 */
	public static function normalize_plain_text( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = str_replace( "\xc2\xa0", ' ', $text );

		return trim( $text );
	}
}
