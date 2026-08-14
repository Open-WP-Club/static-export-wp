<?php
/**
 * Persists per-URL content hashes to support incremental exports.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Export;

/**
 * Stores and retrieves content hashes for exported URLs, used by
 * incremental exports to skip URLs whose content hasn't changed.
 */
final class ContentHashStore {

	/**
	 * Get the stored content hash for a URL.
	 *
	 * @param string $url The URL to look up.
	 * @return string|null The content hash, or null if not found.
	 */
	public function get_hash( string $url ): ?string {
		global $wpdb;

		$table    = $wpdb->prefix . 'sewp_content_hashes';
		$url_hash = hash( 'sha256', $url );

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT content_hash FROM {$table} WHERE url_hash = %s",
				$url_hash,
			)
		);
	}

	/**
	 * Store (insert or update) a content hash for a URL.
	 *
	 * @param string $url          The URL the hash belongs to.
	 * @param string $content_hash The SHA256 content hash.
	 * @param string $output_path  The output file path the URL was written to.
	 * @param string $export_id    The export UUID that produced this hash.
	 */
	public function store_hash( string $url, string $content_hash, string $output_path, string $export_id ): void {
		global $wpdb;

		$table    = $wpdb->prefix . 'sewp_content_hashes';
		$url_hash = hash( 'sha256', $url );
		$now      = current_time( 'mysql' );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (url_hash, url, content_hash, output_path, last_export_id, updated_at)
			VALUES (%s, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE content_hash = VALUES(content_hash), output_path = VALUES(output_path), last_export_id = VALUES(last_export_id), updated_at = VALUES(updated_at)",
				$url_hash,
				$url,
				$content_hash,
				$output_path,
				$export_id,
				$now,
			)
		);
	}

	/**
	 * Compute a SHA256 hash of content.
	 *
	 * @param string $content The content to hash.
	 * @return string The SHA256 hash.
	 */
	public static function hash_content( string $content ): string {
		return hash( 'sha256', $content );
	}
}
