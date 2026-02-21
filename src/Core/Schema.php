<?php

declare(strict_types=1);

namespace StaticExportWP\Core;

final class Schema {

	public const DB_VERSION = '1.0.0';

	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$crawl_queue_table = $wpdb->prefix . 'sewp_crawl_queue';
		$export_log_table  = $wpdb->prefix . 'sewp_export_log';

		$sql = "CREATE TABLE {$crawl_queue_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			export_id bigint(20) unsigned NOT NULL,
			url text NOT NULL,
			url_hash varchar(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			http_status smallint unsigned DEFAULT NULL,
			content_type varchar(100) DEFAULT NULL,
			output_path text DEFAULT NULL,
			error_message text DEFAULT NULL,
			attempts tinyint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY export_status (export_id, status),
			UNIQUE KEY export_url_hash (export_id, url_hash)
		) {$charset_collate};

		CREATE TABLE {$export_log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			export_id varchar(36) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			output_dir text NOT NULL,
			base_url text DEFAULT NULL,
			url_mode varchar(20) NOT NULL DEFAULT 'relative',
			total_urls int unsigned NOT NULL DEFAULT 0,
			completed_urls int unsigned NOT NULL DEFAULT 0,
			failed_urls int unsigned NOT NULL DEFAULT 0,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			settings_snapshot longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY export_id (export_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'sewp_db_version', self::DB_VERSION );
	}
}
