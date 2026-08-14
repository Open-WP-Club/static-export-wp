<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\BatchFetcher;
use StaticExportWP\Tests\Helpers\WpStubHelpers;
use WpOrg\Requests\Exception as RequestsException;
use WpOrg\Requests\Requests;

final class BatchFetcherTest extends TestCase {

	use WpStubHelpers;

	private BatchFetcher $fetcher;

	protected function setUp(): void {
		$this->reset_wp_state();

		$this->set_option( 'sewp_settings', [
			'timeout' => 15,
		] );

		$settings     = new Settings();
		$this->fetcher = new BatchFetcher( $settings );
	}

	public function test_fetch_batch_returns_empty_array_for_empty_input(): void {
		$result = $this->fetcher->fetch_batch( [] );

		$this->assertSame( [], $result );
	}

	public function test_fetch_batch_returns_success_results_keyed_by_url(): void {
		$url = 'https://example.com/';

		Requests::$_responses[ $url ] = [
			'status_code' => 200,
			'headers'     => [ 'content-type' => 'text/html; charset=UTF-8' ],
			'body'        => '<html><body>Hello</body></html>',
		];

		$results = $this->fetcher->fetch_batch( [ $url ] );

		$this->assertArrayHasKey( $url, $results );
		$result = $results[ $url ];
		$this->assertSame( $url, $result->url );
		$this->assertSame( 200, $result->http_status );
		$this->assertStringContainsString( 'text/html', $result->content_type );
		$this->assertStringContainsString( 'Hello', $result->body );
		$this->assertTrue( $result->is_success() );
	}

	public function test_fetch_batch_handles_multiple_urls(): void {
		$url1 = 'https://example.com/';
		$url2 = 'https://example.com/about/';

		Requests::$_responses[ $url1 ] = [ 'status_code' => 200, 'headers' => [], 'body' => 'home' ];
		Requests::$_responses[ $url2 ] = [ 'status_code' => 200, 'headers' => [], 'body' => 'about' ];

		$results = $this->fetcher->fetch_batch( [ $url1, $url2 ] );

		$this->assertCount( 2, $results );
		$this->assertSame( 'home', $results[ $url1 ]->body );
		$this->assertSame( 'about', $results[ $url2 ]->body );
	}

	public function test_fetch_batch_converts_exception_to_error_result(): void {
		$url = 'https://example.com/fail';

		Requests::$_responses[ $url ] = new RequestsException( 'Connection timed out', 'curlerror' );

		$results = $this->fetcher->fetch_batch( [ $url ] );

		$result = $results[ $url ];
		$this->assertSame( $url, $result->url );
		$this->assertSame( 0, $result->http_status );
		$this->assertSame( 'Connection timed out', $result->error );
		$this->assertFalse( $result->is_success() );
	}

	public function test_fetch_batch_defaults_to_200_when_no_response_queued(): void {
		$url = 'https://example.com/no-stub';

		$results = $this->fetcher->fetch_batch( [ $url ] );

		$this->assertSame( 200, $results[ $url ]->http_status );
	}

	public function test_fetch_batch_reports_non_200_status(): void {
		$url = 'https://example.com/missing';

		Requests::$_responses[ $url ] = [ 'status_code' => 404, 'headers' => [], 'body' => 'Not Found' ];

		$results = $this->fetcher->fetch_batch( [ $url ] );

		$this->assertSame( 404, $results[ $url ]->http_status );
		$this->assertFalse( $results[ $url ]->is_success() );
	}
}
