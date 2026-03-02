<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Admin\Controllers;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Admin\Controllers\SettingsController;
use StaticExportWP\Core\Settings;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class SettingsControllerTest extends TestCase {

	use WpStubHelpers;

	protected function setUp(): void {
		$this->reset_wp_state();
	}

	public function test_get_returns_all_settings(): void {
		$this->set_option( 'sewp_settings', [
			'url_mode'   => 'relative',
			'batch_size' => 10,
		] );

		$settings   = new Settings();
		$controller = new SettingsController( $settings );
		$response   = $controller->get();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'relative', $response->get_data()['url_mode'] );
	}

	public function test_update_saves_and_returns(): void {
		$this->set_option( 'sewp_settings', [
			'url_mode'   => 'relative',
			'batch_size' => 10,
		] );

		$settings   = new Settings();
		$controller = new SettingsController( $settings );

		$request = new \WP_REST_Request();
		$request->set_json_params( [ 'url_mode' => 'absolute', 'batch_size' => 20 ] );

		$response = $controller->update( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertSame( 'absolute', $response->get_data()['settings']['url_mode'] );
	}
}
