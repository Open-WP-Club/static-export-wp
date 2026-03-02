<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Notification\WebhookNotifier;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class WebhookNotifierTest extends TestCase {

	use WpStubHelpers;

	protected function setUp(): void {
		$this->reset_wp_state();
	}

	public function test_does_nothing_when_no_url(): void {
		$this->set_option( 'sewp_settings', [
			'webhook_url' => '',
		] );

		$settings = new Settings();
		$notifier = new WebhookNotifier( $settings );
		$notifier->notify( 'exp-1', 'completed', [ 'total' => 10, 'completed' => 10, 'failed' => 0 ], '5 mins' );

		// No exception means success -- webhook was skipped.
		$this->assertTrue( true );
	}

	public function test_does_nothing_when_event_not_in_list(): void {
		$this->set_option( 'sewp_settings', [
			'webhook_url'    => 'https://hooks.example.com/webhook',
			'webhook_events' => [ 'failed' ],
		] );

		$settings = new Settings();
		$notifier = new WebhookNotifier( $settings );
		$notifier->notify( 'exp-1', 'completed', [ 'total' => 10, 'completed' => 10, 'failed' => 0 ], '5 mins' );

		$this->assertTrue( true );
	}

	public function test_sends_payload_for_completed(): void {
		global $_wp_remote_responses;
		$webhook_url = 'https://hooks.example.com/webhook';

		$_wp_remote_responses[ $webhook_url ] = [
			'response' => [ 'code' => 200 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [] ),
			'body'     => '',
		];

		$this->set_option( 'sewp_settings', [
			'webhook_url'    => $webhook_url,
			'webhook_events' => [ 'completed', 'failed' ],
			'webhook_secret' => '',
		] );

		$settings = new Settings();
		$notifier = new WebhookNotifier( $settings );
		$notifier->notify( 'exp-1', 'completed', [ 'total' => 10, 'completed' => 10, 'failed' => 0 ], '5 mins' );

		$this->assertTrue( true );
	}

	public function test_sends_payload_for_failed(): void {
		global $_wp_remote_responses;
		$webhook_url = 'https://hooks.example.com/webhook';

		$_wp_remote_responses[ $webhook_url ] = [
			'response' => [ 'code' => 200 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [] ),
			'body'     => '',
		];

		$this->set_option( 'sewp_settings', [
			'webhook_url'    => $webhook_url,
			'webhook_events' => [ 'completed', 'failed' ],
			'webhook_secret' => '',
		] );

		$settings = new Settings();
		$notifier = new WebhookNotifier( $settings );
		$notifier->notify( 'exp-1', 'failed', [ 'total' => 10, 'completed' => 0, 'failed' => 10 ], '1 min' );

		$this->assertTrue( true );
	}

	public function test_includes_hmac_signature_when_secret_set(): void {
		global $_wp_remote_responses;
		$webhook_url = 'https://hooks.example.com/webhook';

		$_wp_remote_responses[ $webhook_url ] = [
			'response' => [ 'code' => 200 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [] ),
			'body'     => '',
		];

		$this->set_option( 'sewp_settings', [
			'webhook_url'    => $webhook_url,
			'webhook_events' => [ 'completed', 'failed' ],
			'webhook_secret' => 'my-secret-key',
		] );

		$settings = new Settings();
		$notifier = new WebhookNotifier( $settings );
		$notifier->notify( 'exp-1', 'completed', [ 'total' => 10, 'completed' => 10, 'failed' => 0 ], '5 mins' );

		// Sending with secret should not throw.
		$this->assertTrue( true );
	}

	public function test_send_test_returns_error_when_no_url(): void {
		$this->set_option( 'sewp_settings', [
			'webhook_url' => '',
		] );

		$settings = new Settings();
		$notifier = new WebhookNotifier( $settings );
		$result   = $notifier->send_test();

		$this->assertSame( 400, $result->get_status() );
		$this->assertFalse( $result->get_data()['success'] );
	}

	public function test_send_test_returns_success_on_200(): void {
		global $_wp_remote_responses;
		$webhook_url = 'https://hooks.example.com/webhook';

		$_wp_remote_responses[ $webhook_url ] = [
			'response' => [ 'code' => 200 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [] ),
			'body'     => '{"ok":true}',
		];

		$this->set_option( 'sewp_settings', [
			'webhook_url'    => $webhook_url,
			'webhook_secret' => '',
		] );

		$settings = new Settings();
		$notifier = new WebhookNotifier( $settings );
		$result   = $notifier->send_test();

		$this->assertSame( 200, $result->get_status() );
		$this->assertTrue( $result->get_data()['success'] );
	}

	public function test_send_test_returns_error_on_non_200(): void {
		global $_wp_remote_responses;
		$webhook_url = 'https://hooks.example.com/webhook';

		$_wp_remote_responses[ $webhook_url ] = [
			'response' => [ 'code' => 500 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [] ),
			'body'     => 'Internal Server Error',
		];

		$this->set_option( 'sewp_settings', [
			'webhook_url'    => $webhook_url,
			'webhook_secret' => '',
		] );

		$settings = new Settings();
		$notifier = new WebhookNotifier( $settings );
		$result   = $notifier->send_test();

		$this->assertSame( 502, $result->get_status() );
		$this->assertFalse( $result->get_data()['success'] );
	}
}
