<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Utility\PathHelper;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class PathHelperTest extends TestCase {

	use WpStubHelpers;

	private PathHelper $helper;

	protected function setUp(): void {
		$this->reset_wp_state();
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

	public function test_url_to_filepath_ignores_query_string(): void {
		$this->assertSame( 'page/index.html', $this->helper->url_to_filepath( '/page/?foo=bar' ) );
	}

	public function test_url_to_filepath_ignores_fragment(): void {
		$this->assertSame( 'page/index.html', $this->helper->url_to_filepath( '/page/#section' ) );
	}

	public function test_resolve_path_removes_dots(): void {
		$this->assertSame( '/var/www/html/file.txt', $this->helper->resolve_path( '/var/www/./html/file.txt' ) );
	}

	public function test_resolve_path_removes_double_dots(): void {
		$this->assertSame( '/var/html/file.txt', $this->helper->resolve_path( '/var/www/../html/file.txt' ) );
	}

	public function test_resolve_path_multiple_double_dots(): void {
		$this->assertSame( '/file.txt', $this->helper->resolve_path( '/var/www/../../file.txt' ) );
	}

	public function test_safe_path_prevents_traversal(): void {
		$result = $this->helper->safe_path( '/var/www/output', '../../etc/passwd' );
		$this->assertFalse( $result );
	}

	public function test_safe_path_allows_valid(): void {
		$result = $this->helper->safe_path( '/var/www/output', 'about/index.html' );
		$this->assertSame( '/var/www/output/about/index.html', $result );
	}

	public function test_ensure_directory_creates_dir(): void {
		$dir = sys_get_temp_dir() . '/sewp_pathhelper_test_' . uniqid();
		$this->assertFalse( is_dir( $dir ) );

		$result = $this->helper->ensure_directory( $dir );

		$this->assertTrue( $result );
		$this->assertTrue( is_dir( $dir ) );
		rmdir( $dir );
	}
}
