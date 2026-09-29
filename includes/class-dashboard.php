<?php
/**
 * Admin dashboard widget and chat messages screen.
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CQFW_Dashboard {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'admin_post_cqfw_update_message_status', array( $this, 'handle_status_update' ) );
		add_action( 'admin_post_cqfw_delete_message', array( $this, 'handle_delete_message' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin scripts/styles for chat messages screen.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'chat-quote_page_cqfw-messages' === $hook ) {
			$css_ver = file_exists( CQFW_PATH . 'assets/css/admin-dashboard.css' ) ? (string) filemtime( CQFW_PATH . 'assets/css/admin-dashboard.css' ) : CQFW_VERSION;
			$js_ver  = file_exists( CQFW_PATH . 'assets/js/admin-dashboard.js' ) ? (string) filemtime( CQFW_PATH . 'assets/js/admin-dashboard.js' ) : CQFW_VERSION;

			wp_enqueue_style(
				'cqfw-admin-dashboard',
				CQFW_URL . 'assets/css/admin-dashboard.css',
				array(),
				$css_ver
			);

			wp_enqueue_script(
				'cqfw-admin-dashboard',
				CQFW_URL . 'assets/js/admin-dashboard.js',
				array( 'jquery' ),
				$js_ver,
				true
			);

			wp_localize_script(
				'cqfw-admin-dashboard',
				'cqfwAdminInbox',
				array(
					'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
					'nonce'              => wp_create_nonce( 'cqfw_admin_nonce' ),
					'nonceUser'          => wp_create_nonce( 'cqfw_nonce' ),
					'getConversations'   => 'cqfw_admin_get_conversations',
					'getHistory'         => 'cqfw_get_chat_history',
					'sendReply'          => 'cqfw_admin_send_reply',
					'deleteConversation' => 'cqfw_admin_delete_conversation',
					'pollMessages'       => 'cqfw_poll_messages',
					'whatsappNumber'     => CQFW_Settings::get_setting( 'whatsapp_number' ),
				)
			);
		} elseif ( 'chat-quote_page_cqfw-analytics' === $hook ) {
			wp_enqueue_style(
				'cqfw-admin-analytics',
				CQFW_URL . 'assets/css/admin-analytics.css',
				array(),
				CQFW_VERSION
			);

			// Enqueue Chart.js locally
			wp_enqueue_script(
				'chart-js',
				CQFW_URL . 'assets/js/chart.min.js',
				array(),
				'3.9.1',
				true
			);

			wp_enqueue_script(
				'cqfw-admin-analytics',
				CQFW_URL . 'assets/js/admin-analytics.js',
				array( 'chart-js' ),
				CQFW_VERSION,
				true
			);
		}
	}

	/**
	 * Register admin pages.
	 *
	 * @return void
	 */
	public function register_admin_pages() {
		$pages = class_exists( 'CQFW_Settings' ) ? CQFW_Settings::get_admin_pages() : array();

		add_submenu_page(
			'cqfw-settings',
			isset( $pages['cqfw-messages']['title'] ) ? $pages['cqfw-messages']['title'] : __( 'Customer Inbox', 'chat-quote-for-woocommerce' ),
			isset( $pages['cqfw-messages']['menu'] ) ? $pages['cqfw-messages']['menu'] : __( 'Customer Inbox', 'chat-quote-for-woocommerce' ),
			cqfw_get_admin_capability(),
			'cqfw-messages',
			array( $this, 'render_messages_page' )
		);

		add_submenu_page(
			'cqfw-settings',
			isset( $pages['cqfw-analytics']['title'] ) ? $pages['cqfw-analytics']['title'] : __( 'Reports & Analytics', 'chat-quote-for-woocommerce' ),
			isset( $pages['cqfw-analytics']['menu'] ) ? $pages['cqfw-analytics']['menu'] : __( 'Reports', 'chat-quote-for-woocommerce' ),
			cqfw_get_admin_capability(),
			'cqfw-analytics',
			array( $this, 'render_analytics_page' )
		);
	}

	/**
	 * Register dashboard widget.
	 *
	 * @return void
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'cqfw_dashboard_widget',
			__( 'Chat Quote for WooCommerce Analytics', 'chat-quote-for-woocommerce' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		$analytics = new CQFW_Analytics();
		$total     = $analytics->get_total_clicks();
		$top       = $analytics->get_top_products( 10 );
		$days      = $analytics->get_last_seven_days_clicks();
		$pending   = $analytics->get_message_count( 'pending' );

		echo '<div class="cqfw-dashboard-stats">';
		echo '<p><strong>' . esc_html__( 'Total Clicks:', 'chat-quote-for-woocommerce' ) . '</strong> ' . esc_html( (string) $total ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Unread Messages:', 'chat-quote-for-woocommerce' ) . '</strong> ' . esc_html( (string) $pending ) . '</p>';
		echo '</div>';

		echo '<h4>' . esc_html__( 'Top 10 Products', 'chat-quote-for-woocommerce' ) . '</h4>';
		echo '<ol>';
		foreach ( $top as $row ) {
			printf(
				'<li>%1$s <span class="count">(%2$d)</span></li>',
				esc_html( $row['product_name'] ),
				(int) $row['clicks']
			);
		}
		echo '</ol>';

		echo '<h4>' . esc_html__( 'Last 7 Days Clicks', 'chat-quote-for-woocommerce' ) . '</h4>';
		echo '<ul>';
		foreach ( $days as $row ) {
			printf(
				'<li>%1$s - %2$d</li>',
				esc_html( $row['day'] ),
				(int) $row['clicks']
			);
		}
		echo '</ul>';
	}

	/**
	 * Render analytics page.
	 *
	 * @return void
	 */
	public function render_analytics_page() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			return;
		}

		$analytics = new CQFW_Analytics();
		$total     = $analytics->get_total_clicks();
		$pending   = $analytics->get_message_count( 'pending' );
		$top       = $analytics->get_top_products( 5 );
		$days      = $analytics->get_last_seven_days_clicks();
		
		// Prepare chart data
		$chart_labels = array();
		$chart_data   = array();
		// Ensure last 7 days are plotted even if some days have 0 clicks
		for ( $i = 6; $i >= 0; $i-- ) {
			$date = gmdate( 'Y-m-d', strtotime( "-$i days" ) );
			$count = 0;
			foreach ( $days as $day ) {
				if ( $day['day'] === $date ) {
					$count = (int) $day['clicks'];
					break;
				}
			}
			$chart_labels[] = gmdate( 'M j', strtotime( $date ) );
			$chart_data[]   = $count;
		}

		// Inject data for JS
		wp_add_inline_script(
			'cqfw-admin-analytics',
			'var cqfwAnalyticsData = ' . wp_json_encode( array(
				'chartLabels' => $chart_labels,
				'chartData'   => $chart_data,
			) ) . ';',
			'before'
		);

		?>
		<?php
		if ( class_exists( 'CQFW_Settings' ) ) {
			CQFW_Settings::render_page_header( 'cqfw-analytics' );
		}
		?>
		<div class="cqfw-analytics-wrap">
			<div class="cqfw-stat-grid">
				<div class="cqfw-stat-card is-success">
					<div class="cqfw-stat-icon">
						<span class="dashicons dashicons-chart-bar"></span>
					</div>
					<div class="cqfw-stat-info">
						<span class="cqfw-stat-label"><?php esc_html_e( 'Total WhatsApp Clicks', 'chat-quote-for-woocommerce' ); ?></span>
						<span class="cqfw-stat-value" data-target="<?php echo esc_attr( $total ); ?>">0</span>
					</div>
				</div>

				<div class="cqfw-stat-card is-primary">
					<div class="cqfw-stat-icon">
						<span class="dashicons dashicons-format-chat"></span>
					</div>
					<div class="cqfw-stat-info">
						<span class="cqfw-stat-label"><?php esc_html_e( 'Unread Messages', 'chat-quote-for-woocommerce' ); ?></span>
						<span class="cqfw-stat-value" data-target="<?php echo esc_attr( $pending ); ?>">0</span>
					</div>
				</div>
				
				<div class="cqfw-stat-card is-warning">
					<div class="cqfw-stat-icon">
						<span class="dashicons dashicons-cart"></span>
					</div>
					<div class="cqfw-stat-info">
						<span class="cqfw-stat-label"><?php esc_html_e( 'Top Products Engaged', 'chat-quote-for-woocommerce' ); ?></span>
						<span class="cqfw-stat-value" data-target="<?php echo esc_attr( count( $top ) ); ?>">0</span>
					</div>
				</div>
			</div>

			<div class="cqfw-charts-grid">
				<div class="cqfw-chart-card">
					<h2><?php esc_html_e( 'Engagement Overview (Last 7 Days)', 'chat-quote-for-woocommerce' ); ?></h2>
					<div class="cqfw-chart-container">
						<canvas id="cqfw-clicks-chart"></canvas>
					</div>
				</div>

				<div class="cqfw-chart-card">
					<h2><?php esc_html_e( 'Top Products', 'chat-quote-for-woocommerce' ); ?></h2>
					<ul class="cqfw-top-products">
						<?php if ( empty( $top ) ) : ?>
							<li><span class="cqfw-product-name"><?php esc_html_e( 'No data yet.', 'chat-quote-for-woocommerce' ); ?></span></li>
						<?php else : ?>
							<?php foreach ( $top as $row ) : ?>
								<li>
									<span class="cqfw-product-name" title="<?php echo esc_attr( $row['product_name'] ); ?>">
										<?php echo esc_html( $row['product_name'] ); ?>
									</span>
									<span class="cqfw-product-count"><?php echo esc_html( $row['clicks'] ); ?></span>
								</li>
							<?php endforeach; ?>
						<?php endif; ?>
					</ul>
				</div>
			</div>
		</div>
		<?php
		if ( class_exists( 'CQFW_Settings' ) ) {
			CQFW_Settings::render_page_footer();
		}
	}

	/**
	 * Render messages admin page.
	 *
	 * @return void
	 */
	public function render_messages_page() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			return;
		}
		if ( class_exists( 'CQFW_Settings' ) ) {
			CQFW_Settings::render_page_header( 'cqfw-messages' );
		}
		?>
		<div class="cqfw-inbox-wrap" id="cqfw-inbox-wrap">
			<div class="cqfw-inbox-toolbar">
				<div class="cqfw-inbox-toolbar__left">
					<h2 class="cqfw-inbox-heading"><?php esc_html_e( 'Customer Inbox', 'chat-quote-for-woocommerce' ); ?></h2>
					<button type="button" class="cqfw-inbox-fullscreen-btn" id="cqfw-inbox-fullscreen-btn" title="<?php esc_attr_e( 'Open full screen chat', 'chat-quote-for-woocommerce' ); ?>" aria-pressed="false">
						<span class="dashicons dashicons-fullscreen-alt cqfw-fs-icon-expand" aria-hidden="true"></span>
						<span class="dashicons dashicons-fullscreen-exit-alt cqfw-fs-icon-exit" aria-hidden="true"></span>
						<span class="cqfw-inbox-fullscreen-btn__label"><?php esc_html_e( 'Full screen', 'chat-quote-for-woocommerce' ); ?></span>
					</button>
				</div>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<?php $export_url = wp_nonce_url( admin_url( 'admin.php?cqfw_action=export_csv&type=messages' ), 'cqfw_export_nonce', 'nonce' ); ?>
				<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Export messages (CSV)', 'chat-quote-for-woocommerce' ); ?></a>
			<?php endif; ?>
			</div>
			<div class="cqfw-admin-inbox" id="cqfw-admin-inbox-root">
				<!-- Sidebar / Chat list -->
				<div class="cqfw-inbox-sidebar">
					<div class="cqfw-inbox-search-container">
						<span class="dashicons dashicons-search"></span>
						<input type="search" id="cqfw-inbox-search" placeholder="<?php esc_attr_e( 'Search chats...', 'chat-quote-for-woocommerce' ); ?>" autocomplete="off" />
					</div>
					<div class="cqfw-inbox-list" id="cqfw-inbox-threads-list">
						<div class="cqfw-inbox-loading"><?php esc_html_e( 'Loading conversations...', 'chat-quote-for-woocommerce' ); ?></div>
					</div>
				</div>

				<!-- Chat area -->
				<div class="cqfw-inbox-main" id="cqfw-inbox-main-view">
					<!-- Default placeholder when no conversation is selected -->
					<div class="cqfw-inbox-placeholder" id="cqfw-inbox-no-chat-selected">
						<span class="dashicons dashicons-format-chat"></span>
						<h3><?php esc_html_e( 'Select a Conversation', 'chat-quote-for-woocommerce' ); ?></h3>
						<p><?php esc_html_e( 'Choose a chat from the sidebar list to see the message history and reply.', 'chat-quote-for-woocommerce' ); ?></p>
					</div>

					<!-- Active chat pane -->
					<div class="cqfw-inbox-chat-pane" id="cqfw-inbox-active-chat" style="display: none;">
						<!-- Header -->
						<div class="cqfw-chat-header">
							<button type="button" class="cqfw-mobile-back-btn" id="cqfw-mobile-back">
								<span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Back', 'chat-quote-for-woocommerce' ); ?>
							</button>
							<div class="cqfw-chat-header-info">
								<h2 id="cqfw-active-name">...</h2>
								<div class="cqfw-active-meta">
									<span id="cqfw-active-phone">...</span>
									<span class="cqfw-meta-separator">&bull;</span>
									<span id="cqfw-active-context-link">...</span>
								</div>
							</div>
							<div class="cqfw-chat-header-actions">
								<button type="button" class="cqfw-chat-fs-btn" id="cqfw-chat-fullscreen-btn" title="<?php esc_attr_e( 'Full screen chat', 'chat-quote-for-woocommerce' ); ?>">
									<span class="dashicons dashicons-fullscreen-alt cqfw-fs-icon-expand" aria-hidden="true"></span>
									<span class="dashicons dashicons-fullscreen-exit-alt cqfw-fs-icon-exit" aria-hidden="true"></span>
								</button>
								<a href="#" target="_blank" rel="noopener noreferrer" class="button button-secondary cqfw-whatsapp-reply-btn" id="cqfw-whatsapp-reply-link">
									<span class="dashicons dashicons-share"></span> <?php esc_html_e( 'Reply on WhatsApp', 'chat-quote-for-woocommerce' ); ?>
								</a>
								<button type="button" class="button button-link-delete cqfw-delete-thread-btn" id="cqfw-delete-thread-btn" title="<?php esc_attr_e( 'Delete conversation', 'chat-quote-for-woocommerce' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</div>

						<!-- Messages bubble scroll container -->
						<div class="cqfw-chat-body" id="cqfw-chat-bubbles-container">
							<!-- Loaded dynamically -->
						</div>

						<!-- Reply text form (PRO Pill Input Bar) -->
						<div class="cqfw-chat-footer">
							<!-- Attachment Preview Chip for Admin -->
							<div class="cqfw-admin-attachment-preview" style="display:none;width:100%;margin-bottom:6px;">
								<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:#e0f2fe;color:#0369a1;border-radius:20px;font-size:12px;font-weight:600;">
									<span>📎</span> <span class="cqfw-admin-attachment-name"></span>
									<button type="button" class="cqfw-admin-attachment-remove" style="background:none;border:none;cursor:pointer;color:#0369a1;font-weight:bold;font-size:15px;line-height:1;margin-left:4px;padding:0;">&times;</button>
								</span>
							</div>

							<form id="cqfw-admin-reply-form" novalidate style="width:100%;margin:0;">
								<div class="cqfw-admin-pill-bar">
									<!-- Attachment (+ Menu) -->
									<div class="cqfw-admin-attach-dropdown" style="position:relative;display:flex;align-items:center;">
										<button type="button" id="cqfw-admin-attach-btn" class="cqfw-admin-icon-btn" title="<?php esc_attr_e( 'Attach file or screenshot (+)', 'chat-quote-for-woocommerce' ); ?>">
											<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
										</button>
										<div class="cqfw-admin-attach-menu" hidden>
											<button type="button" class="cqfw-admin-attach-file">
												<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
												<span><?php esc_html_e( 'Send a file', 'chat-quote-for-woocommerce' ); ?></span>
											</button>
											<button type="button" class="cqfw-admin-attach-screenshot">
												<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
												<span><?php esc_html_e( 'Add screenshot', 'chat-quote-for-woocommerce' ); ?></span>
											</button>
										</div>
										<input type="file" id="cqfw-admin-hidden-file" style="display:none;" />
									</div>

									<!-- Reply Textarea -->
									<textarea id="cqfw-reply-text" rows="1" placeholder="<?php esc_attr_e( 'Write a message...', 'chat-quote-for-woocommerce' ); ?>" required></textarea>

									<!-- Emoji (☺ Menu) -->
									<div class="cqfw-admin-emoji-dropdown" style="position:relative;display:flex;align-items:center;">
										<button type="button" id="cqfw-admin-emoji-btn" class="cqfw-admin-icon-btn" title="<?php esc_attr_e( 'Insert emoji (☺)', 'chat-quote-for-woocommerce' ); ?>">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
										</button>
										<div class="cqfw-admin-emoji-picker" hidden>
											<div class="cqfw-admin-emoji-grid">
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="😊">😊</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="😁">😁</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="😂">😂</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="🥰">🥰</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="😍">😍</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="😐">😐</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="😟">😟</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="🥱">🥱</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="😢">😢</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="😭">😭</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="🎉">🎉</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="❤️">❤️</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="👌">👌</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="👍">👍</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="👎">👎</button>
												<button type="button" class="cqfw-admin-emoji-item" data-emoji="🙏">🙏</button>
											</div>
										</div>
									</div>

									<!-- Send Button (Upward Arrow as in Screenshot 1) -->
									<button type="submit" id="cqfw-send-reply-btn" title="<?php esc_attr_e( 'Send Reply', 'chat-quote-for-woocommerce' ); ?>">
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
			</div>
		</div>
		<?php
		if ( class_exists( 'CQFW_Settings' ) ) {
			CQFW_Settings::render_page_footer();
		}
	}

	/**
	 * Handle status update action.
	 *
	 * @return void
	 */
	public function handle_status_update() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			wp_die( esc_html__( 'Access denied.', 'chat-quote-for-woocommerce' ) );
		}

		$message_id = isset( $_GET['message_id'] ) ? absint( wp_unslash( $_GET['message_id'] ) ) : 0;
		$status     = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'cqfw_message_action_' . $message_id ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'chat-quote-for-woocommerce' ) );
		}

		$allowed = array( 'pending', 'read', 'replied' );
		if ( ! in_array( $status, $allowed, true ) ) {
			wp_die( esc_html__( 'Invalid status.', 'chat-quote-for-woocommerce' ) );
		}

		( new CQFW_Analytics() )->update_message_status( $message_id, $status );
		wp_safe_redirect( admin_url( 'admin.php?page=cqfw-messages' ) );
		exit;
	}

	/**
	 * Handle delete action.
	 *
	 * @return void
	 */
	public function handle_delete_message() {
		if ( ! current_user_can( cqfw_get_admin_capability() ) ) {
			wp_die( esc_html__( 'Access denied.', 'chat-quote-for-woocommerce' ) );
		}

		$message_id = isset( $_GET['message_id'] ) ? absint( wp_unslash( $_GET['message_id'] ) ) : 0;
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'cqfw_message_delete_' . $message_id ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'chat-quote-for-woocommerce' ) );
		}

		( new CQFW_Analytics() )->delete_message( $message_id );
		wp_safe_redirect( admin_url( 'admin.php?page=cqfw-messages' ) );
		exit;
	}
}

/**
 * List table for chat messages.
 */
class CQFW_Messages_List_Table extends WP_List_Table {

	/**
	 * Analytics instance.
	 *
	 * @var CQFW_Analytics
	 */
	private $analytics;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'cqfw_message',
				'plural'   => 'cqfw_messages',
				'ajax'     => false,
			)
		);

		$this->analytics = new CQFW_Analytics();
	}

	/**
	 * Get columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'name'       => __( 'Name', 'chat-quote-for-woocommerce' ),
			'phone'      => __( 'Phone', 'chat-quote-for-woocommerce' ),
			'message'    => __( 'Message', 'chat-quote-for-woocommerce' ),
			'status'     => __( 'Status', 'chat-quote-for-woocommerce' ),
			'created_at' => __( 'Date', 'chat-quote-for-woocommerce' ),
		);
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search filter in admin list table.
		$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$result = $this->analytics->get_messages(
			array(
				'per_page' => $per_page,
				'paged'    => $current_page,
				'search'   => $search,
			)
		);

		$this->items = $result['items'];
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages'  => ceil( $result['total'] / $per_page ),
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
		if ( 'message' === $column_name ) {
			return esc_html( wp_trim_words( $item['message'], 18, '...' ) );
		}

		if ( 'created_at' === $column_name ) {
			return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['created_at'] ) );
		}

		if ( 'status' === $column_name ) {
			return esc_html( $item['status'] );
		}

		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Name column with actions.
	 *
	 * @param array<string,mixed> $item Item row.
	 * @return string
	 */
	public function column_name( $item ) {
		$view_url = admin_url( 'admin.php?page=cqfw-messages&view_id=' . absint( $item['id'] ) );

		$actions = array(
			'view'    => sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), esc_html__( 'View', 'chat-quote-for-woocommerce' ) ),
			'read'    => sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cqfw_update_message_status&message_id=' . absint( $item['id'] ) . '&status=read' ), 'cqfw_message_action_' . absint( $item['id'] ) ) ),
				esc_html__( 'Mark Read', 'chat-quote-for-woocommerce' )
			),
			'replied' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cqfw_update_message_status&message_id=' . absint( $item['id'] ) . '&status=replied' ), 'cqfw_message_action_' . absint( $item['id'] ) ) ),
				esc_html__( 'Mark Replied', 'chat-quote-for-woocommerce' )
			),
			'delete'  => sprintf(
				'<a class="submitdelete" href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cqfw_delete_message&message_id=' . absint( $item['id'] ) ), 'cqfw_message_delete_' . absint( $item['id'] ) ) ),
				esc_js( __( 'Delete this message?', 'chat-quote-for-woocommerce' ) ),
				esc_html__( 'Delete', 'chat-quote-for-woocommerce' )
			),
		);

		return sprintf(
			'<strong>%1$s</strong><br />%2$s',
			esc_html( $item['name'] ),
			implode( ' | ', $actions )
		);
	}

	/**
	 * Checkbox column not used.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array();
	}
}
