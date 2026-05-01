<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Export\FileWriter;
use StaticExportWP\Tests\Helpers\WpStubHelpers;
use StaticExportWP\Utility\PathHelper;

/**
 * Tests for FileWriter.
 *
 * Uses a temporary directory for real filesystem operations so we verify
 * that the PathHelper + WP_Filesystem integration actually writes files.
 * FileWriter must log (not silently swallow) write failures.
 */
final class FileWriterTest extends TestCase {

	use WpStubHelpers;

	private string $output_dir;
	private FileWriter $writer;

	protected function setUp(): void {
		$this->reset_wp_state();

		$this->output_dir = sys_get_temp_dir() . '/sewp_test_' . uniqid();
		mkdir( $this->output_dir, 0755, true );

		$this->writer = new FileWriter( new PathHelper() );

		// Bootstrap a direct WP_Filesystem using the 'direct' method so tests
		// don't need wp-admin includes.
		global $wp_filesystem;
		$wp_filesystem = new FakeWpFilesystem();
	}

	protected function tearDown(): void {
		// Clean up temp directory.
		if ( is_dir( $this->output_dir ) ) {
			array_map( 'unlink', glob( $this->output_dir . '/*' ) ?: [] );
			rmdir( $this->output_dir );
		}
	}

	// ── write_html ─────────────────────────────────────────────────────────

	public function test_write_html_returns_relative_path_on_success(): void {
		global $wp_filesystem;
		$wp_filesystem->put_contents_result = true;

		$result = $this->writer->write_html(
			$this->output_dir,
			'https://example.com/',
			'<html><body>Hello</body></html>',
		);

		$this->assertSame( 'index.html', $result );
	}

	public function test_write_html_returns_false_when_filesystem_fails(): void {
		global $wp_filesystem;
		$wp_filesystem->put_contents_result = false;

		$result = $this->writer->write_html(
			$this->output_dir,
			'https://example.com/about/',
			'<html></html>',
		);

		$this->assertFalse( $result );
	}

	/**
	 * FileWriter must log (not silently swallow) write failures.
	 */
	public function test_write_html_logs_error_on_filesystem_failure(): void {
		global $wp_filesystem;
		$wp_filesystem->put_contents_result = false;

		// Redirect error_log to a temp file (WP_DEBUG is true in bootstrap).
		$logfile = tempnam( sys_get_temp_dir(), 'sewp_log_' );
		$prev    = ini_set( 'error_log', $logfile );

		$this->writer->write_html(
			$this->output_dir,
			'https://example.com/contact/',
			'<html></html>',
		);

		ini_set( 'error_log', $prev ?: '' );
		$logged = (string) file_get_contents( $logfile );
		unlink( $logfile );

		$this->assertStringContainsString( 'Failed to write HTML file', $logged,
			'A write failure must produce an error log entry' );
	}

	// ── copy_asset ─────────────────────────────────────────────────────────

	public function test_copy_asset_skips_php_files(): void {
		$result = $this->writer->copy_asset(
			$this->output_dir,
			'https://example.com/wp-cron.php',
			'https://example.com',
		);

		$this->assertFalse( $result );
	}

	public function test_copy_asset_returns_false_for_external_url(): void {
		// An external URL has no matching local path; wp_remote_get stub returns WP_Error.
		$result = $this->writer->copy_asset(
			$this->output_dir,
			'https://cdn.other.com/style.css',
			'https://example.com',
		);

		$this->assertFalse( $result );
	}

	public function test_copy_asset_caches_result_on_second_call(): void {
		global $wp_filesystem;
		$wp_filesystem->put_contents_result = true;

		// Create a fake local CSS file.
		$css_src = $this->output_dir . '/style.css';
		file_put_contents( $css_src, 'body{}' );

		// Point ABSPATH to our temp dir so url_to_local_path resolves correctly.
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', $this->output_dir . '/' );
		}

		// First call copies the asset.
		$dest_dir = sys_get_temp_dir() . '/sewp_dest_' . uniqid();
		mkdir( $dest_dir, 0755, true );

		$result1 = $this->writer->copy_asset(
			$dest_dir,
			'https://example.com/style.css',
			'https://example.com',
		);
		$result2 = $this->writer->copy_asset(
			$dest_dir,
			'https://example.com/style.css',
			'https://example.com',
		);

		// Both calls must return the same relative path (second from cache).
		$this->assertSame( $result1, $result2 );

		// Clean up.
		@unlink( $css_src );
		array_map( 'unlink', glob( $dest_dir . '/*' ) ?: [] );
		@rmdir( $dest_dir );
	}
}

/**
 * Fake WP_Filesystem for tests — avoids loading wp-admin/includes/file.php.
 */
class FakeWpFilesystem {

	public bool $put_contents_result = true;

	public function put_contents( string $file, string $contents, int $mode = 0644 ): bool {
		if ( $this->put_contents_result ) {
			// Actually write so path-existence checks in subsequent calls work.
			@mkdir( dirname( $file ), 0755, true );
			return (bool) file_put_contents( $file, $contents );
		}
		return false;
	}

	public function rmdir( string $path, bool $recursive = false ): bool { // @phpstan-ignore-line
		return true;
	}
}
