<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Background;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class ProgressTrackerTest extends TestCase {

	use WpStubHelpers;

	private ProgressTracker $tracker;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->tracker = new ProgressTracker();
	}

	public function test_start_creates_running_progress(): void {
		$this->tracker->start( 'exp-1', 100 );

		$progress = $this->tracker->get();
		$this->assertNotNull( $progress );
		$this->assertSame( 'exp-1', $progress['export_id'] );
		$this->assertSame( 'running', $progress['status'] );
		$this->assertSame( 100, $progress['total'] );
		$this->assertSame( 0, $progress['completed'] );
		$this->assertSame( 0, $progress['failed'] );
	}

	public function test_update_counts(): void {
		$this->tracker->start( 'exp-1', 100 );
		$this->tracker->update_counts( 'exp-1', 50, 5, 'https://example.com/about/' );

		$progress = $this->tracker->get();
		$this->assertSame( 50, $progress['completed'] );
		$this->assertSame( 5, $progress['failed'] );
		$this->assertSame( 'https://example.com/about/', $progress['current_url'] );
	}

	public function test_update_counts_ignores_wrong_export_id(): void {
		$this->tracker->start( 'exp-1', 100 );
		$this->tracker->update_counts( 'wrong-id', 50, 5 );

		$progress = $this->tracker->get();
		$this->assertSame( 0, $progress['completed'] );
	}

	public function test_update_total(): void {
		$this->tracker->start( 'exp-1', 100 );
		$this->tracker->update_total( 'exp-1', 200 );

		$progress = $this->tracker->get();
		$this->assertSame( 200, $progress['total'] );
	}

	public function test_update_total_ignores_wrong_id(): void {
		$this->tracker->start( 'exp-1', 100 );
		$this->tracker->update_total( 'wrong-id', 200 );

		$progress = $this->tracker->get();
		$this->assertSame( 100, $progress['total'] );
	}

	public function test_update_status(): void {
		$this->tracker->start( 'exp-1', 100 );
		$this->tracker->update_status( 'exp-1', 'deploying' );

		$progress = $this->tracker->get();
		$this->assertSame( 'deploying', $progress['status'] );
	}

	public function test_finish_sets_status_and_completed_at(): void {
		$this->tracker->start( 'exp-1', 100 );
		$this->tracker->finish( 'exp-1', 'completed' );

		$progress = $this->tracker->get();
		$this->assertSame( 'completed', $progress['status'] );
		$this->assertArrayHasKey( 'completed_at', $progress );
	}

	public function test_finish_ignores_wrong_id(): void {
		$this->tracker->start( 'exp-1', 100 );
		$this->tracker->finish( 'wrong-id' );

		$progress = $this->tracker->get();
		$this->assertSame( 'running', $progress['status'] );
	}

	public function test_cancel_sets_cancelled(): void {
		$this->tracker->start( 'exp-1', 100 );
		$this->tracker->cancel( 'exp-1' );

		$this->assertTrue( $this->tracker->is_cancelled( 'exp-1' ) );
	}

	public function test_is_cancelled_false_for_running(): void {
		$this->tracker->start( 'exp-1', 100 );

		$this->assertFalse( $this->tracker->is_cancelled( 'exp-1' ) );
	}

	public function test_is_running(): void {
		$this->assertFalse( $this->tracker->is_running() );

		$this->tracker->start( 'exp-1', 100 );
		$this->assertTrue( $this->tracker->is_running() );

		$this->tracker->finish( 'exp-1' );
		$this->assertFalse( $this->tracker->is_running() );
	}

	public function test_get_returns_null_when_empty(): void {
		$this->assertNull( $this->tracker->get() );
	}

	public function test_clear_removes_progress(): void {
		$this->tracker->start( 'exp-1', 100 );
		$this->tracker->clear();

		$this->assertNull( $this->tracker->get() );
	}
}
