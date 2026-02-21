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
}
