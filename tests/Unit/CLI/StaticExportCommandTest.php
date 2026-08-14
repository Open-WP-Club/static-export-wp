<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Background\ActionSchedulerBridge;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\CLI\StaticExportCommand;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Crawler\UrlDiscovery;
use StaticExportWP\Tests\Helpers\ReflectionHelper;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

/**
 * Tests for the `wp static-export` CLI command.
 */
final class StaticExportCommandTest extends TestCase {

	use WpStubHelpers;

	private string $output_dir;
	private ProgressTracker $progress;
	private StaticExportCommand $command;

	protected function setUp(): void {
		$this->reset_wp_state();

		$GLOBALS['wpdb'] = new \wpdb();
		global $wp_filesystem;
		$wp_filesystem = new \WP_Filesystem_Stub();

		\WP_CLI::$_log = [];
		global $_wp_cli_formatted_items;
		$_wp_cli_formatted_items = null;

		$this->output_dir = sys_get_temp_dir() . '/sewp_cli_test_' . uniqid();

		$settings    = new Settings();
		$this->progress = new ProgressTracker();
		$crawl_queue = new CrawlQueue();
		$scheduler   = new ActionSchedulerBridge();

		$this->set_option( 'sewp_settings', [
			'output_dir' => $this->output_dir,
		] );

		$export_manager = ReflectionHelper::buildRealExportManager(
			settings: $settings,
			progress: $this->progress,
			crawl_queue: $crawl_queue,
			scheduler: $scheduler,
		);

		$this->command = new StaticExportCommand(
			$export_manager,
			$settings,
			new UrlDiscovery( $settings ),
			$this->progress,
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );

		if ( is_dir( $this->output_dir ) ) {
			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $this->output_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST,
			);
			foreach ( $items as $item ) {
				$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
			}
			rmdir( $this->output_dir );
		}
	}

	private function last_log( string $level ): ?array {
		$matches = array_values( array_filter( \WP_CLI::$_log, fn( $l ) => $level === $l['level'] ) );
		return $matches ? end( $matches ) : null;
	}

	// ── generate() ─────────────────────────────────────────────────────────

	public function test_generate_errors_when_export_already_running(): void {
		$this->progress->start( 'exp-running', 10 );

		$this->expectException( \WP_CLI_ExitException::class );
		$this->expectExceptionMessage( 'An export is already running' );

		$this->command->generate( [], [] );
	}

	public function test_generate_starts_background_export_by_default(): void {
		$this->command->generate( [], [] );

		global $_wp_cron_events;
		$events = array_filter( $_wp_cron_events, fn( $e ) => 'sewp_process_batch' === $e['hook'] );
		$this->assertNotEmpty( $events, 'Background export must schedule a batch cron event' );

		$success = $this->last_log( 'success' );
		$this->assertNotNull( $success );
		$this->assertStringContainsString( 'Background export started', $success['message'] );
	}

	public function test_generate_synchronous_completes_and_logs_success(): void {
		// First query() call is CrawlQueue::enqueue()'s INSERT (in start()); the second
		// is retry_failed(), which must return 0 to break run_sync()'s retry loop --
		// the stub otherwise defaults query() to "1 row affected", looping forever.
		$GLOBALS['wpdb']->_query_returns[] = 1;
		$GLOBALS['wpdb']->_query_returns[] = 0;

		$this->command->generate( [], [ 'synchronous' => true ] );

		$success = $this->last_log( 'success' );
		$this->assertNotNull( $success );
		$this->assertStringContainsString( 'Export completed', $success['message'] );
	}

	public function test_generate_applies_output_dir_override(): void {
		$GLOBALS['wpdb']->_query_returns[] = 1;
		$GLOBALS['wpdb']->_query_returns[] = 0;
		$custom_dir = sys_get_temp_dir() . '/sewp_cli_override_' . uniqid();

		$this->command->generate( [], [ 'synchronous' => true, 'output-dir' => $custom_dir ] );

		$this->assertDirectoryExists( $custom_dir );

		// Cleanup the override dir (separate from $this->output_dir).
		@unlink( $custom_dir . '/.htaccess' );
		@rmdir( $custom_dir );
	}

	// ── status() ───────────────────────────────────────────────────────────

	public function test_status_logs_message_when_no_export_data(): void {
		$this->command->status( [], [] );

		$log = $this->last_log( 'log' );
		$this->assertNotNull( $log );
		$this->assertStringContainsString( 'No export data available', $log['message'] );
	}

	public function test_status_json_format_logs_encoded_progress(): void {
		$this->progress->start( 'exp-1', 10 );

		$this->command->status( [], [ 'format' => 'json' ] );

		$log = $this->last_log( 'log' );
		$this->assertNotNull( $log );
		$decoded = json_decode( $log['message'], true );
		$this->assertSame( 'exp-1', $decoded['export_id'] );
	}

	public function test_status_table_format_calls_format_items(): void {
		$this->progress->start( 'exp-1', 10 );

		$this->command->status( [], [] );

		global $_wp_cli_formatted_items;
		$this->assertNotNull( $_wp_cli_formatted_items );
		$this->assertSame( 'table', $_wp_cli_formatted_items['format'] );
	}

	// ── cancel() ───────────────────────────────────────────────────────────

	public function test_cancel_warns_when_nothing_running(): void {
		$this->command->cancel( [], [] );

		$warning = $this->last_log( 'warning' );
		$this->assertNotNull( $warning );
		$this->assertStringContainsString( 'No running export', $warning['message'] );
	}

	public function test_cancel_stops_running_export(): void {
		$this->progress->start( 'exp-1', 10 );

		$this->command->cancel( [], [] );

		$this->assertTrue( $this->progress->is_cancelled( 'exp-1' ) );
		$success = $this->last_log( 'success' );
		$this->assertNotNull( $success );
	}

	// ── clean() ────────────────────────────────────────────────────────────

	public function test_clean_logs_when_output_dir_missing(): void {
		$this->command->clean( [], [] );

		$log = $this->last_log( 'log' );
		$this->assertNotNull( $log );
		$this->assertStringContainsString( 'does not exist', $log['message'] );
	}

	public function test_clean_removes_existing_output_dir(): void {
		mkdir( $this->output_dir, 0755, true );
		file_put_contents( $this->output_dir . '/index.html', 'hi' );

		$this->command->clean( [], [ 'yes' => true ] );

		$this->assertDirectoryDoesNotExist( $this->output_dir );
		$success = $this->last_log( 'success' );
		$this->assertNotNull( $success );
	}

	// ── list-urls ──────────────────────────────────────────────────────────

	public function test_list_urls_count_format_logs_number(): void {
		$this->command->list_urls( [], [ 'format' => 'count' ] );

		$log = $this->last_log( 'log' );
		$this->assertNotNull( $log );
		// Default discovery (no configured post types) still yields the home URL.
		$this->assertSame( '1', $log['message'] );
	}

	public function test_list_urls_table_format_calls_format_items(): void {
		$this->command->list_urls( [], [] );

		global $_wp_cli_formatted_items;
		$this->assertNotNull( $_wp_cli_formatted_items );
		$this->assertSame( 'table', $_wp_cli_formatted_items['format'] );
		$this->assertSame( [ 'url' ], $_wp_cli_formatted_items['fields'] );
	}
}
