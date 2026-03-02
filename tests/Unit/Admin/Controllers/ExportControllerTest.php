<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Admin\Controllers;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Admin\Controllers\ExportController;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Core\Settings;
use StaticExportWP\Tests\Helpers\ReflectionHelper;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

/**
 * Tests for ExportController.
 *
 * Uses real instances of all final classes, controlling behaviour through
 * the WP stub layer.
 */
final class ExportControllerTest extends TestCase {

	use WpStubHelpers;

	private ProgressTracker $progress;

	protected function setUp(): void {
		$this->reset_wp_state();
		$GLOBALS['wpdb'] = new \wpdb();
		$this->progress  = new ProgressTracker();
	}

	public function test_start_returns_409_when_running(): void {
		// Mark an export as already running.
		$this->progress->start( 'existing-export', 10 );

		$settings       = new Settings();
		$export_manager = ReflectionHelper::buildRealExportManager(
			settings: $settings,
			progress: $this->progress,
		);

		$controller = new ExportController( $export_manager, $this->progress );

		$response = $controller->start();

		$this->assertSame( 409, $response->get_status() );
		$this->assertArrayHasKey( 'error', $response->get_data() );
	}

	public function test_start_returns_success_with_export_id(): void {
		$settings       = new Settings();
		$export_manager = ReflectionHelper::buildRealExportManager(
			settings: $settings,
			progress: $this->progress,
		);

		$controller = new ExportController( $export_manager, $this->progress );

		$response = $controller->start();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		// export_id should be a UUID string.
		$this->assertNotEmpty( $response->get_data()['export_id'] );
	}

	public function test_cancel_returns_404_when_no_running_export(): void {
		$settings       = new Settings();
		$export_manager = ReflectionHelper::buildRealExportManager(
			settings: $settings,
			progress: $this->progress,
		);

		$controller = new ExportController( $export_manager, $this->progress );

		$response = $controller->cancel();

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_status_returns_idle_when_no_progress(): void {
		$settings       = new Settings();
		$export_manager = ReflectionHelper::buildRealExportManager(
			settings: $settings,
			progress: $this->progress,
		);

		$controller = new ExportController( $export_manager, $this->progress );

		$response = $controller->status();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'idle', $response->get_data()['status'] );
	}
}
