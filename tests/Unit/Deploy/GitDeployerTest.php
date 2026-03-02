<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Deploy\GitDeployer;
use StaticExportWP\Export\ExportJob;
use StaticExportWP\Utility\Logger;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class GitDeployerTest extends TestCase {

	use WpStubHelpers;

	protected function setUp(): void {
		$this->reset_wp_state();
	}

	public function test_fails_when_no_remote(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_git_remote' => '',
			'deploy_git_token'  => '',
			'deploy_git_branch' => 'main',
		] );

		$settings = new Settings();
		$logger   = new Logger();
		$deployer = new GitDeployer( $settings, $logger );

		$job    = new ExportJob( 'exp-1', '/tmp/export', 'relative', '', [] );
		$result = $deployer->deploy( $job );

		$this->assertFalse( $result->success );
		$this->assertStringContainsString( 'No Git remote', $result->message );
	}

	public function test_fails_when_not_https(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_git_remote' => 'git@github.com:user/repo.git',
			'deploy_git_token'  => '',
			'deploy_git_branch' => 'main',
		] );

		$settings = new Settings();
		$logger   = new Logger();
		$deployer = new GitDeployer( $settings, $logger );

		$job    = new ExportJob( 'exp-1', '/tmp/export', 'relative', '', [] );
		$result = $deployer->deploy( $job );

		$this->assertFalse( $result->success );
		$this->assertStringContainsString( 'HTTPS', $result->message );
	}

	public function test_fails_when_output_dir_missing(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_git_remote' => 'https://github.com/user/repo.git',
			'deploy_git_token'  => '',
			'deploy_git_branch' => 'main',
		] );

		$settings = new Settings();
		$logger   = new Logger();
		$deployer = new GitDeployer( $settings, $logger );

		$job    = new ExportJob( 'exp-1', '/nonexistent/path/12345', 'relative', '', [] );
		$result = $deployer->deploy( $job );

		$this->assertFalse( $result->success );
		$this->assertStringContainsString( 'Output directory', $result->message );
	}

	public function test_label_returns_git_push(): void {
		$this->set_option( 'sewp_settings', [] );

		$settings = new Settings();
		$logger   = new Logger();
		$deployer = new GitDeployer( $settings, $logger );

		$this->assertSame( 'Git Push', $deployer->label() );
	}
}
