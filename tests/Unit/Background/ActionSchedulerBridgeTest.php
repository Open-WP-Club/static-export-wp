<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Background;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use StaticExportWP\Background\ActionSchedulerBridge;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

/**
 * Tests for ActionSchedulerBridge.
 *
 * By default (no Action Scheduler plugin present) as_*() functions do not
 * exist, so the bridge must fall back to wp_cron. The Action-Scheduler-
 * available path is tested in a separate process so the global as_*()
 * function definitions don't leak into (and change the behaviour of) the
 * rest of the test suite.
 */
final class ActionSchedulerBridgeTest extends TestCase {

	use WpStubHelpers;

	private ActionSchedulerBridge $bridge;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->bridge = new ActionSchedulerBridge();
	}

	// ── wp_cron fallback (Action Scheduler unavailable) ───────────────────────

	public function test_schedule_batch_falls_back_to_wp_cron(): void {
		$this->bridge->schedule_batch( 'exp-1' );

		global $_wp_cron_events;
		$events = array_filter( $_wp_cron_events, fn( $e ) => 'sewp_process_batch' === $e['hook'] );

		$this->assertNotEmpty( $events, 'Expected a wp_cron event when Action Scheduler is unavailable' );
	}

	public function test_schedule_batch_passes_export_id_as_arg(): void {
		$this->bridge->schedule_batch( 'exp-42' );

		global $_wp_cron_events;
		$events = array_values( array_filter( $_wp_cron_events, fn( $e ) => 'sewp_process_batch' === $e['hook'] ) );

		$this->assertNotEmpty( $events );
		$this->assertSame( [ 'exp-42' ], $events[0]['args'] );
	}

	public function test_unschedule_all_clears_wp_cron_hook(): void {
		$this->bridge->schedule_batch( 'exp-1' );
		$this->bridge->unschedule_all();

		$this->assertFalse( wp_next_scheduled( 'sewp_process_batch', [ 'exp-1' ] ) );
	}

	public function test_has_pending_false_when_nothing_scheduled(): void {
		$this->assertFalse( $this->bridge->has_pending( 'exp-1' ) );
	}

	public function test_has_pending_true_after_schedule_batch(): void {
		$this->bridge->schedule_batch( 'exp-1' );

		$this->assertTrue( $this->bridge->has_pending( 'exp-1' ) );
	}

	public function test_has_pending_is_scoped_to_export_id(): void {
		$this->bridge->schedule_batch( 'exp-1' );

		$this->assertFalse( $this->bridge->has_pending( 'exp-2' ) );
	}

	// ── Action Scheduler path (isolated process) ──────────────────────────────

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_schedule_batch_prefers_action_scheduler_when_available(): void {
		global $_as_calls;
		$_as_calls = [];

		require_once __DIR__ . '/../../Stubs/action-scheduler-stub-functions.php';

		$this->reset_wp_state();
		$bridge = new ActionSchedulerBridge();
		$bridge->schedule_batch( 'exp-1' );

		$this->assertCount( 1, $_as_calls );
		$this->assertSame( 'sewp_process_batch', $_as_calls[0]['hook'] );
		$this->assertSame( [ 'exp-1' ], $_as_calls[0]['args'] );

		// Must NOT also fall back to wp_cron.
		global $_wp_cron_events;
		$events = array_filter( $_wp_cron_events, fn( $e ) => 'sewp_process_batch' === $e['hook'] );
		$this->assertEmpty( $events );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_unschedule_all_uses_action_scheduler_when_available(): void {
		global $_as_calls;
		$_as_calls = [];

		require_once __DIR__ . '/../../Stubs/action-scheduler-stub-functions.php';

		$this->reset_wp_state();
		$bridge = new ActionSchedulerBridge();
		$bridge->unschedule_all();

		$this->assertCount( 1, $_as_calls );
		$this->assertSame( 'as_unschedule_all_actions', $_as_calls[0]['method'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_has_pending_uses_action_scheduler_when_available(): void {
		global $_as_calls;
		$_as_calls = [];

		require_once __DIR__ . '/../../Stubs/action-scheduler-stub-functions.php';

		$this->reset_wp_state();
		$bridge = new ActionSchedulerBridge();

		// Even with nothing scheduled via wp_cron, the AS-backed check must be used.
		$this->assertTrue( $bridge->has_pending( 'exp-1' ) );
	}
}
