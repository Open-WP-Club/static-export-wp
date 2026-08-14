<?php
/**
 * REST controller for starting, cancelling, monitoring and cleaning exports.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Admin\Controllers;

use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Export\ExportManager;
use StaticExportWP\Export\FileWriter;
use StaticExportWP\Utility\PathHelper;

/**
 * Handles the `/export/start`, `/export/cancel`, `/export/status` and
 * `/export/clean` REST routes.
 */
final class ExportController {

	/**
	 * Create the controller.
	 *
	 * @param ExportManager   $export_manager Manages starting, cancelling and cleaning exports.
	 * @param ProgressTracker $progress       Tracks the progress of the currently running export.
	 */
	public function __construct(
		private readonly ExportManager $export_manager,
		private readonly ProgressTracker $progress,
	) {}

	/**
	 * Start a new background export, unless one is already running.
	 *
	 * @return \WP_REST_Response Response with the new export's ID, or a 409 error if one is running.
	 */
	public function start(): \WP_REST_Response {
		if ( $this->progress->is_running() ) {
			return new \WP_REST_Response(
				array( 'error' => __( 'An export is already running.', 'static-export-wp' ) ),
				409,
			);
		}

		$job = $this->export_manager->start_background();

		return new \WP_REST_Response(
			array(
				'success'   => true,
				'export_id' => $job->export_id,
			)
		);
	}

	/**
	 * Cancel the currently running export, if any.
	 *
	 * @return \WP_REST_Response Success response, or a 404 error if nothing is running.
	 */
	public function cancel(): \WP_REST_Response {
		$progress = $this->progress->get();

		if ( ! $progress || ! isset( $progress['export_id'] ) ) {
			return new \WP_REST_Response(
				array( 'error' => __( 'No running export to cancel.', 'static-export-wp' ) ),
				404,
			);
		}

		$this->export_manager->cancel( $progress['export_id'] );

		return new \WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * Get the progress of the currently running (or last known) export.
	 *
	 * @return \WP_REST_Response Response with the export progress, or `status: idle` if none is tracked.
	 */
	public function status(): \WP_REST_Response {
		$progress = $this->progress->get();

		if ( ! $progress ) {
			return new \WP_REST_Response(
				array(
					'status' => 'idle',
				)
			);
		}

		return new \WP_REST_Response( $progress );
	}

	/**
	 * Delete the current export's output directory.
	 *
	 * @return \WP_REST_Response Response indicating whether the cleanup succeeded.
	 */
	public function clean(): \WP_REST_Response {
		$settings   = \StaticExportWP\Core\Plugin::instance()->settings();
		$output_dir = $settings->get( 'output_dir' );

		$file_writer = new FileWriter( new PathHelper() );
		$result      = $file_writer->clean_output( $output_dir );

		return new \WP_REST_Response(
			array(
				'success' => $result,
			)
		);
	}
}
