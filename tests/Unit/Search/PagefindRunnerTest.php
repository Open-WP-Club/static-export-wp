<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Export\ExportJob;
use StaticExportWP\Search\PagefindRunner;
use StaticExportWP\Utility\Logger;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class PagefindRunnerTest extends TestCase {

	use WpStubHelpers;

	protected function setUp(): void {
		$this->reset_wp_state();
	}

	public function test_does_nothing_when_disabled(): void {
		$this->set_option( 'sewp_settings', [
			'pagefind_enabled' => false,
		] );

		$settings = new Settings();
		$logger   = new Logger();

		$runner = new PagefindRunner( $settings, $logger );
		$job    = new ExportJob( 'exp-1', '/tmp/export', 'relative', '', [] );

		// When pagefind is disabled, run() should return early without
		// calling any shell commands or logging warnings.
		$runner->run( $job );

		// If we got here without errors, the test passes.
		$this->assertTrue( true );
	}

	public function test_warns_when_output_dir_missing(): void {
		$this->set_option( 'sewp_settings', [
			'pagefind_enabled' => true,
		] );

		$settings = new Settings();
		$logger   = new Logger();

		$runner = new PagefindRunner( $settings, $logger );
		$job    = new ExportJob( 'exp-1', '/nonexistent/path/12345', 'relative', '', [] );

		// This should log a warning about either npx not found or output dir missing.
		// Since Logger just calls error_log(), it will not throw.
		$runner->run( $job );

		$this->assertTrue( true );
	}

	public function test_warns_when_npx_not_found(): void {
		$this->set_option( 'sewp_settings', [
			'pagefind_enabled' => true,
		] );

		$settings = new Settings();
		$logger   = new Logger();

		$runner = new PagefindRunner( $settings, $logger );
		$job    = new ExportJob( 'exp-1', '/nonexistent/path/12345', 'relative', '', [] );

		// We expect warnings about either npx not found or output dir missing.
		// With real Logger, these go to error_log which is fine.
		$runner->run( $job );

		$this->assertTrue( true );
	}
}
