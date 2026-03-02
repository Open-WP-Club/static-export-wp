<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Notification\ExportNotifier;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class ExportNotifierTest extends TestCase {

	use WpStubHelpers;

	protected function setUp(): void {
		$this->reset_wp_state();
	}

	public function test_sends_email_when_enabled(): void {
		$this->set_option( 'sewp_settings', [
			'notify_enabled' => true,
			'notify_email'   => 'user@example.com',
		] );

		$settings = new Settings();
		$notifier = new ExportNotifier( $settings );
		$notifier->notify( 'exp-1', 'completed', [ 'total' => 10, 'completed' => 10, 'failed' => 0 ], '5 mins' );

		$log = $this->get_mail_log();
		$this->assertCount( 1, $log );
		$this->assertSame( 'user@example.com', $log[0]['to'] );
	}

	public function test_uses_admin_email_when_no_notify_email(): void {
		$this->set_option( 'admin_email', 'admin@example.com' );
		$this->set_option( 'sewp_settings', [
			'notify_enabled' => true,
			'notify_email'   => '',
		] );

		$settings = new Settings();
		$notifier = new ExportNotifier( $settings );
		$notifier->notify( 'exp-1', 'completed', [ 'total' => 10, 'completed' => 10, 'failed' => 0 ], '5 mins' );

		$log = $this->get_mail_log();
		$this->assertCount( 1, $log );
		$this->assertSame( 'admin@example.com', $log[0]['to'] );
	}

	public function test_does_nothing_when_disabled(): void {
		$this->set_option( 'sewp_settings', [
			'notify_enabled' => false,
		] );

		$settings = new Settings();
		$notifier = new ExportNotifier( $settings );
		$notifier->notify( 'exp-1', 'completed', [ 'total' => 10, 'completed' => 10, 'failed' => 0 ], '5 mins' );

		$this->assertEmpty( $this->get_mail_log() );
	}

	public function test_subject_contains_site_name_and_status(): void {
		$this->set_bloginfo( 'name', 'My Blog' );
		$this->set_option( 'sewp_settings', [
			'notify_enabled' => true,
			'notify_email'   => 'user@test.com',
		] );

		$settings = new Settings();
		$notifier = new ExportNotifier( $settings );
		$notifier->notify( 'exp-1', 'completed', [ 'total' => 10, 'completed' => 10, 'failed' => 0 ], '5 mins' );

		$log = $this->get_mail_log();
		$this->assertStringContainsString( 'My Blog', $log[0]['subject'] );
		$this->assertStringContainsString( 'completed', $log[0]['subject'] );
	}

	public function test_body_contains_export_details(): void {
		$this->set_option( 'sewp_settings', [
			'notify_enabled' => true,
			'notify_email'   => 'user@test.com',
		] );

		$settings = new Settings();
		$notifier = new ExportNotifier( $settings );
		$notifier->notify( 'exp-1', 'completed', [ 'total' => 100, 'completed' => 95, 'failed' => 5 ], '10 mins' );

		$log = $this->get_mail_log();
		$this->assertStringContainsString( 'exp-1', $log[0]['message'] );
		$this->assertStringContainsString( '100', $log[0]['message'] );
		$this->assertStringContainsString( '10 mins', $log[0]['message'] );
	}
}
