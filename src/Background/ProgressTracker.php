<?php
/**
 * Tracks the progress of the currently running (or last) static export.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Background;

/**
 * Persists export progress data in a single WordPress option so the admin UI
 * and background processes can read and update it.
 */
final class ProgressTracker {

	private const string OPTION_KEY = 'sewp_export_progress';

	/**
	 * Initialize progress tracking for a new export.
	 *
	 * @param string $export_id Export identifier.
	 * @param int    $total     Total number of URLs expected to be processed.
	 */
	public function start( string $export_id, int $total ): void {
		update_option(
			self::OPTION_KEY,
			array(
				'export_id'   => $export_id,
				'status'      => 'running',
				'total'       => $total,
				'completed'   => 0,
				'failed'      => 0,
				'current_url' => '',
				'started_at'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Update the completed/failed counters and currently processed URL.
	 *
	 * @param string $export_id   Export identifier the progress belongs to.
	 * @param int    $completed   Number of URLs completed so far.
	 * @param int    $failed      Number of URLs that failed so far.
	 * @param string $current_url URL currently being processed, if any.
	 */
	public function update_counts( string $export_id, int $completed, int $failed, string $current_url = '' ): void {
		$progress = $this->get();

		if ( ! $progress || $progress['export_id'] !== $export_id ) {
			return;
		}

		$progress['completed']   = $completed;
		$progress['failed']      = $failed;
		$progress['current_url'] = $current_url;

		update_option( self::OPTION_KEY, $progress );
	}

	/**
	 * Update the total number of URLs expected for the export.
	 *
	 * @param string $export_id Export identifier the progress belongs to.
	 * @param int    $total     New total URL count.
	 */
	public function update_total( string $export_id, int $total ): void {
		$progress = $this->get();

		if ( ! $progress || $progress['export_id'] !== $export_id ) {
			return;
		}

		$progress['total'] = $total;
		update_option( self::OPTION_KEY, $progress );
	}

	/**
	 * Update the status of the tracked export.
	 *
	 * @param string $export_id Export identifier the progress belongs to.
	 * @param string $status    New status value (e.g. 'running', 'cancelled').
	 */
	public function update_status( string $export_id, string $status ): void {
		$progress = $this->get();

		if ( ! $progress || $progress['export_id'] !== $export_id ) {
			return;
		}

		$progress['status'] = $status;
		update_option( self::OPTION_KEY, $progress );
	}

	/**
	 * Mark the export as finished and record the completion time.
	 *
	 * @param string $export_id Export identifier the progress belongs to.
	 * @param string $status    Final status to record (defaults to 'completed').
	 */
	public function finish( string $export_id, string $status = 'completed' ): void {
		$progress = $this->get();

		if ( ! $progress || $progress['export_id'] !== $export_id ) {
			return;
		}

		$progress['status']       = $status;
		$progress['completed_at'] = current_time( 'mysql' );

		update_option( self::OPTION_KEY, $progress );
	}

	/**
	 * Mark the export as cancelled.
	 *
	 * @param string $export_id Export identifier the progress belongs to.
	 */
	public function cancel( string $export_id ): void {
		$this->update_status( $export_id, 'cancelled' );
	}

	/**
	 * Check whether the given export has been cancelled.
	 *
	 * @param string $export_id Export identifier to check.
	 *
	 * @return bool True if the tracked progress belongs to this export and is cancelled.
	 */
	public function is_cancelled( string $export_id ): bool {
		$progress = $this->get();
		return $progress
			&& $progress['export_id'] === $export_id
			&& 'cancelled' === $progress['status'];
	}

	/**
	 * Check whether an export is currently running.
	 *
	 * @return bool True if the tracked progress status is 'running'.
	 */
	public function is_running(): bool {
		$progress = $this->get();
		return $progress && 'running' === ( $progress['status'] ?? '' );
	}

	/**
	 * Retrieve the current progress data.
	 *
	 * @return array|null Progress data array, or null if none is stored.
	 */
	public function get(): ?array {
		$data = get_option( self::OPTION_KEY );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Remove any stored progress data.
	 */
	public function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}
