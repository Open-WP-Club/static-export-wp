<?php

declare(strict_types=1);

namespace StaticExportWP\Background;

final class ActionSchedulerBridge {

	private const HOOK = 'sewp_process_batch';

	/**
	 * Schedule the next batch processing action.
	 */
	public function schedule_batch( string $export_id ): void {
		$args = [ $export_id ];

		if ( $this->has_action_scheduler() ) {
			as_enqueue_async_action( self::HOOK, $args, 'static-export-wp' );
		} else {
			wp_schedule_single_event( time(), self::HOOK, $args );
			// Spawn a loopback request to trigger wp_cron.
			spawn_cron();
		}
	}

	/**
	 * Unschedule all pending batch actions.
	 */
	public function unschedule_all(): void {
		if ( $this->has_action_scheduler() ) {
			as_unschedule_all_actions( self::HOOK, [], 'static-export-wp' );
		}

		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Check if there's already a pending batch action.
	 */
	public function has_pending( string $export_id ): bool {
		$args = [ $export_id ];

		if ( $this->has_action_scheduler() ) {
			return as_has_scheduled_action( self::HOOK, $args, 'static-export-wp' );
		}

		return (bool) wp_next_scheduled( self::HOOK, $args );
	}

	private function has_action_scheduler(): bool {
		return function_exists( 'as_enqueue_async_action' );
	}
}
