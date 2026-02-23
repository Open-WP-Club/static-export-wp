<?php

declare(strict_types=1);

namespace StaticExportWP\Admin\Controllers;

use StaticExportWP\Crawler\CrawlQueue;

final class BrokenLinkController {

	public function __construct(
		private readonly CrawlQueue $crawl_queue,
	) {}

	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$export_id = $request->get_param( 'export_id' );

		if ( empty( $export_id ) ) {
			return new \WP_REST_Response( [ 'links' => [] ] );
		}

		$links = $this->crawl_queue->get_broken_links( $export_id );

		return new \WP_REST_Response( [
			'links' => $links,
			'total' => count( $links ),
		] );
	}
}
