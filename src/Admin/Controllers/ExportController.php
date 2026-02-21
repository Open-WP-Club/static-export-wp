<?php

declare(strict_types=1);

namespace StaticExportWP\Admin\Controllers;

use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Export\ExportManager;
use StaticExportWP\Export\FileWriter;
use StaticExportWP\Utility\PathHelper;

final class ExportController {

	public function __construct(
		private readonly ExportManager $export_manager,
		private readonly ProgressTracker $progress,
	) {}

	public function start(): \WP_REST_Response {
		if ( $this->progress->is_running() ) {
			return new \WP_REST_Response(
				[ 'error' => __( 'An export is already running.', 'static-export-wp' ) ],
				409,
			);
		}

		$job = $this->export_manager->start_background();

		return new \WP_REST_Response( [
			'success'   => true,
			'export_id' => $job->export_id,
		] );
	}

	public function cancel(): \WP_REST_Response {
		$progress = $this->progress->get();

		if ( ! $progress || ! isset( $progress['export_id'] ) ) {
			return new \WP_REST_Response(
				[ 'error' => __( 'No running export to cancel.', 'static-export-wp' ) ],
				404,
			);
		}

		$this->export_manager->cancel( $progress['export_id'] );

		return new \WP_REST_Response( [ 'success' => true ] );
	}

	public function status(): \WP_REST_Response {
		$progress = $this->progress->get();

		if ( ! $progress ) {
			return new \WP_REST_Response( [
				'status' => 'idle',
			] );
		}

		return new \WP_REST_Response( $progress );
	}

	public function clean(): \WP_REST_Response {
		$settings   = \StaticExportWP\Core\Plugin::instance()->settings();
		$output_dir = $settings->get( 'output_dir' );

		$file_writer = new FileWriter( new PathHelper() );
		$result      = $file_writer->clean_output( $output_dir );

		return new \WP_REST_Response( [
			'success' => $result,
		] );
	}
}
