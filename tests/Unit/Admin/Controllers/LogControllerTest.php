<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Admin\Controllers;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Admin\Controllers\LogController;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class LogControllerTest extends TestCase {

	use WpStubHelpers;

	private \wpdb $wpdb;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->wpdb      = new \wpdb();
		$GLOBALS['wpdb'] = $this->wpdb;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
	}

	public function test_index_returns_paginated_logs(): void {
		$this->wpdb->_get_var_returns     = [ '2' ];
		$this->wpdb->_get_results_returns = [ [
			(object) [ 'id' => 1, 'export_id' => 'exp-1', 'status' => 'completed' ],
			(object) [ 'id' => 2, 'export_id' => 'exp-2', 'status' => 'failed' ],
		] ];

		$controller = new LogController();

		$request = new \WP_REST_Request();
		$request->set_param( 'per_page', 20 );
		$request->set_param( 'page', 1 );

		$response = $controller->index( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 2, $data['logs'] );
		$this->assertSame( 2, $data['total'] );
		$this->assertSame( 1, $data['page'] );
	}

	public function test_size_report_returns_exports(): void {
		$this->wpdb->_get_results_returns = [ [
			(object) [
				'export_id'    => 'exp-1',
				'started_at'   => '2024-01-01 00:00:00',
				'completed_at' => '2024-01-01 00:05:00',
				'size_report'  => '{"html":100,"css":200,"total":300}',
			],
		] ];

		$controller = new LogController();

		$request = new \WP_REST_Request();
		$request->set_param( 'limit', 10 );

		$response = $controller->size_report( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 'exp-1', $data[0]['export_id'] );
		$this->assertSame( 100, $data[0]['size_report']['html'] );
	}
}
