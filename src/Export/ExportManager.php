<?php

declare(strict_types=1);

namespace StaticExportWP\Export;

use StaticExportWP\Background\ActionSchedulerBridge;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Crawler\Fetcher;
use StaticExportWP\Crawler\UrlDiscovery;
use StaticExportWP\Utility\Logger;

final class ExportManager {

	public function __construct(
		private readonly Settings $settings,
		private readonly UrlDiscovery $url_discovery,
		private readonly Fetcher $fetcher,
		private readonly CrawlQueue $crawl_queue,
		private readonly HtmlProcessor $html_processor,
		private readonly FileWriter $file_writer,
		private readonly ProgressTracker $progress,
		private readonly ActionSchedulerBridge $scheduler,
		private readonly Logger $logger,
	) {}

	/**
	 * Start a new export.
	 *
	 * @param array $overrides Optional settings overrides.
	 * @return ExportJob
	 */
	public function start( array $overrides = [] ): ExportJob {
		$all_settings = $this->settings->get_all();
		$merged       = wp_parse_args( $overrides, $all_settings );

		$export_id  = wp_generate_uuid4();
		$output_dir = $merged['output_dir'];
		$url_mode   = $merged['url_mode'];
		$base_url   = $merged['base_url'];

		$this->logger->info( 'Starting export', [ 'export_id' => $export_id ] );

		// Discover URLs.
		$urls = $this->url_discovery->discover();
		$this->logger->info( 'Discovered URLs', [ 'count' => count( $urls ) ] );

		// Enqueue all URLs.
		$this->crawl_queue->enqueue( $export_id, $urls );

		// Save to export log table.
		$this->save_export_log( $export_id, $output_dir, $url_mode, $base_url, count( $urls ), $all_settings );

		// Set progress.
		$this->progress->start( $export_id, count( $urls ) );

		$job = new ExportJob(
			export_id: $export_id,
			output_dir: $output_dir,
			url_mode: $url_mode,
			base_url: $base_url,
			settings_snapshot: $all_settings,
			started_at: current_time( 'mysql' ),
		);

		return $job;
	}

	/**
	 * Start a background export (via Action Scheduler or wp_cron).
	 */
	public function start_background( array $overrides = [] ): ExportJob {
		$job = $this->start( $overrides );
		$this->scheduler->schedule_batch( $job->export_id );
		return $job;
	}

	/**
	 * Run the export synchronously (for CLI use).
	 *
	 * @param callable|null $on_progress Called after each URL with (completed, total, current_url).
	 */
	public function run_sync( array $overrides = [], ?callable $on_progress = null ): ExportJob {
		$job        = $this->start( $overrides );
		$batch_size = (int) $this->settings->get( 'batch_size', 10 );

		while ( $this->crawl_queue->has_pending( $job->export_id ) ) {
			if ( $this->progress->is_cancelled( $job->export_id ) ) {
				$this->progress->update_status( $job->export_id, 'cancelled' );
				break;
			}

			$batch = $this->crawl_queue->get_next_batch( $job->export_id, $batch_size );

			foreach ( $batch as $queue_item ) {
				$this->process_url( $job, $queue_item );

				$counts = $this->crawl_queue->get_counts( $job->export_id );
				$this->progress->update_counts(
					$job->export_id,
					$counts['completed'],
					$counts['failed'],
					$queue_item->url,
				);

				if ( $on_progress ) {
					$on_progress( $counts['completed'], $counts['total'], $queue_item->url );
				}
			}
		}

		$this->finalize( $job );

		return $job;
	}

	/**
	 * Process a single URL from the queue.
	 */
	public function process_url( ExportJob $job, object $queue_item ): void {
		$this->logger->info( 'Processing URL', [ 'url' => $queue_item->url ] );

		$result = $this->fetcher->fetch( $queue_item->url );

		if ( ! $result->is_success() ) {
			$this->crawl_queue->mark_failed(
				(int) $queue_item->id,
				$result->error ?? "HTTP {$result->http_status}",
				$result->http_status,
			);
			return;
		}

		if ( $result->is_html() ) {
			$processed = $this->html_processor->process(
				$result->body,
				$queue_item->url,
				$job->url_mode,
				$job->base_url,
			);

			$output_path = $this->file_writer->write_html(
				$job->output_dir,
				$queue_item->url,
				$processed['html'],
			);

			// Enqueue newly discovered URLs.
			if ( ! empty( $processed['discovered_urls'] ) ) {
				$this->crawl_queue->enqueue( $job->export_id, $processed['discovered_urls'] );
				// Update total count.
				$counts = $this->crawl_queue->get_counts( $job->export_id );
				$this->progress->update_total( $job->export_id, $counts['total'] );
			}

			// Copy assets.
			$site_url = untrailingslashit( home_url() );
			foreach ( $processed['assets'] as $asset_url ) {
				$this->file_writer->copy_asset( $job->output_dir, $asset_url, $site_url );
			}
		} else {
			// Non-HTML (e.g., XML feeds) — save as-is.
			$output_path = $this->file_writer->write_html(
				$job->output_dir,
				$queue_item->url,
				$result->body,
			);
		}

		if ( false !== $output_path ) {
			$this->crawl_queue->mark_completed(
				(int) $queue_item->id,
				$result->http_status,
				$result->content_type,
				$output_path,
			);
		} else {
			$this->crawl_queue->mark_failed(
				(int) $queue_item->id,
				'Failed to write output file',
				$result->http_status,
			);
		}
	}

	/**
	 * Finalize an export: retry failed, update status.
	 */
	public function finalize( ExportJob $job ): void {
		$max_retries = (int) $this->settings->get( 'max_retries', 3 );
		$this->crawl_queue->retry_failed( $job->export_id, $max_retries );

		$counts = $this->crawl_queue->get_counts( $job->export_id );

		$status = $counts['failed'] > 0 && $counts['completed'] === 0
			? 'failed'
			: 'completed';

		$this->progress->finish( $job->export_id, $status );
		$this->update_export_log( $job->export_id, $status, $counts );

		$this->logger->info( 'Export finalized', [
			'export_id' => $job->export_id,
			'status'    => $status,
			'completed' => $counts['completed'],
			'failed'    => $counts['failed'],
		] );
	}

	/**
	 * Cancel a running export.
	 */
	public function cancel( string $export_id ): void {
		$this->progress->cancel( $export_id );
		$this->scheduler->unschedule_all();
	}

	/**
	 * Get the current export job from progress data.
	 */
	public function get_current_job(): ?ExportJob {
		$progress = $this->progress->get();
		if ( ! $progress || ! isset( $progress['export_id'] ) ) {
			return null;
		}

		$settings = $this->settings->get_all();

		return new ExportJob(
			export_id: $progress['export_id'],
			output_dir: $settings['output_dir'],
			url_mode: $settings['url_mode'],
			base_url: $settings['base_url'],
			settings_snapshot: $settings,
			started_at: $progress['started_at'] ?? null,
		);
	}

	private function save_export_log( string $export_id, string $output_dir, string $url_mode, string $base_url, int $total, array $settings ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'sewp_export_log',
			[
				'export_id'         => $export_id,
				'status'            => 'running',
				'output_dir'        => $output_dir,
				'base_url'          => $base_url,
				'url_mode'          => $url_mode,
				'total_urls'        => $total,
				'started_at'        => current_time( 'mysql' ),
				'settings_snapshot' => wp_json_encode( $settings ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ],
		);
	}

	private function update_export_log( string $export_id, string $status, array $counts ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'sewp_export_log',
			[
				'status'         => $status,
				'total_urls'     => $counts['total'],
				'completed_urls' => $counts['completed'],
				'failed_urls'    => $counts['failed'],
				'completed_at'   => current_time( 'mysql' ),
			],
			[ 'export_id' => $export_id ],
			[ '%s', '%d', '%d', '%d', '%s' ],
			[ '%s' ],
		);
	}
}
