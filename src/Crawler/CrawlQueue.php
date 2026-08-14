<?php
/**
 * Persists and manages the crawl queue used to track URLs across an export.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Crawler;

/**
 * Manages the database-backed queue of URLs to crawl for an export, including
 * enqueueing, batching, marking completion/failure, and status reporting.
 */
final class CrawlQueue {

	/**
	 * Get the fully-prefixed name of the crawl queue database table.
	 *
	 * @return string The table name.
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'sewp_crawl_queue';
	}

	/**
	 * Enqueue a batch of URLs for an export using multi-row INSERT.
	 *
	 * @param string   $export_id The export this queue belongs to.
	 * @param string[] $urls      URLs to enqueue.
	 * @param string   $referrer  The page URL that linked to these URLs.
	 * @return int Number of URLs enqueued (excludes duplicates).
	 */
	public function enqueue( string $export_id, array $urls, string $referrer = '' ): int {
		global $wpdb;

		if ( empty( $urls ) ) {
			return 0;
		}

		$table      = $this->table();
		$chunk_size = 100;
		$total      = 0;

		// Insert in chunks to avoid exceeding max_allowed_packet.
		foreach ( array_chunk( $urls, $chunk_size ) as $chunk ) {
			$values       = array();
			$placeholders = array();

			foreach ( $chunk as $url ) {
				$url_hash       = hash( 'sha256', $url );
				$placeholders[] = '(%s, %s, %s, %s, %s, NOW(), NOW())';
				$values[]       = $export_id;
				$values[]       = $url;
				$values[]       = $url_hash;
				$values[]       = 'pending';
				$values[]       = $referrer;
			}

			$sql = "INSERT IGNORE INTO {$table} (export_id, url, url_hash, status, referrer, created_at, updated_at) VALUES "
				. implode( ', ', $placeholders );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built dynamically above.
			$result = $wpdb->query( $wpdb->prepare( $sql, ...$values ) );

			if ( false !== $result ) {
				$total += $result;
			}
		}

		return $total;
	}

	/**
	 * Get the next batch of pending URLs.
	 *
	 * @param string $export_id  The export to fetch pending URLs for.
	 * @param int    $batch_size Maximum number of rows to return.
	 * @return object[] Array of queue row objects.
	 */
	public function get_next_batch( string $export_id, int $batch_size ): array {
		global $wpdb;

		$table = $this->table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is a fixed, plugin-controlled identifier, not user input.
				"SELECT * FROM {$table}
			WHERE export_id = %s AND status = 'pending'
			ORDER BY id ASC
			LIMIT %d",
				$export_id,
				$batch_size,
			)
		);

		// Mark as processing to prevent other workers from picking them up.
		if ( ! empty( $rows ) ) {
			$ids          = wp_list_pluck( $rows, 'id' );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- {$table} is fixed; {$placeholders} expands to literal %d tokens consumed by prepare() via ...$ids.
					"UPDATE {$table} SET status = 'processing', updated_at = NOW() WHERE id IN ({$placeholders})",
					...$ids,
				)
			);
		}

		return $rows ? $rows : array();
	}

	/**
	 * Mark a queue item as successfully completed.
	 *
	 * @param int    $id           The queue row ID.
	 * @param int    $http_status  The HTTP status code returned when fetching the URL.
	 * @param string $content_type The Content-Type of the fetched response.
	 * @param string $output_path  The path the fetched content was written to.
	 * @return void
	 */
	public function mark_completed( int $id, int $http_status, string $content_type, string $output_path ): void {
		global $wpdb;

		$wpdb->update(
			$this->table(),
			array(
				'status'       => 'completed',
				'http_status'  => $http_status,
				'content_type' => $content_type,
				'output_path'  => $output_path,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' ),
		);
	}

	/**
	 * Mark a queue item as failed and increment its attempt count.
	 *
	 * @param int    $id          The queue row ID.
	 * @param string $error       The error message describing the failure.
	 * @param int    $http_status The HTTP status code returned, if any.
	 * @return void
	 */
	public function mark_failed( int $id, string $error, int $http_status = 0 ): void {
		global $wpdb;

		$table = $this->table();
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is a fixed, plugin-controlled identifier, not user input.
				"UPDATE {$table}
			SET status = 'failed', error_message = %s, http_status = %d,
				attempts = attempts + 1, updated_at = NOW()
			WHERE id = %d",
				$error,
				$http_status,
				$id,
			)
		);
	}

	/**
	 * Reset failed items that haven't exceeded max retries back to pending.
	 *
	 * @param string $export_id   The export to retry failed items for.
	 * @param int    $max_retries Items with fewer than this many attempts are reset.
	 * @return int Number of items reset to pending.
	 */
	public function retry_failed( string $export_id, int $max_retries ): int {
		global $wpdb;

		$table = $this->table();
		return (int) $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is a fixed, plugin-controlled identifier, not user input.
				"UPDATE {$table}
			SET status = 'pending', updated_at = NOW()
			WHERE export_id = %s AND status = 'failed' AND attempts < %d",
				$export_id,
				$max_retries,
			)
		);
	}

	/**
	 * Get count of URLs by status.
	 *
	 * @param string $export_id The export to count queue items for.
	 * @return array{pending: int, processing: int, completed: int, failed: int, total: int}
	 */
	public function get_counts( string $export_id ): array {
		global $wpdb;

		$table   = $this->table();
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is a fixed, plugin-controlled identifier, not user input.
				"SELECT status, COUNT(*) as cnt FROM {$table} WHERE export_id = %s GROUP BY status",
				$export_id,
			)
		);

		$counts = array(
			'pending'    => 0,
			'processing' => 0,
			'completed'  => 0,
			'failed'     => 0,
			'total'      => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->cnt;
		}

		$counts['total'] = $counts['pending'] + $counts['processing'] + $counts['completed'] + $counts['failed'];

		return $counts;
	}

	/**
	 * Determine whether an export has any pending or processing queue items left.
	 *
	 * @param string $export_id The export to check.
	 * @return bool True if the export has unfinished items, false otherwise.
	 */
	public function has_pending( string $export_id ): bool {
		global $wpdb;

		$table = $this->table();
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is a fixed, plugin-controlled identifier, not user input.
				"SELECT COUNT(*) FROM {$table} WHERE export_id = %s AND status IN ('pending', 'processing')",
				$export_id,
			)
		);

		return $count > 0;
	}

	/**
	 * Get broken links (failed URLs with HTTP status >= 400) for an export.
	 *
	 * @param string $export_id The export to fetch broken links for.
	 * @return object[] Array of {url, http_status, error_message, referrer}.
	 */
	public function get_broken_links( string $export_id ): array {
		global $wpdb;

		$table = $this->table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is a fixed, plugin-controlled identifier, not user input.
				"SELECT url, http_status, error_message, referrer FROM {$table}
			WHERE export_id = %s AND status = 'failed' AND http_status >= 400
			ORDER BY http_status DESC, id ASC",
				$export_id,
			)
		);

		return $rows ? $rows : array();
	}

	/**
	 * Reset items stuck in 'processing' longer than $minutes back to 'pending'.
	 *
	 * Recovers from PHP crashes or timeouts that left items orphaned mid-batch.
	 *
	 * @param string $export_id The export to reset stale items for.
	 * @param int    $minutes   Items processing longer than this are reset.
	 * @return int Number of items reset to pending.
	 */
	public function reset_stale_processing( string $export_id, int $minutes = 30 ): int {
		global $wpdb;

		$table = $this->table();
		return (int) $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is a fixed, plugin-controlled identifier, not user input.
				"UPDATE {$table}
			SET status = 'pending', updated_at = NOW()
			WHERE export_id = %s AND status = 'processing'
			AND updated_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
				$export_id,
				$minutes,
			)
		);
	}

	/**
	 * Delete all queue items belonging to an export.
	 *
	 * @param string $export_id The export whose queue items should be removed.
	 * @return void
	 */
	public function clear( string $export_id ): void {
		global $wpdb;
		$wpdb->delete( $this->table(), array( 'export_id' => $export_id ), array( '%s' ) );
	}
}
