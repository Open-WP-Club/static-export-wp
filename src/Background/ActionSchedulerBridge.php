<?php
/**
 * Bridges background batch scheduling between Action Scheduler and wp_cron.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Background;

/**
 * Schedules and queries the "process batch" background action, preferring
 * Action Scheduler when it is available and falling back to wp_cron.
 */
final class ActionSchedulerBridge {

	private const string HOOK = 'sewp_process_batch';

	/**
	 * Schedule the next batch processing action.
	 *
	 * @param string $export_id Export identifier the batch belongs to.
	 */
	public function schedule_batch( string $export_id ): void {
		$args = array( $export_id );

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
			as_unschedule_all_actions( self::HOOK, array(), 'static-export-wp' );
		}

		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Check if there's already a pending batch action.
	 *
	 * @param string $export_id Export identifier to check.
	 *
	 * @return bool True if a batch action is already scheduled.
	 */
	public function has_pending( string $export_id ): bool {
		$args = array( $export_id );

		if ( $this->has_action_scheduler() ) {
			return as_has_scheduled_action( self::HOOK, $args, 'static-export-wp' );
		}

		return (bool) wp_next_scheduled( self::HOOK, $args );
	}

	/**
	 * Determine whether the Action Scheduler library is available.
	 *
	 * @return bool True if Action Scheduler functions can be used.
	 */
	private function has_action_scheduler(): bool {
		return function_exists( 'as_enqueue_async_action' );
	}
}
