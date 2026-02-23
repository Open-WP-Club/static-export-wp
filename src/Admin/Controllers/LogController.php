<?php

declare(strict_types=1);

namespace StaticExportWP\Admin\Controllers;

final class LogController {

	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$per_page = (int) ( $request->get_param( 'per_page' ) ?? 20 );
		$page     = (int) ( $request->get_param( 'page' ) ?? 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$table = $wpdb->prefix . 'sewp_export_log';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$logs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset,
		) );

		return new \WP_REST_Response( [
			'logs'       => $logs ?: [],
			'total'      => $total,
			'page'       => $page,
			'per_page'   => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		] );
	}

	public function size_report( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$limit = (int) ( $request->get_param( 'limit' ) ?? 10 );
		$limit = max( 1, min( $limit, 50 ) );
		$table = $wpdb->prefix . 'sewp_export_log';

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT export_id, started_at, completed_at, size_report
			 FROM {$table}
			 WHERE size_report IS NOT NULL
			 ORDER BY id DESC
			 LIMIT %d",
			$limit,
		) );

		$exports = [];
		foreach ( ( $rows ?: [] ) as $row ) {
			$exports[] = [
				'export_id'    => $row->export_id,
				'started_at'   => $row->started_at,
				'completed_at' => $row->completed_at,
				'size_report'  => json_decode( $row->size_report, true ) ?: [],
			];
		}

		return new \WP_REST_Response( $exports );
	}
}
