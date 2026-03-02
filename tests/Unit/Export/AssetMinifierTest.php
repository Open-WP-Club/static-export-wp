<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Export\AssetMinifier;
use StaticExportWP\Utility\Logger;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class AssetMinifierTest extends TestCase {

	use WpStubHelpers;

	private AssetMinifier $minifier;
	private string $tmp_dir;

	protected function setUp(): void {
		$this->reset_wp_state();
		// Use a real Logger -- it just calls error_log(), which is harmless in tests.
		$logger         = new Logger();
		$this->minifier = new AssetMinifier( $logger );
		$this->tmp_dir  = sys_get_temp_dir() . '/sewp_minifier_test_' . uniqid();
		mkdir( $this->tmp_dir, 0755, true );
	}

	protected function tearDown(): void {
		$this->remove_dir( $this->tmp_dir );
	}

	public function test_minify_css_reduces_size(): void {
		$css = "body {\n    margin:    0;\n    padding:   0;\n}\n\nh1 {\n    color: red;\n}\n";
		$file = $this->tmp_dir . '/style.css';
		file_put_contents( $file, $css );

		$result = $this->minifier->minify_css( $file );

		$this->assertTrue( $result );
		$this->assertLessThan( strlen( $css ), strlen( file_get_contents( $file ) ) );
	}

	public function test_minify_js_reduces_size(): void {
		$js = "function hello() {\n    var x = 1;\n    var y = 2;\n    return x + y;\n}\n";
		$file = $this->tmp_dir . '/app.js';
		file_put_contents( $file, $js );

		$result = $this->minifier->minify_js( $file );

		$this->assertTrue( $result );
		$this->assertLessThanOrEqual( strlen( $js ), strlen( file_get_contents( $file ) ) );
	}

	#[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
	public function test_minify_css_returns_false_for_missing_file(): void {
		$result = @$this->minifier->minify_css( $this->tmp_dir . '/nonexistent.css' );

		$this->assertFalse( $result );
	}

	#[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
	public function test_minify_js_returns_false_for_missing_file(): void {
		$result = @$this->minifier->minify_js( $this->tmp_dir . '/nonexistent.js' );

		$this->assertFalse( $result );
	}

	public function test_minify_asset_dispatches_css(): void {
		$file = $this->tmp_dir . '/style.css';
		file_put_contents( $file, "body { margin: 0; }" );

		$result = $this->minifier->minify_asset( $file, true, false );

		$this->assertTrue( $result );
	}

	public function test_minify_asset_dispatches_js(): void {
		$file = $this->tmp_dir . '/app.js';
		file_put_contents( $file, "var x = 1;" );

		$result = $this->minifier->minify_asset( $file, false, true );

		$this->assertTrue( $result );
	}

	public function test_minify_asset_returns_false_for_unknown_extension(): void {
		$file = $this->tmp_dir . '/data.xml';
		file_put_contents( $file, '<xml/>' );

		$result = $this->minifier->minify_asset( $file, true, true );

		$this->assertFalse( $result );
	}

	public function test_minify_asset_returns_false_when_disabled(): void {
		$file = $this->tmp_dir . '/style.css';
		file_put_contents( $file, "body { margin: 0; }" );

		$result = $this->minifier->minify_asset( $file, false, false );

		$this->assertFalse( $result );
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);
		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $dir );
	}
}
