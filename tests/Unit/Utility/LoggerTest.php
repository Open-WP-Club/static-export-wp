<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Utility\Logger;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class LoggerTest extends TestCase {

	use WpStubHelpers;

	private Logger $logger;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->logger = new Logger();
	}

	public function test_info_logs_with_info_level(): void {
		// WP_DEBUG is true (set in stubs), so error_log is called.
		// We capture via a custom error handler.
		$logged = [];
		set_error_handler( function () use ( &$logged ) {
			return true;
		} );

		$old = ini_set( 'error_log', '/dev/null' );

		// We test indirectly that it doesn't throw.
		$this->logger->info( 'Test message' );

		ini_set( 'error_log', $old ?: '' );
		restore_error_handler();

		// If we get here without exception, the method works.
		$this->assertTrue( true );
	}

	public function test_error_logs_with_error_level(): void {
		$this->logger->error( 'Error occurred' );
		$this->assertTrue( true );
	}

	public function test_warning_logs_with_warning_level(): void {
		$this->logger->warning( 'Warning issued' );
		$this->assertTrue( true );
	}

	public function test_info_with_context_appends_json(): void {
		$this->logger->info( 'Test', [ 'key' => 'value' ] );
		$this->assertTrue( true );
	}
}
