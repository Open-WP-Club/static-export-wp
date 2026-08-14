<?php
/**
 * REST controller for the export log and size report.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Admin\Controllers;

/**
 * Handles the `/export/log` and `/export/size-report` REST routes.
 */
final class LogController {

	/**
	 * List paginated entries from the export log.
	 *
	 * @param \WP_REST_Request $request The REST request, accepts `page` and `per_page` params.
	 * @return \WP_REST_Response Response with the log entries and pagination metadata.
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$per_page = (int) ( $request->get_param( 'per_page' ) ?? 20 );
		$page     = (int) ( $request->get_param( 'page' ) ?? 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$table = $wpdb->prefix . 'sewp_export_log';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset,
			)
		);

		return new \WP_REST_Response(
			array(
				'logs'        => $logs ?: array(),
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * List the most recent exports' size reports.
	 *
	 * @param \WP_REST_Request $request The REST request, accepts a `limit` param (max 50).
	 * @return \WP_REST_Response Response with an array of export size reports.
	 */
	public function size_report( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$limit = (int) ( $request->get_param( 'limit' ) ?? 10 );
		$limit = max( 1, min( $limit, 50 ) );
		$table = $wpdb->prefix . 'sewp_export_log';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT export_id, started_at, completed_at, size_report
			 FROM {$table}
			 WHERE size_report IS NOT NULL
			 ORDER BY id DESC
			 LIMIT %d",
				$limit,
			)
		);

		$exports = array();
		foreach ( ( $rows ?: array() ) as $row ) {
			$exports[] = array(
				'export_id'    => $row->export_id,
				'started_at'   => $row->started_at,
				'completed_at' => $row->completed_at,
				'size_report'  => json_decode( $row->size_report, true ) ?: array(),
			);
		}

		return new \WP_REST_Response( $exports );
	}
}
