<?php

declare(strict_types=1);

namespace StaticExportWP\Export;

final class ContentHashStore {

	/**
	 * Get the stored content hash for a URL.
	 *
	 * @return string|null The content hash, or null if not found.
	 */
	public function get_hash( string $url ): ?string {
		global $wpdb;

		$table    = $wpdb->prefix . 'sewp_content_hashes';
		$url_hash = hash( 'sha256', $url );

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT content_hash FROM {$table} WHERE url_hash = %s",
			$url_hash,
		) );
	}

	/**
	 * Store (insert or update) a content hash for a URL.
	 */
	public function store_hash( string $url, string $content_hash, string $output_path, string $export_id ): void {
		global $wpdb;

		$table    = $wpdb->prefix . 'sewp_content_hashes';
		$url_hash = hash( 'sha256', $url );
		$now      = current_time( 'mysql' );

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (url_hash, url, content_hash, output_path, last_export_id, updated_at)
			VALUES (%s, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE content_hash = VALUES(content_hash), output_path = VALUES(output_path), last_export_id = VALUES(last_export_id), updated_at = VALUES(updated_at)",
			$url_hash,
			$url,
			$content_hash,
			$output_path,
			$export_id,
			$now,
		) );
	}

	/**
	 * Compute a SHA256 hash of content.
	 */
	public static function hash_content( string $content ): string {
		return hash( 'sha256', $content );
	}
}
