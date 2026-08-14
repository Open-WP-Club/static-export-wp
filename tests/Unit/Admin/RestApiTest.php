<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StaticExportWP\Admin\RestApi;
use StaticExportWP\Background\ActionSchedulerBridge;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Crawler\UrlDiscovery;
use StaticExportWP\Tests\Helpers\ReflectionHelper;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

/**
 * Tests for RestApi route registration and permission wiring.
 */
final class RestApiTest extends TestCase {

	use WpStubHelpers;

	private RestApi $rest_api;

	protected function setUp(): void {
		$this->reset_wp_state();
		$GLOBALS['wpdb'] = new \wpdb();

		$settings       = new Settings();
		$progress       = new ProgressTracker();
		$crawl_queue    = new CrawlQueue();
		$scheduler      = new ActionSchedulerBridge();
		$url_discovery  = new UrlDiscovery( $settings );
		$export_manager = ReflectionHelper::buildRealExportManager(
			settings: $settings,
			progress: $progress,
			crawl_queue: $crawl_queue,
			scheduler: $scheduler,
		);

		$this->rest_api = new RestApi( $export_manager, $settings, $url_discovery, $progress, $crawl_queue );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
	}

	private function registered_routes(): array {
		global $_wp_rest_routes;
		return $_wp_rest_routes;
	}

	private function find_route( string $route ): ?array {
		foreach ( $this->registered_routes() as $registration ) {
			if ( $registration['route'] === $route ) {
				return $registration;
			}
		}
		return null;
	}

	public function test_register_routes_uses_sewp_v1_namespace(): void {
		$this->rest_api->register_routes();

		$routes = $this->registered_routes();
		$this->assertNotEmpty( $routes );
		foreach ( $routes as $registration ) {
			$this->assertSame( 'sewp/v1', $registration['namespace'] );
		}
	}

	public static function route_provider(): array {
		return [
			'settings'       => [ '/settings' ],
			'export start'   => [ '/export/start' ],
			'export cancel'  => [ '/export/cancel' ],
			'export status'  => [ '/export/status' ],
			'export clean'   => [ '/export/clean' ],
			'discover urls'  => [ '/export/discover-urls' ],
			'post types'     => [ '/post-types' ],
			'export log'     => [ '/export/log' ],
			'size report'    => [ '/export/size-report' ],
			'webhook test'   => [ '/webhook/test' ],
			'broken links'   => [ '/export/broken-links' ],
		];
	}

	#[DataProvider( 'route_provider' )]
	public function test_registers_expected_route( string $route ): void {
		$this->rest_api->register_routes();

		$this->assertNotNull( $this->find_route( $route ), "Expected route {$route} to be registered" );
	}

	public function test_all_routes_use_check_permissions_callback(): void {
		$this->rest_api->register_routes();

		foreach ( $this->registered_routes() as $registration ) {
			$args = $registration['args'];
			// Some routes register a single args array, others a list of method configs.
			$configs = array_is_list( $args ) ? $args : [ $args ];

			foreach ( $configs as $config ) {
				$this->assertSame(
					[ $this->rest_api, 'check_permissions' ],
					$config['permission_callback'],
					"Route {$registration['route']} must gate access via check_permissions",
				);
			}
		}
	}

	public function test_settings_route_registers_get_and_post(): void {
		$this->rest_api->register_routes();

		$registration = $this->find_route( '/settings' );
		$this->assertNotNull( $registration );
		$methods = array_map( fn( $config ) => $config['methods'], $registration['args'] );

		$this->assertContains( \WP_REST_Server::READABLE, $methods );
		$this->assertContains( \WP_REST_Server::CREATABLE, $methods );
	}

	public function test_broken_links_route_requires_export_id(): void {
		$this->rest_api->register_routes();

		$registration = $this->find_route( '/export/broken-links' );
		$this->assertNotNull( $registration );
		$this->assertTrue( $registration['args']['args']['export_id']['required'] );
	}

	public function test_check_permissions_true_for_manage_options(): void {
		$this->set_user_can( 'manage_options', true );

		$this->assertTrue( $this->rest_api->check_permissions() );
	}

	public function test_check_permissions_false_without_manage_options(): void {
		$this->set_user_can( 'manage_options', false );

		$this->assertFalse( $this->rest_api->check_permissions() );
	}
}
