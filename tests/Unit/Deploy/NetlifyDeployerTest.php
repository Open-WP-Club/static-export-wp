<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Deploy\NetlifyDeployer;
use StaticExportWP\Export\ExportJob;
use StaticExportWP\Utility\Logger;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class NetlifyDeployerTest extends TestCase {

	use WpStubHelpers;

	protected function setUp(): void {
		$this->reset_wp_state();
	}

	public function test_fails_when_no_token(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_netlify_token'   => '',
			'deploy_netlify_site_id' => 'site-123',
		] );

		$settings = new Settings();
		$logger   = new Logger();
		$deployer = new NetlifyDeployer( $settings, $logger );

		$job    = new ExportJob( 'exp-1', '/tmp/export', 'relative', '', [] );
		$result = $deployer->deploy( $job );

		$this->assertFalse( $result->success );
		$this->assertStringContainsString( 'No Netlify access token', $result->message );
	}

	public function test_fails_when_no_site_id(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_netlify_token'   => 'token-123',
			'deploy_netlify_site_id' => '',
		] );

		$settings = new Settings();
		$logger   = new Logger();
		$deployer = new NetlifyDeployer( $settings, $logger );

		$job    = new ExportJob( 'exp-1', '/tmp/export', 'relative', '', [] );
		$result = $deployer->deploy( $job );

		$this->assertFalse( $result->success );
		$this->assertStringContainsString( 'No Netlify site ID', $result->message );
	}

	public function test_fails_when_output_dir_missing(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_netlify_token'   => 'token-123',
			'deploy_netlify_site_id' => 'site-123',
		] );

		$settings = new Settings();
		$logger   = new Logger();
		$deployer = new NetlifyDeployer( $settings, $logger );

		$job    = new ExportJob( 'exp-1', '/nonexistent/path/12345', 'relative', '', [] );
		$result = $deployer->deploy( $job );

		$this->assertFalse( $result->success );
		$this->assertStringContainsString( 'Output directory', $result->message );
	}

	public function test_label_returns_netlify_api(): void {
		$this->set_option( 'sewp_settings', [] );

		$settings = new Settings();
		$logger   = new Logger();
		$deployer = new NetlifyDeployer( $settings, $logger );

		$this->assertSame( 'Netlify API', $deployer->label() );
	}
}
