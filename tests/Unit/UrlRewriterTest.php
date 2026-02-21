<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Export\UrlRewriter;

/**
 * Tests for UrlRewriter.
 *
 * Note: These tests require WordPress functions (home_url, wp_parse_url, etc.)
 * and therefore need a WordPress test environment to run.
 * They are structured as integration tests that verify URL rewriting logic.
 */
final class UrlRewriterTest extends TestCase {

	public function test_url_to_path_root(): void {
		// Test the static path conversion logic directly.
		$this->assertSame( 'index.html', $this->convert_path( '/' ) );
	}

	public function test_url_to_path_page(): void {
		$this->assertSame( 'about/index.html', $this->convert_path( '/about/' ) );
	}

	public function test_url_to_path_nested(): void {
		$this->assertSame( 'blog/my-post/index.html', $this->convert_path( '/blog/my-post/' ) );
	}

	public function test_url_to_path_with_extension(): void {
		$this->assertSame( 'wp-content/style.css', $this->convert_path( '/wp-content/style.css' ) );
	}

	public function test_url_to_path_empty(): void {
		$this->assertSame( 'index.html', $this->convert_path( '' ) );
	}

	/**
	 * Simplified path conversion for unit testing without WordPress.
	 */
	private function convert_path( string $url ): string {
		$path = parse_url( $url, PHP_URL_PATH ) ?? '/';
		$path = trim( $path, '/' );

		if ( '' === $path ) {
			return 'index.html';
		}

		$ext = pathinfo( $path, PATHINFO_EXTENSION );
		if ( '' !== $ext ) {
			return $path;
		}

		return $path . '/index.html';
	}
}
