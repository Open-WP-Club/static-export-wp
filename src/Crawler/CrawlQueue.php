<?php

declare(strict_types=1);

namespace StaticExportWP\Crawler;

final class CrawlQueue {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'sewp_crawl_queue';
	}

	/**
	 * Enqueue a batch of URLs for an export.
	 *
	 * @param string   $export_id
	 * @param string[] $urls
	 * @return int Number of URLs enqueued (excludes duplicates).
	 */
	public function enqueue( string $export_id, array $urls ): int {
		global $wpdb;

		$count = 0;
		$table = $this->table();

		foreach ( $urls as $url ) {
			$url_hash = hash( 'sha256', $url );
			$result   = $wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$table} (export_id, url, url_hash, status, created_at, updated_at)
				VALUES (%s, %s, %s, 'pending', NOW(), NOW())",
				$export_id,
				$url,
				$url_hash,
			) );

			if ( false !== $result && $result > 0 ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get the next batch of pending URLs.
	 *
	 * @return object[] Array of queue row objects.
	 */
	public function get_next_batch( string $export_id, int $batch_size ): array {
		global $wpdb;

		$table = $this->table();
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE export_id = %s AND status = 'pending'
			ORDER BY id ASC
			LIMIT %d",
			$export_id,
			$batch_size,
		) );

		// Mark as processing to prevent other workers from picking them up.
		if ( ! empty( $rows ) ) {
			$ids         = wp_list_pluck( $rows, 'id' );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET status = 'processing', updated_at = NOW() WHERE id IN ({$placeholders})",
				...$ids,
			) );
		}

		return $rows ?: [];
	}

	public function mark_completed( int $id, int $http_status, string $content_type, string $output_path ): void {
		global $wpdb;

		$wpdb->update(
			$this->table(),
			[
				'status'       => 'completed',
				'http_status'  => $http_status,
				'content_type' => $content_type,
				'output_path'  => $output_path,
				'updated_at'   => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s', '%s', '%s' ],
			[ '%d' ],
		);
	}

	public function mark_failed( int $id, string $error, int $http_status = 0 ): void {
		global $wpdb;

		$table = $this->table();
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table}
			SET status = 'failed', error_message = %s, http_status = %d,
				attempts = attempts + 1, updated_at = NOW()
			WHERE id = %d",
			$error,
			$http_status,
			$id,
		) );
	}

	/**
	 * Reset failed items that haven't exceeded max retries back to pending.
	 */
	public function retry_failed( string $export_id, int $max_retries ): int {
		global $wpdb;

		$table = $this->table();
		return (int) $wpdb->query( $wpdb->prepare(
			"UPDATE {$table}
			SET status = 'pending', updated_at = NOW()
			WHERE export_id = %s AND status = 'failed' AND attempts < %d",
			$export_id,
			$max_retries,
		) );
	}

	/**
	 * Get count of URLs by status.
	 *
	 * @return array{pending: int, processing: int, completed: int, failed: int, total: int}
	 */
	public function get_counts( string $export_id ): array {
		global $wpdb;

		$table   = $this->table();
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT status, COUNT(*) as cnt FROM {$table} WHERE export_id = %s GROUP BY status",
			$export_id,
		) );

		$counts = [
			'pending'    => 0,
			'processing' => 0,
			'completed'  => 0,
			'failed'     => 0,
			'total'      => 0,
		];

		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->cnt;
		}

		$counts['total'] = $counts['pending'] + $counts['processing'] + $counts['completed'] + $counts['failed'];

		return $counts;
	}

	public function has_pending( string $export_id ): bool {
		global $wpdb;

		$table = $this->table();
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE export_id = %s AND status IN ('pending', 'processing')",
			$export_id,
		) );

		return $count > 0;
	}

	public function clear( string $export_id ): void {
		global $wpdb;
		$wpdb->delete( $this->table(), [ 'export_id' => $export_id ], [ '%s' ] );
	}
}
