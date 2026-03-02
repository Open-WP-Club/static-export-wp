<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Crawler\FetchResult;

final class FetchResultTest extends TestCase {

	public function test_is_success_with_200(): void {
		$result = new FetchResult( 'https://example.com', 200, 'text/html', '<html></html>', [] );
		$this->assertTrue( $result->is_success() );
	}

	public function test_is_success_with_301(): void {
		$result = new FetchResult( 'https://example.com', 301, 'text/html', '', [] );
		$this->assertTrue( $result->is_success() );
	}

	public function test_is_not_success_with_404(): void {
		$result = new FetchResult( 'https://example.com', 404, 'text/html', '', [] );
		$this->assertFalse( $result->is_success() );
	}

	public function test_is_not_success_with_500(): void {
		$result = new FetchResult( 'https://example.com', 500, 'text/html', '', [] );
		$this->assertFalse( $result->is_success() );
	}

	public function test_is_not_success_with_error(): void {
		$result = new FetchResult( 'https://example.com', 200, 'text/html', '', [], 'Connection failed' );
		$this->assertFalse( $result->is_success() );
	}

	public function test_is_html_with_text_html(): void {
		$result = new FetchResult( 'https://example.com', 200, 'text/html', '', [] );
		$this->assertTrue( $result->is_html() );
	}

	public function test_is_html_with_text_html_charset(): void {
		$result = new FetchResult( 'https://example.com', 200, 'text/html; charset=UTF-8', '', [] );
		$this->assertTrue( $result->is_html() );
	}

	public function test_is_not_html_with_text_css(): void {
		$result = new FetchResult( 'https://example.com', 200, 'text/css', '', [] );
		$this->assertFalse( $result->is_html() );
	}
}
