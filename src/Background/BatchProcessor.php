<?php
/**
 * Processes a single batch of URLs for a running export, then reschedules
 * or finalizes the export as needed.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Background;

use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Export\ExportManager;

/**
 * Fetches and processes the next batch of queued URLs for an export,
 * applying rate limiting, updating progress, and scheduling the next batch
 * or finalizing the export once the queue is drained.
 */
final class BatchProcessor {

	/**
	 * Constructor.
	 *
	 * @param ExportManager         $export_manager Runs the actual export work.
	 * @param CrawlQueue            $crawl_queue    Tracks pending/failed URLs for the export.
	 * @param ProgressTracker       $progress       Tracks and reports export progress.
	 * @param ActionSchedulerBridge $scheduler      Schedules the next batch action.
	 * @param Settings              $settings       Plugin settings.
	 */
	public function __construct(
		private readonly ExportManager $export_manager,
		private readonly CrawlQueue $crawl_queue,
		private readonly ProgressTracker $progress,
		private readonly ActionSchedulerBridge $scheduler,
		private readonly Settings $settings,
	) {}

	/**
	 * Handle a batch processing action.
	 * This is called by Action Scheduler or wp_cron.
	 *
	 * @param string $export_id Export identifier the batch belongs to.
	 */
	public function handle( string $export_id ): void {
		if ( $this->progress->is_cancelled( $export_id ) ) {
			return;
		}

		$job = $this->export_manager->get_current_job();
		if ( ! $job || $job->export_id !== $export_id ) {
			return;
		}

		$batch_size  = (int) $this->settings->get( 'batch_size', 10 );
		$max_retries = (int) $this->settings->get( 'max_retries', 3 );
		$batch       = $this->crawl_queue->get_next_batch( $export_id, $batch_size );

		if ( empty( $batch ) ) {
			// No pending items — retry failed URLs before deciding to finalize.
			$this->maybe_retry_or_finalize( $export_id, $job, $max_retries );
			return;
		}

		// Rate limiting: track batch duration and sleep for remainder.
		$rate_limit  = max( 1, (int) $this->settings->get( 'rate_limit', 50 ) );
		$delay_us    = (int) ( 1_000_000 / $rate_limit );
		$batch_start = microtime( true );

		$this->export_manager->process_batch( $job, $batch );

		$elapsed_us  = (int) ( ( microtime( true ) - $batch_start ) * 1_000_000 );
		$expected_us = count( $batch ) * $delay_us;
		if ( $elapsed_us < $expected_us ) {
			usleep( $expected_us - $elapsed_us );
		}

		$counts   = $this->crawl_queue->get_counts( $export_id );
		$last_url = end( $batch ) ? end( $batch )->url : '';
		$this->progress->update_counts(
			$export_id,
			$counts['completed'],
			$counts['failed'],
			$last_url,
		);

		if ( $this->crawl_queue->has_pending( $export_id ) ) {
			$this->scheduler->schedule_batch( $export_id );
		} else {
			$this->maybe_retry_or_finalize( $export_id, $job, $max_retries );
		}
	}

	/**
	 * Retry any failed URLs; if there are retried items schedule another batch,
	 * otherwise finalize the export.
	 *
	 * @param string                           $export_id   Export identifier the batch belongs to.
	 * @param \StaticExportWP\Export\ExportJob $job         Current export job.
	 * @param int                              $max_retries Maximum retry attempts allowed per URL.
	 */
	private function maybe_retry_or_finalize( string $export_id, \StaticExportWP\Export\ExportJob $job, int $max_retries ): void {
		if ( $this->crawl_queue->retry_failed( $export_id, $max_retries ) > 0 ) {
			$this->scheduler->schedule_batch( $export_id );
		} else {
			$this->export_manager->finalize( $job );
		}
	}
}
