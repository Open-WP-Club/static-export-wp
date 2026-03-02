<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Export\SizeReport;

final class SizeReportTest extends TestCase {

	private string $tmp_dir;

	protected function setUp(): void {
		$this->tmp_dir = sys_get_temp_dir() . '/sewp_size_report_test_' . uniqid();
		mkdir( $this->tmp_dir, 0755, true );
	}

	protected function tearDown(): void {
		$this->remove_dir( $this->tmp_dir );
	}

	public function test_empty_dir_returns_zeros(): void {
		$result = SizeReport::scan( $this->tmp_dir );

		$this->assertSame( 0, $result['html'] );
		$this->assertSame( 0, $result['css'] );
		$this->assertSame( 0, $result['js'] );
		$this->assertSame( 0, $result['images'] );
		$this->assertSame( 0, $result['fonts'] );
		$this->assertSame( 0, $result['other'] );
		$this->assertSame( 0, $result['total'] );
	}

	public function test_nonexistent_dir_returns_zeros(): void {
		$result = SizeReport::scan( '/nonexistent/path/123456' );

		$this->assertSame( 0, $result['total'] );
	}

	public function test_categorizes_html_css_js(): void {
		file_put_contents( $this->tmp_dir . '/index.html', str_repeat( 'a', 100 ) );
		file_put_contents( $this->tmp_dir . '/style.css', str_repeat( 'b', 200 ) );
		file_put_contents( $this->tmp_dir . '/app.js', str_repeat( 'c', 300 ) );

		$result = SizeReport::scan( $this->tmp_dir );

		$this->assertSame( 100, $result['html'] );
		$this->assertSame( 200, $result['css'] );
		$this->assertSame( 300, $result['js'] );
	}

	public function test_categorizes_images(): void {
		file_put_contents( $this->tmp_dir . '/photo.jpg', str_repeat( 'x', 500 ) );
		file_put_contents( $this->tmp_dir . '/icon.png', str_repeat( 'y', 150 ) );
		file_put_contents( $this->tmp_dir . '/logo.svg', str_repeat( 'z', 80 ) );

		$result = SizeReport::scan( $this->tmp_dir );

		$this->assertSame( 730, $result['images'] );
	}

	public function test_total_is_sum_of_all(): void {
		file_put_contents( $this->tmp_dir . '/index.html', str_repeat( 'a', 100 ) );
		file_put_contents( $this->tmp_dir . '/style.css', str_repeat( 'b', 200 ) );
		file_put_contents( $this->tmp_dir . '/photo.jpg', str_repeat( 'c', 300 ) );
		file_put_contents( $this->tmp_dir . '/data.xml', str_repeat( 'd', 50 ) );

		$result = SizeReport::scan( $this->tmp_dir );

		$this->assertSame( 650, $result['total'] );
		$this->assertSame( 50, $result['other'] );
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
