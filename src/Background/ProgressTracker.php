<?php

declare(strict_types=1);

namespace StaticExportWP\Background;

final class ProgressTracker {

	private const string OPTION_KEY = 'sewp_export_progress';

	public function start( string $export_id, int $total ): void {
		update_option( self::OPTION_KEY, [
			'export_id'   => $export_id,
			'status'      => 'running',
			'total'       => $total,
			'completed'   => 0,
			'failed'      => 0,
			'current_url' => '',
			'started_at'  => current_time( 'mysql' ),
		] );
	}

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

	public function update_total( string $export_id, int $total ): void {
		$progress = $this->get();

		if ( ! $progress || $progress['export_id'] !== $export_id ) {
			return;
		}

		$progress['total'] = $total;
		update_option( self::OPTION_KEY, $progress );
	}

	public function update_status( string $export_id, string $status ): void {
		$progress = $this->get();

		if ( ! $progress || $progress['export_id'] !== $export_id ) {
			return;
		}

		$progress['status'] = $status;
		update_option( self::OPTION_KEY, $progress );
	}

	public function finish( string $export_id, string $status = 'completed' ): void {
		$progress = $this->get();

		if ( ! $progress || $progress['export_id'] !== $export_id ) {
			return;
		}

		$progress['status']       = $status;
		$progress['completed_at'] = current_time( 'mysql' );

		update_option( self::OPTION_KEY, $progress );
	}

	public function cancel( string $export_id ): void {
		$this->update_status( $export_id, 'cancelled' );
	}

	public function is_cancelled( string $export_id ): bool {
		$progress = $this->get();
		return $progress
			&& $progress['export_id'] === $export_id
			&& 'cancelled' === $progress['status'];
	}

	public function is_running(): bool {
		$progress = $this->get();
		return $progress && 'running' === ( $progress['status'] ?? '' );
	}

	/**
	 * @return array|null
	 */
	public function get(): ?array {
		$data = get_option( self::OPTION_KEY );
		return is_array( $data ) ? $data : null;
	}

	public function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}
