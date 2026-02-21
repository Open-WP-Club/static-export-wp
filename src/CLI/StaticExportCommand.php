<?php

declare(strict_types=1);

namespace StaticExportWP\CLI;

use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\UrlDiscovery;
use StaticExportWP\Export\ExportManager;

/**
 * Manage static site exports.
 */
final class StaticExportCommand {

	public function __construct(
		private readonly ExportManager $export_manager,
		private readonly Settings $settings,
		private readonly UrlDiscovery $url_discovery,
		private readonly ProgressTracker $progress,
	) {}

	/**
	 * Generate a static export of your WordPress site.
	 *
	 * ## OPTIONS
	 *
	 * [--output-dir=<path>]
	 * : Output directory. Defaults to configured setting.
	 *
	 * [--base-url=<url>]
	 * : Base URL for absolute mode.
	 *
	 * [--url-mode=<mode>]
	 * : URL mode: relative or absolute.
	 *
	 * [--batch-size=<n>]
	 * : Number of URLs per batch.
	 *
	 * [--synchronous]
	 * : Run the export synchronously with a progress bar.
	 *
	 * ## EXAMPLES
	 *
	 *     wp static-export generate --synchronous
	 *     wp static-export generate --output-dir=/tmp/export --url-mode=absolute --base-url=https://example.com
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function generate( array $args, array $assoc_args ): void {
		if ( $this->progress->is_running() ) {
			\WP_CLI::error( __( 'An export is already running. Cancel it first with: wp static-export cancel', 'static-export-wp' ) );
		}

		$overrides = [];

		if ( isset( $assoc_args['output-dir'] ) ) {
			$overrides['output_dir'] = $assoc_args['output-dir'];
		}
		if ( isset( $assoc_args['base-url'] ) ) {
			$overrides['base_url'] = $assoc_args['base-url'];
		}
		if ( isset( $assoc_args['url-mode'] ) ) {
			$overrides['url_mode'] = $assoc_args['url-mode'];
		}
		if ( isset( $assoc_args['batch-size'] ) ) {
			$overrides['batch_size'] = (int) $assoc_args['batch-size'];
		}

		$synchronous = \WP_CLI\Utils\get_flag_value( $assoc_args, 'synchronous', false );

		if ( $synchronous ) {
			\WP_CLI::log( __( 'Discovering URLs...', 'static-export-wp' ) );

			$progress_bar = null;

			$job = $this->export_manager->run_sync(
				$overrides,
				function ( int $completed, int $total, string $current_url ) use ( &$progress_bar ) {
					if ( null === $progress_bar ) {
						$progress_bar = \WP_CLI\Utils\make_progress_bar(
							__( 'Exporting pages', 'static-export-wp' ),
							$total,
						);
					}
					$progress_bar->tick();
				},
			);

			if ( $progress_bar ) {
				$progress_bar->finish();
			}

			$progress = $this->progress->get();
			$status   = $progress['status'] ?? 'unknown';

			if ( 'completed' === $status ) {
				$settings = $this->settings->get_all();
				$merged   = wp_parse_args( $overrides, $settings );
				\WP_CLI::success( sprintf(
					/* translators: %1$d: completed URLs, %2$s: output directory */
					__( 'Export completed: %1$d pages exported to %2$s', 'static-export-wp' ),
					$progress['completed'] ?? 0,
					$merged['output_dir'],
				) );
			} elseif ( 'cancelled' === $status ) {
				\WP_CLI::warning( __( 'Export was cancelled.', 'static-export-wp' ) );
			} else {
				\WP_CLI::warning( sprintf(
					/* translators: %1$d: completed, %2$d: failed */
					__( 'Export finished with issues: %1$d completed, %2$d failed.', 'static-export-wp' ),
					$progress['completed'] ?? 0,
					$progress['failed'] ?? 0,
				) );
			}
		} else {
			$job = $this->export_manager->start_background( $overrides );
			\WP_CLI::success( sprintf(
				/* translators: %s: export ID */
				__( 'Background export started. Export ID: %s', 'static-export-wp' ),
				$job->export_id,
			) );
			\WP_CLI::log( __( 'Check progress with: wp static-export status', 'static-export-wp' ) );
		}
	}

	/**
	 * Show the status of the current or last export.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function status( array $args, array $assoc_args ): void {
		$progress = $this->progress->get();

		if ( ! $progress ) {
			\WP_CLI::log( __( 'No export data available.', 'static-export-wp' ) );
			return;
		}

		$format = $assoc_args['format'] ?? 'table';

		if ( 'json' === $format ) {
			\WP_CLI::log( wp_json_encode( $progress, JSON_PRETTY_PRINT ) );
			return;
		}

		$data = [
			[
				'Field' => __( 'Export ID', 'static-export-wp' ),
				'Value' => $progress['export_id'] ?? '-',
			],
			[
				'Field' => __( 'Status', 'static-export-wp' ),
				'Value' => $progress['status'] ?? '-',
			],
			[
				'Field' => __( 'Total URLs', 'static-export-wp' ),
				'Value' => $progress['total'] ?? 0,
			],
			[
				'Field' => __( 'Completed', 'static-export-wp' ),
				'Value' => $progress['completed'] ?? 0,
			],
			[
				'Field' => __( 'Failed', 'static-export-wp' ),
				'Value' => $progress['failed'] ?? 0,
			],
			[
				'Field' => __( 'Current URL', 'static-export-wp' ),
				'Value' => $progress['current_url'] ?? '-',
			],
			[
				'Field' => __( 'Started At', 'static-export-wp' ),
				'Value' => $progress['started_at'] ?? '-',
			],
		];

		\WP_CLI\Utils\format_items( $format, $data, [ 'Field', 'Value' ] );
	}

	/**
	 * Cancel a running export.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function cancel( array $args, array $assoc_args ): void {
		$progress = $this->progress->get();

		if ( ! $progress || 'running' !== ( $progress['status'] ?? '' ) ) {
			\WP_CLI::warning( __( 'No running export to cancel.', 'static-export-wp' ) );
			return;
		}

		$this->export_manager->cancel( $progress['export_id'] );
		\WP_CLI::success( __( 'Export cancelled.', 'static-export-wp' ) );
	}

	/**
	 * Delete the static export output directory.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function clean( array $args, array $assoc_args ): void {
		$output_dir = $this->settings->get( 'output_dir' );

		if ( ! is_dir( $output_dir ) ) {
			\WP_CLI::log( __( 'Output directory does not exist. Nothing to clean.', 'static-export-wp' ) );
			return;
		}

		\WP_CLI::confirm(
			sprintf(
				/* translators: %s: directory path */
				__( 'This will delete %s and all its contents. Continue?', 'static-export-wp' ),
				$output_dir,
			),
			$assoc_args,
		);

		$file_writer = new \StaticExportWP\Export\FileWriter( new \StaticExportWP\Utility\PathHelper() );
		if ( $file_writer->clean_output( $output_dir ) ) {
			\WP_CLI::success( __( 'Output directory cleaned.', 'static-export-wp' ) );
		} else {
			\WP_CLI::error( __( 'Failed to clean output directory.', 'static-export-wp' ) );
		}
	}

	/**
	 * List all discoverable URLs.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - count
	 * ---
	 *
	 * @subcommand list-urls
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function list_urls( array $args, array $assoc_args ): void {
		$urls   = $this->url_discovery->discover();
		$format = $assoc_args['format'] ?? 'table';

		if ( 'count' === $format ) {
			\WP_CLI::log( (string) count( $urls ) );
			return;
		}

		$items = array_map( fn( string $url ) => [ 'url' => $url ], $urls );
		\WP_CLI\Utils\format_items( $format, $items, [ 'url' ] );
	}
}
