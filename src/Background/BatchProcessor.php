<?php

declare(strict_types=1);

namespace StaticExportWP\Background;

use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Export\ExportManager;

final class BatchProcessor {

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
	 */
	public function handle( string $export_id ): void {
		// Check if export has been cancelled.
		if ( $this->progress->is_cancelled( $export_id ) ) {
			return;
		}

		$job = $this->export_manager->get_current_job();
		if ( ! $job || $job->export_id !== $export_id ) {
			return;
		}

		$batch_size = (int) $this->settings->get( 'batch_size', 10 );
		$batch      = $this->crawl_queue->get_next_batch( $export_id, $batch_size );

		if ( empty( $batch ) ) {
			// No more pending URLs — finalize.
			$this->export_manager->finalize( $job );
			return;
		}

		// Process entire batch with parallel HTTP fetching.
		$this->export_manager->process_batch( $job, $batch );

		// Update progress once after the whole batch.
		$counts   = $this->crawl_queue->get_counts( $export_id );
		$last_url = end( $batch ) ? end( $batch )->url : '';
		$this->progress->update_counts(
			$export_id,
			$counts['completed'],
			$counts['failed'],
			$last_url,
		);

		// Schedule next batch if there are still pending URLs.
		if ( $this->crawl_queue->has_pending( $export_id ) ) {
			$this->scheduler->schedule_batch( $export_id );
		} else {
			$this->export_manager->finalize( $job );
		}
	}
}
