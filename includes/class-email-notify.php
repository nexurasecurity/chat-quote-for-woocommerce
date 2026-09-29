<?php
/**
 * Instant email notifications (Fiverr-style) — no WP-Cron dependency.
 *
 * Shared hosting / AWS safe: sends in the same request as the message,
 * respects an hourly cap via DB options (not fragile transients), and
 * flushes backlog on the next request.
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_Email_Notify {

	public const QUEUE_OPTION = 'cqfw_pending_email_queue';
	public const CRON_HOOK    = 'cqfw_process_pending_email_queue';

	/** Seconds between follow-up emails for the same chat session. */
	public const SESSION_COOLDOWN = 120;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'cqfw_new_live_message', array( $this, 'on_live_message' ), 10, 4 );
		add_action( 'init', array( $this, 'disable_legacy_cron' ), 1 );
	}

	/**
	 * Remove legacy hourly cron — shared hosts often never run it.
	 *
	 * @return void
	 */
	public function disable_legacy_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Whether admin email alerts are enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = class_exists( 'CQFW_Settings' ) ? CQFW_Settings::get_settings() : array();
		if ( isset( $settings['email_notify_enabled'] ) ) {
			return ! empty( $settings['email_notify_enabled'] );
		}
		return true;
	}

	/**
	 * Notification recipient (custom or WP admin email).
	 *
	 * @return string
	 */
	public static function get_notify_email() {
		$settings = class_exists( 'CQFW_Settings' ) ? CQFW_Settings::get_settings() : array();
		$custom   = isset( $settings['email_notify_address'] ) ? sanitize_email( $settings['email_notify_address'] ) : '';
		if ( is_email( $custom ) ) {
			return $custom;
		}
		return (string) get_option( 'admin_email' );
	}

	/**
	 * Hourly send cap (default 100 for shared hosting).
	 *
	 * @return int
	 */
	public static function get_hourly_limit() {
		$settings = class_exists( 'CQFW_Settings' ) ? CQFW_Settings::get_settings() : array();
		if ( isset( $settings['max_emails_per_hour'] ) ) {
			return absint( $settings['max_emails_per_hour'] );
		}
		return 100;
	}

	/**
	 * New visitor chat message → instant email (first msg always; then cooldown).
	 *
	 * @param int    $msg_id     Message ID.
	 * @param string $session_id Session ID.
	 * @param string $name       Visitor name.
	 * @param string $message    Message text.
	 * @return void
	 */
	public function on_live_message( $msg_id, $session_id, $name, $message ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$session_id = sanitize_text_field( (string) $session_id );
		$name       = sanitize_text_field( (string) $name );
		$message    = sanitize_textarea_field( (string) $message );
		$msg_id     = absint( $msg_id );

		if ( '' === $session_id || '' === $message ) {
			return;
		}

		$visitor_count = self::count_visitor_messages( $session_id );
		$is_first      = ( $visitor_count <= 1 );
		$hash          = md5( $session_id );
		$cool_key      = 'cqfw_email_cool_' . $hash;
		$pending_key   = 'cqfw_email_pending_' . $hash;

		if ( ! $is_first && get_transient( $cool_key ) ) {
			set_transient(
				$pending_key,
				array(
					'msg_id'  => $msg_id,
					'name'    => $name,
					'message' => $message,
				),
				self::SESSION_COOLDOWN + 60
			);
			return;
		}

		// Prefer pending stash if cooldown just expired.
		$pending = get_transient( $pending_key );
		if ( ! $is_first && is_array( $pending ) && ! empty( $pending['message'] ) ) {
			$name    = isset( $pending['name'] ) ? (string) $pending['name'] : $name;
			$message = (string) $pending['message'];
			$msg_id  = isset( $pending['msg_id'] ) ? absint( $pending['msg_id'] ) : $msg_id;
			delete_transient( $pending_key );
		}

		self::send_live_chat_email( $msg_id, $session_id, $name, $message, $is_first );

		if ( ! $is_first ) {
			set_transient( $cool_key, 1, self::SESSION_COOLDOWN );
		}
	}

	/**
	 * Count visitor messages in a session.
	 *
	 * @param string $session_id Session.
	 * @return int
	 */
	private static function count_visitor_messages( $session_id ) {
		global $wpdb;
		$table = class_exists( 'CQFW_Analytics' ) ? CQFW_Analytics::get_table( 'messages' ) : '';
		if ( ! $table ) {
			return 1;
		}
		// Table name comes from CQFW_Analytics whitelist ($wpdb->prefix . 'cqfw_chat_messages').
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$table` WHERE session_id = %s AND sender = %s", $session_id, 'visitor' ) );
	}

	/**
	 * Build + send the admin live-chat email.
	 *
	 * @param int    $msg_id     Message ID.
	 * @param string $session_id Session.
	 * @param string $name       Name.
	 * @param string $message    Body.
	 * @param bool   $is_first   First message in thread.
	 * @return bool
	 */
	public static function send_live_chat_email( $msg_id, $session_id, $name, $message, $is_first = false ) {
		$to = self::get_notify_email();
		if ( ! is_email( $to ) ) {
			return false;
		}

		$site  = get_bloginfo( 'name' );
		$inbox = admin_url( 'admin.php?page=cqfw-messages' );

		if ( $is_first ) {
			$subject = sprintf(
				/* translators: 1: site name, 2: visitor name */
				__( '[%1$s] New chat from %2$s', 'chat-quote-for-woocommerce' ),
				$site,
				$name ? $name : __( 'Visitor', 'chat-quote-for-woocommerce' )
			);
			$headline = __( 'New customer chat started', 'chat-quote-for-woocommerce' );
		} else {
			$subject = sprintf(
				/* translators: 1: site name, 2: visitor name */
				__( '[%1$s] New message from %2$s', 'chat-quote-for-woocommerce' ),
				$site,
				$name ? $name : __( 'Visitor', 'chat-quote-for-woocommerce' )
			);
			$headline = __( 'New message in Customer Inbox', 'chat-quote-for-woocommerce' );
		}

		$body  = '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:15px;line-height:1.5;color:#0f172a;">';
		$body .= '<h2 style="margin:0 0 12px;">' . esc_html( $headline ) . '</h2>';
		$body .= '<p style="margin:0 0 8px;"><strong>' . esc_html__( 'Visitor:', 'chat-quote-for-woocommerce' ) . '</strong> ' . esc_html( $name ? $name : __( 'Visitor', 'chat-quote-for-woocommerce' ) ) . '</p>';
		$body .= '<p style="margin:0 0 12px;"><strong>' . esc_html__( 'Message:', 'chat-quote-for-woocommerce' ) . '</strong></p>';
		$body .= '<blockquote style="margin:0 0 16px;padding:12px 14px;background:#f8fafc;border-left:4px solid #0f766e;">' . nl2br( esc_html( $message ) ) . '</blockquote>';
		$body .= '<p><a href="' . esc_url( $inbox ) . '" style="display:inline-block;background:#0f766e;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;">' . esc_html__( 'Open Customer Inbox', 'chat-quote-for-woocommerce' ) . '</a></p>';
		$body .= '<p style="margin-top:16px;font-size:12px;color:#64748b;">' . esc_html__( 'Sent instantly when the customer messaged — no cron delay.', 'chat-quote-for-woocommerce' ) . '</p>';
		$body .= '</div>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return self::send_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Hourly send counter key.
	 *
	 * @return string
	 */
	private static function hour_counter_key() {
		return 'cqfw_emails_sent_' . gmdate( 'YmdH' );
	}

	/**
	 * Read hourly send count (DB option — survives object-cache eviction better than transients alone).
	 *
	 * @return int
	 */
	private static function get_hour_count() {
		$key  = self::hour_counter_key();
		$data = get_option( $key, null );
		if ( ! is_array( $data ) || empty( $data['expires'] ) || (int) $data['expires'] < time() ) {
			return 0;
		}
		return isset( $data['count'] ) ? (int) $data['count'] : 0;
	}

	/**
	 * Increment hourly send count.
	 *
	 * @return void
	 */
	private static function bump_hour_count() {
		$key   = self::hour_counter_key();
		$data  = get_option( $key, null );
		$count = 0;
		if ( is_array( $data ) && ! empty( $data['expires'] ) && (int) $data['expires'] >= time() ) {
			$count = isset( $data['count'] ) ? (int) $data['count'] : 0;
		}
		update_option(
			$key,
			array(
				'count'   => $count + 1,
				'expires' => time() + HOUR_IN_SECONDS,
			),
			false
		);
	}

	/**
	 * Send mail immediately when under hourly cap; otherwise queue for next request.
	 *
	 * @param string|array $to          Recipient(s).
	 * @param string       $subject     Subject.
	 * @param string       $message     HTML/text body.
	 * @param array|string $headers     Headers.
	 * @param array        $attachments Attachments.
	 * @return bool
	 */
	public static function send_mail( $to, $subject, $message, $headers = array(), $attachments = array() ) {
		self::flush_queue( 5 );

		$limit = self::get_hourly_limit();
		$count = self::get_hour_count();

		if ( 0 === $limit || $count < $limit ) {
			$sent = wp_mail( $to, $subject, $message, $headers, $attachments );
			if ( $sent ) {
				if ( 0 !== $limit ) {
					self::bump_hour_count();
				}
				self::flush_queue( 3 );
				return true;
			}
			self::queue_mail( $to, $subject, $message, $headers, $attachments, 1 );
			return false;
		}

		self::queue_mail( $to, $subject, $message, $headers, $attachments );
		return false;
	}

	/**
	 * Queue email for the next request that has capacity.
	 *
	 * @param string|array $to          To.
	 * @param string       $subject     Subject.
	 * @param string       $message     Body.
	 * @param array|string $headers     Headers.
	 * @param array        $attachments Files.
	 * @param int          $retries     Prior failed attempts.
	 * @return void
	 */
	private static function queue_mail( $to, $subject, $message, $headers, $attachments, $retries = 0 ) {
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}
		if ( count( $queue ) >= 50 ) {
			$queue = array_slice( $queue, -40 );
		}
		$queue[] = array(
			'to'          => $to,
			'subject'     => $subject,
			'message'     => $message,
			'headers'     => $headers,
			'attachments' => $attachments,
			'retries'     => absint( $retries ),
			'queued_at'   => current_time( 'mysql' ),
		);
		update_option( self::QUEUE_OPTION, $queue, false );
	}

	/**
	 * Process queued emails in the current request (no cron).
	 *
	 * @param int $max Max items to send this request.
	 * @return void
	 */
	public static function flush_queue( $max = 5 ) {
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( empty( $queue ) || ! is_array( $queue ) ) {
			return;
		}

		$limit  = self::get_hourly_limit();
		$count  = self::get_hour_count();
		$max    = max( 1, absint( $max ) );
		$sent_n = 0;
		$remain = array();

		while ( ! empty( $queue ) && $sent_n < $max ) {
			$item = array_shift( $queue );
			if ( 0 !== $limit && $count >= $limit ) {
				$remain[] = $item;
				$remain   = array_merge( $remain, $queue );
				$queue    = array();
				break;
			}

			$ok = wp_mail(
				$item['to'],
				$item['subject'],
				$item['message'],
				isset( $item['headers'] ) ? $item['headers'] : array(),
				isset( $item['attachments'] ) ? $item['attachments'] : array()
			);

			if ( $ok ) {
				++$count;
				++$sent_n;
				if ( 0 !== $limit ) {
					self::bump_hour_count();
				}
				continue;
			}

			$retries = isset( $item['retries'] ) ? (int) $item['retries'] : 0;
			if ( $retries < 5 ) {
				$item['retries'] = $retries + 1;
				$remain[]        = $item;
			}
		}

		update_option( self::QUEUE_OPTION, array_merge( $remain, $queue ), false );
	}
}
