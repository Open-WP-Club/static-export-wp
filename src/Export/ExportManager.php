<?php

declare(strict_types=1);

namespace StaticExportWP\Export;

use StaticExportWP\Background\ActionSchedulerBridge;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\BatchFetcher;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Crawler\Fetcher;
use StaticExportWP\Crawler\FetchResult;
use StaticExportWP\Crawler\UrlDiscovery;
use StaticExportWP\Utility\Logger;

final class ExportManager {

	public function __construct(
		private readonly Settings $settings,
		private readonly UrlDiscovery $url_discovery,
		private readonly Fetcher $fetcher,
		private readonly CrawlQueue $crawl_queue,
		private readonly HtmlProcessor $html_processor,
		private readonly AssetCollector $asset_collector,
		private readonly FileWriter $file_writer,
		private readonly ProgressTracker $progress,
		private readonly ActionSchedulerBridge $scheduler,
		private readonly Logger $logger,
		private readonly ?ContentHashStore $content_hash_store = null,
		private readonly ?BatchFetcher $batch_fetcher = null,
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

			// Use parallel fetching when available.
			$this->process_batch( $job, $batch );

			// Update progress once per batch instead of per URL.
			$counts    = $this->crawl_queue->get_counts( $job->export_id );
			$last_url  = end( $batch ) ? end( $batch )->url : '';
			$this->progress->update_counts(
				$job->export_id,
				$counts['completed'],
				$counts['failed'],
				$last_url,
			);

			if ( $on_progress ) {
				$on_progress( $counts['completed'], $counts['total'], $last_url );
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

		// Incremental export: skip unchanged content.
		$incremental = (bool) $this->settings->get( 'incremental_export', false );
		if ( $incremental && null !== $this->content_hash_store ) {
			$new_hash    = ContentHashStore::hash_content( $result->body );
			$stored_hash = $this->content_hash_store->get_hash( $queue_item->url );

			if ( null !== $stored_hash && $stored_hash === $new_hash ) {
				$this->logger->info( 'Skipping unchanged URL', [ 'url' => $queue_item->url ] );
				$this->crawl_queue->mark_completed(
					(int) $queue_item->id,
					$result->http_status,
					$result->content_type,
					'',
				);
				return;
			}
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

			// Enqueue newly discovered URLs (filtered by pagination depth).
			$discovered = $this->filter_pagination( $processed['discovered_urls'] );
			if ( ! empty( $discovered ) ) {
				$this->crawl_queue->enqueue( $job->export_id, $discovered );
				// Update total count.
				$counts = $this->crawl_queue->get_counts( $job->export_id );
				$this->progress->update_total( $job->export_id, $counts['total'] );
			}

			// Copy assets and recursively crawl CSS for nested references.
			$site_url = untrailingslashit( home_url() );
			foreach ( $processed['assets'] as $asset_url ) {
				$copied = $this->file_writer->copy_asset( $job->output_dir, $asset_url, $site_url );

				if ( false !== $copied && $this->is_css_url( $asset_url ) ) {
					$this->crawl_css_assets( $job->output_dir, $asset_url, $site_url );
				}
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

			// Store content hash for incremental export.
			if ( null !== $this->content_hash_store ) {
				$content_hash = ContentHashStore::hash_content( $result->body );
				$this->content_hash_store->store_hash(
					$queue_item->url,
					$content_hash,
					$output_path,
					$job->export_id,
				);
			}
		} else {
			$this->crawl_queue->mark_failed(
				(int) $queue_item->id,
				'Failed to write output file',
				$result->http_status,
			);
		}
	}

	/**
	 * Process a batch of queue items with parallel HTTP fetching.
	 *
	 * @param ExportJob $job        The export job.
	 * @param object[]  $queue_items Queue rows from get_next_batch().
	 */
	public function process_batch( ExportJob $job, array $queue_items ): void {
		if ( null === $this->batch_fetcher || empty( $queue_items ) ) {
			// Fallback to sequential if no batch fetcher.
			foreach ( $queue_items as $queue_item ) {
				$this->process_url( $job, $queue_item );
			}
			return;
		}

		$urls = array_map( fn( $item ) => $item->url, $queue_items );
		$results = $this->batch_fetcher->fetch_batch( $urls );

		// Map queue items by URL for quick lookup.
		$items_by_url = [];
		foreach ( $queue_items as $item ) {
			$items_by_url[ $item->url ] = $item;
		}

		foreach ( $results as $url => $result ) {
			$queue_item = $items_by_url[ $url ] ?? null;
			if ( null === $queue_item ) {
				continue;
			}

			$this->process_fetched_result( $job, $queue_item, $result );
		}
	}

	/**
	 * Process an already-fetched result for a queue item.
	 */
	private function process_fetched_result( ExportJob $job, object $queue_item, FetchResult $result ): void {
		$this->logger->info( 'Processing URL', [ 'url' => $queue_item->url ] );

		if ( ! $result->is_success() ) {
			$this->crawl_queue->mark_failed(
				(int) $queue_item->id,
				$result->error ?? "HTTP {$result->http_status}",
				$result->http_status,
			);
			return;
		}

		// Incremental export: skip unchanged content.
		$incremental = (bool) $this->settings->get( 'incremental_export', false );
		if ( $incremental && null !== $this->content_hash_store ) {
			$new_hash    = ContentHashStore::hash_content( $result->body );
			$stored_hash = $this->content_hash_store->get_hash( $queue_item->url );

			if ( null !== $stored_hash && $stored_hash === $new_hash ) {
				$this->logger->info( 'Skipping unchanged URL', [ 'url' => $queue_item->url ] );
				$this->crawl_queue->mark_completed(
					(int) $queue_item->id,
					$result->http_status,
					$result->content_type,
					'',
				);
				return;
			}
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

			// Enqueue newly discovered URLs (filtered by pagination depth).
			$discovered = $this->filter_pagination( $processed['discovered_urls'] );
			if ( ! empty( $discovered ) ) {
				$this->crawl_queue->enqueue( $job->export_id, $discovered );
				$counts = $this->crawl_queue->get_counts( $job->export_id );
				$this->progress->update_total( $job->export_id, $counts['total'] );
			}

			// Copy assets.
			$site_url = untrailingslashit( home_url() );
			foreach ( $processed['assets'] as $asset_url ) {
				$copied = $this->file_writer->copy_asset( $job->output_dir, $asset_url, $site_url );

				if ( false !== $copied && $this->is_css_url( $asset_url ) ) {
					$this->crawl_css_assets( $job->output_dir, $asset_url, $site_url );
				}
			}
		} else {
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

			if ( null !== $this->content_hash_store ) {
				$content_hash = ContentHashStore::hash_content( $result->body );
				$this->content_hash_store->store_hash(
					$queue_item->url,
					$content_hash,
					$output_path,
					$job->export_id,
				);
			}
		} else {
			$this->crawl_queue->mark_failed(
				(int) $queue_item->id,
				'Failed to write output file',
				$result->http_status,
			);
		}
	}

	/**
	 * Finalize an export: retry failed, generate extras, update status.
	 */
	public function finalize( ExportJob $job ): void {
		$max_retries = (int) $this->settings->get( 'max_retries', 3 );
		$this->crawl_queue->retry_failed( $job->export_id, $max_retries );

		// Generate extra files.
		$this->generate_404_page( $job );
		$this->generate_robots_txt( $job );
		$this->generate_sitemap( $job );

		/**
		 * Fires after sitemap generation, before progress finalization.
		 * Used for post-export processing like search indexing.
		 *
		 * @param ExportJob $job The export job.
		 */
		do_action( 'sewp_post_export_process', $job );

		$counts = $this->crawl_queue->get_counts( $job->export_id );

		$status = $counts['failed'] > 0 && $counts['completed'] === 0
			? 'failed'
			: 'completed';

		$this->progress->finish( $job->export_id, $status );
		$this->update_export_log( $job->export_id, $status, $counts );

		// Run post-export deploy if configured and export has completed pages.
		if ( 'completed' === $status ) {
			$this->run_deploy( $job );
		}

		$this->logger->info( 'Export finalized', [
			'export_id' => $job->export_id,
			'status'    => $status,
			'completed' => $counts['completed'],
			'failed'    => $counts['failed'],
		] );

		// Compute duration for notifications.
		$started_at = $job->started_at ? strtotime( $job->started_at ) : 0;
		$duration   = $started_at > 0 ? human_time_diff( $started_at, time() ) : __( 'unknown', 'static-export-wp' );

		/**
		 * Fires after export is fully finalized (completed or failed).
		 *
		 * @param string $export_id The export UUID.
		 * @param string $status    'completed' or 'failed'.
		 * @param array  $counts    {total, completed, failed}.
		 * @param string $duration  Human-readable duration.
		 */
		do_action( 'sewp_export_finalized', $job->export_id, $status, $counts, $duration );
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

	/**
	 * Fetch the WP 404 page and save as 404.html.
	 */
	private function generate_404_page( ExportJob $job ): void {
		$url_404 = home_url( '/sewp-nonexistent-page-' . wp_rand() . '/' );
		$result  = $this->fetcher->fetch( $url_404 );

		if ( '' !== $result->body && $result->is_html() ) {
			$processed = $this->html_processor->process(
				$result->body,
				home_url( '/404.html' ),
				$job->url_mode,
				$job->base_url,
			);

			$this->file_writer->write_html( $job->output_dir, '/404.html', $processed['html'] );
			$this->logger->info( '404 page generated' );
		}
	}

	/**
	 * Copy robots.txt from the live site.
	 */
	private function generate_robots_txt( ExportJob $job ): void {
		$result = $this->fetcher->fetch( home_url( '/robots.txt' ) );

		if ( $result->is_success() && '' !== $result->body ) {
			$body = $result->body;

			// Rewrite sitemap URL if present.
			if ( 'absolute' === $job->url_mode && '' !== $job->base_url ) {
				$body = str_replace( home_url(), untrailingslashit( $job->base_url ), $body );
			}

			$this->file_writer->write_html( $job->output_dir, '/robots.txt', $body );
			$this->logger->info( 'robots.txt generated' );
		}
	}

	/**
	 * Generate a sitemap.xml from all completed URLs in the queue.
	 */
	private function generate_sitemap( ExportJob $job ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'sewp_crawl_queue';
		$urls  = $wpdb->get_col( $wpdb->prepare(
			"SELECT url FROM {$table} WHERE export_id = %s AND status = 'completed' AND content_type LIKE %s ORDER BY id ASC",
			$job->export_id,
			'%text/html%',
		) );

		if ( empty( $urls ) ) {
			return;
		}

		$site_url = untrailingslashit( home_url() );
		$base     = ( 'absolute' === $job->url_mode && '' !== $job->base_url )
			? untrailingslashit( $job->base_url )
			: $site_url;

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $urls as $url ) {
			$loc  = str_replace( $site_url, $base, $url );
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url( $loc ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . gmdate( 'Y-m-d' ) . "</lastmod>\n";
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>' . "\n";

		$this->file_writer->write_html( $job->output_dir, '/sitemap.xml', $xml );
		$this->logger->info( 'sitemap.xml generated', [ 'urls' => count( $urls ) ] );
	}

	/**
	 * Crawl a CSS file for nested asset references (@import, url()).
	 */
	private function crawl_css_assets( string $output_dir, string $css_url, string $site_url ): void {
		$result = $this->fetcher->fetch( $css_url );

		if ( ! $result->is_success() || '' === $result->body ) {
			return;
		}

		$nested_assets = $this->asset_collector->collect_from_css( $result->body, $css_url, $site_url );

		foreach ( $nested_assets as $asset_url ) {
			$this->file_writer->copy_asset( $output_dir, $asset_url, $site_url );
		}
	}

	/**
	 * Filter out pagination URLs beyond the configured depth.
	 *
	 * @param string[] $urls Discovered URLs.
	 * @return string[] Filtered URLs.
	 */
	private function filter_pagination( array $urls ): array {
		$max_depth = (int) $this->settings->get( 'pagination_depth', 0 );

		// 0 = unlimited — no filtering.
		if ( 0 === $max_depth ) {
			return $urls;
		}

		return array_values( array_filter( $urls, function ( string $url ) use ( $max_depth ): bool {
			// Match WordPress pagination patterns: /page/N/ or /comment-page-N/
			if ( preg_match( '#/page/(\d+)/?#', $url, $m ) ) {
				return (int) $m[1] <= $max_depth;
			}
			if ( preg_match( '#/comment-page-(\d+)/?#', $url, $m ) ) {
				return (int) $m[1] <= $max_depth;
			}
			// ?paged=N query parameter.
			$query = wp_parse_url( $url, PHP_URL_QUERY ) ?? '';
			if ( preg_match( '/(?:^|&)paged=(\d+)/', $query, $m ) ) {
				return (int) $m[1] <= $max_depth;
			}
			return true;
		} ) );
	}

	private function is_css_url( string $url ): bool {
		$path = wp_parse_url( $url, PHP_URL_PATH ) ?? '';
		return str_ends_with( strtolower( $path ), '.css' );
	}

	/**
	 * Run post-export deploy command if configured.
	 *
	 * Security note: The deploy command is set by a user with manage_options
	 * capability (the same trust level as installing plugins). The output_dir
	 * is escaped via escapeshellarg.
	 */
	private function run_deploy( ExportJob $job ): void {
		$method = $this->settings->get( 'deploy_method', 'none' );

		if ( 'none' === $method ) {
			return;
		}

		if ( 'command' === $method ) {
			$command = $this->settings->get( 'deploy_command', '' );

			if ( '' === $command ) {
				return;
			}

			// Replace placeholder with escaped output directory path.
			$command = str_replace( '{{output_dir}}', escapeshellarg( $job->output_dir ), $command );

			$this->logger->info( 'Running deploy command', [ 'command' => $command ] );

			// Command is admin-configured (manage_options), same trust level as plugin installation.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			$output    = [];
			$exit_code = 0;
			exec( $command . ' 2>&1', $output, $exit_code );

			if ( 0 === $exit_code ) {
				$this->logger->info( 'Deploy completed successfully' );
			} else {
				$this->logger->error( 'Deploy failed', [
					'exit_code' => $exit_code,
					'output'    => implode( "\n", $output ),
				] );
			}
		}

		/**
		 * Fires after a successful export, for custom deploy integrations.
		 *
		 * @param ExportJob $job The completed export job.
		 */
		do_action( 'sewp_after_export', $job );
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
