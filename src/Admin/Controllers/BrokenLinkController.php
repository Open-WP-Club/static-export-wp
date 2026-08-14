<?php
/**
 * REST controller for listing broken links found during an export.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Admin\Controllers;

use StaticExportWP\Crawler\CrawlQueue;

/**
 * Handles the `/export/broken-links` REST route.
 */
final class BrokenLinkController {

	/**
	 * Create the controller.
	 *
	 * @param CrawlQueue $crawl_queue Crawl queue used to look up broken links.
	 */
	public function __construct(
		private readonly CrawlQueue $crawl_queue,
	) {}

	/**
	 * List the broken links recorded for an export.
	 *
	 * @param \WP_REST_Request $request The REST request, expects an `export_id` param.
	 * @return \WP_REST_Response Response containing the broken links and their total count.
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$export_id = $request->get_param( 'export_id' );

		if ( empty( $export_id ) ) {
			return new \WP_REST_Response( array( 'links' => array() ) );
		}

		$links = $this->crawl_queue->get_broken_links( $export_id );

		return new \WP_REST_Response(
			array(
				'links' => $links,
				'total' => count( $links ),
			)
		);
	}
}
