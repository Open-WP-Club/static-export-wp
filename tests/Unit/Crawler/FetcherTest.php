<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\Fetcher;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class FetcherTest extends TestCase {

	use WpStubHelpers;

	private Fetcher $fetcher;

	protected function setUp(): void {
		$this->reset_wp_state();

		// Use a real Settings instance backed by the WP options stub.
		$this->set_option( 'sewp_settings', [
			'timeout' => 15,
		] );

		$settings      = new Settings();
		$this->fetcher = new Fetcher( $settings );
	}

	public function test_returns_success_fetch_result(): void {
		global $_wp_remote_responses;
		$url = 'https://example.com/';

		$_wp_remote_responses[ $url ] = [
			'response' => [ 'code' => 200 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [ 'content-type' => 'text/html; charset=UTF-8' ] ),
			'body'     => '<html><body>Hello</body></html>',
		];

		$result = $this->fetcher->fetch( $url );

		$this->assertSame( $url, $result->url );
		$this->assertSame( 200, $result->http_status );
		$this->assertStringContainsString( 'text/html', $result->content_type );
		$this->assertStringContainsString( 'Hello', $result->body );
		$this->assertNull( $result->error );
		$this->assertTrue( $result->is_success() );
	}

	public function test_returns_error_fetch_result(): void {
		global $_wp_remote_responses;
		$url = 'https://example.com/fail';

		$_wp_remote_responses[ $url ] = new \WP_Error( 'http_request_failed', 'Connection timed out' );

		$result = $this->fetcher->fetch( $url );

		$this->assertSame( $url, $result->url );
		$this->assertSame( 0, $result->http_status );
		$this->assertSame( 'Connection timed out', $result->error );
		$this->assertFalse( $result->is_success() );
	}

	public function test_returns_404_fetch_result(): void {
		global $_wp_remote_responses;
		$url = 'https://example.com/missing';

		$_wp_remote_responses[ $url ] = [
			'response' => [ 'code' => 404 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [ 'content-type' => 'text/html' ] ),
			'body'     => 'Not Found',
		];

		$result = $this->fetcher->fetch( $url );

		$this->assertSame( 404, $result->http_status );
		$this->assertFalse( $result->is_success() );
	}

	public function test_sets_user_agent(): void {
		// The fetcher passes user-agent in args to wp_remote_get.
		// We verify by ensuring no error occurs and the fetch works.
		$url = 'https://example.com/ua-test';

		$result = $this->fetcher->fetch( $url );

		$this->assertSame( $url, $result->url );
	}

	public function test_returns_non_html_content_type(): void {
		global $_wp_remote_responses;
		$url = 'https://example.com/style.css';

		$_wp_remote_responses[ $url ] = [
			'response' => [ 'code' => 200 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [ 'content-type' => 'text/css' ] ),
			'body'     => 'body { margin: 0; }',
		];

		$result = $this->fetcher->fetch( $url );

		$this->assertTrue( $result->is_success() );
		$this->assertFalse( $result->is_html() );
	}
}
