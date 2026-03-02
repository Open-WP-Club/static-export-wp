<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Deploy\CommandDeployer;
use StaticExportWP\Export\ExportJob;
use StaticExportWP\Utility\Logger;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class CommandDeployerTest extends TestCase {

	use WpStubHelpers;

	protected function setUp(): void {
		$this->reset_wp_state();
	}

	public function test_fails_when_no_command(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_command' => '',
		] );

		$settings = new Settings();
		$logger   = new Logger();
		$deployer = new CommandDeployer( $settings, $logger );

		$job    = new ExportJob( 'exp-1', '/tmp/export', 'relative', '', [] );
		$result = $deployer->deploy( $job );

		$this->assertFalse( $result->success );
		$this->assertStringContainsString( 'No deploy command', $result->message );
	}

	public function test_label_returns_shell_command(): void {
		$this->set_option( 'sewp_settings', [] );

		$settings = new Settings();
		$logger   = new Logger();
		$deployer = new CommandDeployer( $settings, $logger );

		$this->assertSame( 'Shell Command', $deployer->label() );
	}

	public function test_replaces_output_dir_placeholder(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_command' => 'echo {{output_dir}}',
		] );

		$settings = new Settings();
		$logger   = new Logger();
		$deployer = new CommandDeployer( $settings, $logger );

		$job    = new ExportJob( 'exp-1', '/tmp/export', 'relative', '', [] );
		$result = $deployer->deploy( $job );

		// The echo command should succeed with exit code 0.
		$this->assertTrue( $result->success );
	}
}
