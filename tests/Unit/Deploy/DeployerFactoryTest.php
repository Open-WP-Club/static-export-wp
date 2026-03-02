<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Deploy\CommandDeployer;
use StaticExportWP\Deploy\DeployerFactory;
use StaticExportWP\Deploy\GitDeployer;
use StaticExportWP\Deploy\NetlifyDeployer;
use StaticExportWP\Utility\Logger;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class DeployerFactoryTest extends TestCase {

	use WpStubHelpers;

	private Logger $logger;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->logger = new Logger();
	}

	public function test_returns_null_for_none(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_method' => 'none',
		] );

		$settings = new Settings();
		$factory  = new DeployerFactory( $settings, $this->logger );
		$this->assertNull( $factory->create() );
	}

	public function test_returns_command_deployer(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_method' => 'command',
		] );

		$settings = new Settings();
		$factory  = new DeployerFactory( $settings, $this->logger );
		$deployer = $factory->create();

		$this->assertInstanceOf( CommandDeployer::class, $deployer );
	}

	public function test_returns_git_deployer(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_method' => 'git',
		] );

		$settings = new Settings();
		$factory  = new DeployerFactory( $settings, $this->logger );
		$deployer = $factory->create();

		$this->assertInstanceOf( GitDeployer::class, $deployer );
	}

	public function test_returns_netlify_deployer(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_method' => 'netlify',
		] );

		$settings = new Settings();
		$factory  = new DeployerFactory( $settings, $this->logger );
		$deployer = $factory->create();

		$this->assertInstanceOf( NetlifyDeployer::class, $deployer );
	}

	public function test_returns_null_for_unknown(): void {
		$this->set_option( 'sewp_settings', [
			'deploy_method' => 'unknown_method',
		] );

		$settings = new Settings();
		$factory  = new DeployerFactory( $settings, $this->logger );
		$this->assertNull( $factory->create() );
	}
}
