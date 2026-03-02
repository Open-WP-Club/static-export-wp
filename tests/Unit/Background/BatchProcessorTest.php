<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Background;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Background\ActionSchedulerBridge;
use StaticExportWP\Background\BatchProcessor;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Export\ExportJob;
use StaticExportWP\Export\ExportManager;
use StaticExportWP\Tests\Helpers\ReflectionHelper;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

/**
 * Tests for BatchProcessor.
 *
 * Uses real instances of all final classes, controlling behaviour through
 * the WP stub layer (options, wpdb, HTTP responses).
 */
final class BatchProcessorTest extends TestCase {

	use WpStubHelpers;

	private ProgressTracker $progress;
	private Settings $settings;
	private BatchProcessor $processor;

	protected function setUp(): void {
		$this->reset_wp_state();

		// Ensure wpdb stub is available.
		$GLOBALS['wpdb'] = new \wpdb();

		$this->settings = new Settings();
		$this->progress = new ProgressTracker();

		$this->set_option( 'sewp_settings', [
			'batch_size'  => 5,
			'max_retries' => 3,
		] );

		$crawl_queue    = new CrawlQueue();
		$scheduler      = new ActionSchedulerBridge();
		$export_manager = ReflectionHelper::buildRealExportManager(
			settings: $this->settings,
			progress: $this->progress,
			crawl_queue: $crawl_queue,
			scheduler: $scheduler,
		);

		$this->processor = new BatchProcessor(
			$export_manager,
			$crawl_queue,
			$this->progress,
			$scheduler,
			$this->settings,
		);
	}

	public function test_returns_early_when_cancelled(): void {
		// Set up progress as cancelled.
		$this->progress->start( 'exp-1', 10 );
		$this->progress->cancel( 'exp-1' );

		$this->processor->handle( 'exp-1' );

		// Progress should still show cancelled (not changed to completed/running).
		$data = $this->progress->get();
		$this->assertSame( 'cancelled', $data['status'] );
	}

	public function test_returns_early_when_no_current_job(): void {
		// No progress data set at all -- get_current_job() will return null.

		$this->processor->handle( 'exp-1' );

		// Nothing should have changed -- progress should still be null.
		$this->assertNull( $this->progress->get() );
	}

	public function test_returns_early_when_job_id_mismatch(): void {
		// Start a different export in progress.
		$this->progress->start( 'different-id', 10 );

		$this->processor->handle( 'exp-1' );

		// Progress should still show the original export, unchanged.
		$data = $this->progress->get();
		$this->assertSame( 'different-id', $data['export_id'] );
		$this->assertSame( 'running', $data['status'] );
	}

	public function test_finalizes_when_no_pending_batch(): void {
		// Start an export in progress.
		$this->progress->start( 'exp-1', 10 );

		// wpdb stub returns empty results for get_next_batch.
		// (default behaviour -- no _get_results_returns queued)

		$this->processor->handle( 'exp-1' );

		// After finalization, progress status should change from 'running'.
		$data = $this->progress->get();
		$this->assertNotNull( $data );
		// finalize() calls progress->finish() which sets status to 'completed' or 'failed'.
		$this->assertContains( $data['status'], [ 'completed', 'failed' ] );
	}

	public function test_processes_batch_and_schedules_next(): void {
		// Start an export in progress.
		$this->progress->start( 'exp-1', 10 );

		// Configure wpdb to return a batch of queue items.
		$GLOBALS['wpdb']->_get_results_returns[] = [
			(object) [
				'id'        => 1,
				'url'       => 'https://example.com/',
				'export_id' => 'exp-1',
				'status'    => 'pending',
			],
		];
		// For UPDATE query (mark as processing).
		$GLOBALS['wpdb']->_query_returns[] = 1;

		// For get_counts() query in BatchProcessor (after process_batch).
		$GLOBALS['wpdb']->_get_results_returns[] = [
			(object) [ 'status' => 'completed', 'cnt' => 1 ],
			(object) [ 'status' => 'pending', 'cnt' => 9 ],
		];

		// For has_pending() query -- returns count > 0.
		$GLOBALS['wpdb']->_get_var_returns[] = '9';

		$this->processor->handle( 'exp-1' );

		// Verify that a cron event was scheduled (schedule_batch uses wp_schedule_single_event
		// when Action Scheduler is not available).
		global $_wp_cron_events;
		$batch_events = array_filter( $_wp_cron_events, fn( $e ) => $e['hook'] === 'sewp_process_batch' );
		$this->assertNotEmpty( $batch_events, 'Expected a cron event to be scheduled for the next batch' );
	}
}
