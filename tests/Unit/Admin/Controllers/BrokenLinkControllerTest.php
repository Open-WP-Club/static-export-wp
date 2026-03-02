<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Admin\Controllers;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Admin\Controllers\BrokenLinkController;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class BrokenLinkControllerTest extends TestCase {

	use WpStubHelpers;

	protected function setUp(): void {
		$this->reset_wp_state();

		// Ensure wpdb stub is available for CrawlQueue.
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			$GLOBALS['wpdb'] = new \wpdb();
		}
	}

	public function test_returns_empty_when_no_export_id(): void {
		$crawl_queue = new CrawlQueue();
		$controller  = new BrokenLinkController( $crawl_queue );

		$request = new \WP_REST_Request();

		$response = $controller->index( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertEmpty( $response->get_data()['links'] );
	}

	public function test_returns_broken_links_for_valid_id(): void {
		// Configure the wpdb stub to return broken links.
		$GLOBALS['wpdb']->_get_results_returns[] = [
			(object) [
				'url'           => 'https://example.com/missing',
				'http_status'   => 404,
				'error_message' => 'Not Found',
				'referrer'      => '/',
			],
		];

		$crawl_queue = new CrawlQueue();
		$controller  = new BrokenLinkController( $crawl_queue );

		$request = new \WP_REST_Request();
		$request->set_param( 'export_id', 'exp-1' );

		$response = $controller->index( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data()['links'] );
		$this->assertSame( 1, $response->get_data()['total'] );
	}
}
