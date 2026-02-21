<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Utility\PathHelper;

final class PathHelperTest extends TestCase {

	private PathHelper $helper;

	protected function setUp(): void {
		$this->helper = new PathHelper();
	}

	public function test_url_to_filepath_root(): void {
		$this->assertSame( 'index.html', $this->helper->url_to_filepath( '/' ) );
	}

	public function test_url_to_filepath_page(): void {
		$this->assertSame( 'about/index.html', $this->helper->url_to_filepath( '/about/' ) );
	}

	public function test_url_to_filepath_nested(): void {
		$this->assertSame( 'blog/2024/hello/index.html', $this->helper->url_to_filepath( '/blog/2024/hello/' ) );
	}

	public function test_url_to_filepath_with_extension(): void {
		$this->assertSame( 'wp-content/themes/style.css', $this->helper->url_to_filepath( '/wp-content/themes/style.css' ) );
	}

	public function test_url_to_filepath_full_url(): void {
		$this->assertSame( 'about/index.html', $this->helper->url_to_filepath( 'https://example.com/about/' ) );
	}

	public function test_resolve_path_removes_dots(): void {
		$this->assertSame( '/var/www/html/file.txt', $this->helper->resolve_path( '/var/www/./html/file.txt' ) );
	}

	public function test_resolve_path_removes_double_dots(): void {
		$this->assertSame( '/var/html/file.txt', $this->helper->resolve_path( '/var/www/../html/file.txt' ) );
	}

	public function test_safe_path_prevents_traversal(): void {
		$result = $this->helper->safe_path( '/var/www/output', '../../etc/passwd' );
		$this->assertFalse( $result );
	}

	public function test_safe_path_allows_valid(): void {
		$result = $this->helper->safe_path( '/var/www/output', 'about/index.html' );
		$this->assertSame( '/var/www/output/about/index.html', $result );
	}
}
