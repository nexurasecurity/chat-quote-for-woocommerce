<?php
/**
 * Plugin installer.
 *
 * @package Chat Quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQFW_Installer {

	/**
	 * Run on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::migrate_old_data();
		self::create_tables();
		self::update_db_check();

		// Only bump version when core tables exist (ALTER may fail on locked shared hosts).
		global $wpdb;
		$messages  = CQFW_Analytics::get_table( 'messages' );
		$quotes    = CQFW_Analytics::get_table( 'quotes' );
		$inquiries = CQFW_Analytics::get_table( 'inquiries' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ok_messages = $messages && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $messages ) ) === $messages;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ok_quotes = $quotes && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $quotes ) ) === $quotes;

		if ( $ok_messages && $ok_quotes ) {
			update_option( 'cqfw_version', CQFW_VERSION );
		} else {
			update_option( 'cqfw_db_setup_failed', 1 );
		}

		if ( false === get_option( CQFW_Settings::OPTION_NAME, false ) ) {
			add_option( CQFW_Settings::OPTION_NAME, CQFW_Settings::get_default_settings() );
		}
	}

	/**
	 * Migrate settings options and database tables from wcq to cqfw prefix.
	 *
	 * @return void
	 */
	private static function migrate_old_data() {
		global $wpdb;

		// 1. Migrate Settings Options.
		$old_settings = get_option( 'wcq_settings' );
		if ( false !== $old_settings ) {
			update_option( 'cqfw_settings', $old_settings );
			delete_option( 'wcq_settings' );
		}

		$old_version = get_option( 'wcq_version' );
		if ( false !== $old_version ) {
			update_option( 'cqfw_version', $old_version );
			delete_option( 'wcq_version' );
		}

		// 2. Migrate Database Tables.
		$old_analytics_table = esc_sql( $wpdb->prefix . 'wcq_analytics' );
		$new_analytics_table = esc_sql( $wpdb->prefix . 'cqfw_analytics' );
		$old_messages_table  = esc_sql( $wpdb->prefix . 'wcq_chat_messages' );
		$new_messages_table  = esc_sql( $wpdb->prefix . 'cqfw_chat_messages' );

		// Rename analytics table if old exists and new does not.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_analytics_table ) ) === $old_analytics_table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_analytics_table ) ) !== $new_analytics_table ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "RENAME TABLE `{$old_analytics_table}` TO `{$new_analytics_table}`" );
			}
		}

		// Rename messages table if old exists and new does not.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_messages_table ) ) === $old_messages_table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_messages_table ) ) !== $new_messages_table ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "RENAME TABLE `{$old_messages_table}` TO `{$new_messages_table}`" );
			}
		}
	}

	/**
	 * Create plugin tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$analytics_table = CQFW_Analytics::get_table( 'analytics' );
		$messages_table  = CQFW_Analytics::get_table( 'messages' );
		$quotes_table    = CQFW_Analytics::get_table( 'quotes' );

		$sql_analytics = "CREATE TABLE {$analytics_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NULL DEFAULT NULL,
			page_type varchar(50) NOT NULL,
			click_date datetime NOT NULL,
			ip_hash varchar(64) NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY page_type (page_type),
			KEY click_date (click_date)
		) {$charset_collate};";

		$sql_messages = "CREATE TABLE {$messages_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL DEFAULT '',
			sender varchar(10) NOT NULL DEFAULT 'visitor',
			name varchar(191) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			message longtext NOT NULL,
			product_id bigint(20) unsigned NULL DEFAULT NULL,
			page_url longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY product_id (product_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_quotes = "CREATE TABLE {$quotes_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			product_id bigint(20) unsigned NULL DEFAULT NULL,
			quantity int(11) NOT NULL DEFAULT 1,
			budget varchar(100) NOT NULL DEFAULT '',
			message longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'new',
			admin_response longtext NOT NULL,
			quoted_price varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$inquiries_table = CQFW_Analytics::get_table( 'inquiries' );

		$sql_inquiries = "CREATE TABLE {$inquiries_table} (
			id            bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id    bigint(20) unsigned NULL DEFAULT NULL,
			customer_name varchar(191) NOT NULL DEFAULT '',
			email         varchar(191) NOT NULL DEFAULT '',
			phone         varchar(50)  NOT NULL DEFAULT '',
			inquiry_type  varchar(100) NOT NULL DEFAULT 'General Question',
			question      longtext     NOT NULL,
			custom_fields longtext     NULL,
			status        varchar(20)  NOT NULL DEFAULT 'pending',
			admin_reply   longtext     NOT NULL,
			replied_at    datetime     NULL DEFAULT NULL,
			created_at    datetime     NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_analytics );
		dbDelta( $sql_messages );
		dbDelta( $sql_quotes );
		dbDelta( $sql_inquiries );
	}

	/**
	 * Run upgrade checks to add new columns if they do not exist.
	 *
	 * @return void
	 */
	public static function update_db_check() {
		global $wpdb;
		$inquiries_table = CQFW_Analytics::get_table( 'inquiries' );
		if ( ! empty( $inquiries_table ) ) {
			// Ensure custom_fields column exists (added in v1.3.0).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$row = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$inquiries_table` LIKE %s", 'custom_fields' ) );
			if ( empty( $row ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( "ALTER TABLE `$inquiries_table` ADD `custom_fields` longtext NULL AFTER `question`" );
			}
		}
		$messages_table = CQFW_Analytics::get_table( 'messages' );
		$quotes_table   = CQFW_Analytics::get_table( 'quotes' );

		if ( ! empty( $messages_table ) ) {
			// Check if session_id column exists
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$row = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$messages_table` LIKE %s", 'session_id' ) );
			if ( empty( $row ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( "ALTER TABLE `$messages_table` ADD `session_id` varchar(64) NOT NULL DEFAULT '' AFTER `id`" );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( "ALTER TABLE `$messages_table` ADD KEY `session_id` (`session_id`)" );
			}

			// Check if sender column exists
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$row = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$messages_table` LIKE %s", 'sender' ) );
			if ( empty( $row ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( "ALTER TABLE `$messages_table` ADD `sender` varchar(10) NOT NULL DEFAULT 'visitor' AFTER `session_id`" );
			}

			// Check if attachments column exists
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$row = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$messages_table` LIKE %s", 'attachments' ) );
			if ( empty( $row ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( "ALTER TABLE `$messages_table` ADD `attachments` longtext NULL AFTER `message`" );
			}
		}

		if ( ! empty( $quotes_table ) ) {
			// Check if custom_fields column exists
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$row = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$quotes_table` LIKE %s", 'custom_fields' ) );
			if ( empty( $row ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( "ALTER TABLE `$quotes_table` ADD `custom_fields` longtext NULL AFTER `message`" );
			}

			// Check if attachments column exists
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$row = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$quotes_table` LIKE %s", 'attachments' ) );
			if ( empty( $row ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( "ALTER TABLE `$quotes_table` ADD `attachments` longtext NULL AFTER `custom_fields`" );
			}

			// Check if order_id column exists
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$row = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$quotes_table` LIKE %s", 'order_id' ) );
			if ( empty( $row ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( "ALTER TABLE `$quotes_table` ADD `order_id` bigint(20) unsigned NULL DEFAULT NULL AFTER `quoted_price`" );
			}
		}
	}
}