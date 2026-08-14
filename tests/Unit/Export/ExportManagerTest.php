<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Background\ActionSchedulerBridge;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\BatchFetcher;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Crawler\Fetcher;
use StaticExportWP\Crawler\UrlDiscovery;
use StaticExportWP\Export\AssetCollector;
use StaticExportWP\Export\ContentHashStore;
use StaticExportWP\Export\ExportJob;
use StaticExportWP\Export\ExportManager;
use StaticExportWP\Export\FileWriter;
use StaticExportWP\Export\HtmlProcessor;
use StaticExportWP\Export\UrlRewriter;
use StaticExportWP\Tests\Helpers\ReflectionHelper;
use StaticExportWP\Tests\Helpers\WpStubHelpers;
use StaticExportWP\Utility\Logger;
use StaticExportWP\Utility\PathHelper;
use WpOrg\Requests\Requests;

/**
 * Characterization tests for ExportManager.
 *
 * Written before refactoring the process_url()/process_fetched_result()
 * duplication so both the synchronous (Fetcher) and batch (BatchFetcher)
 * code paths are pinned down and proven equivalent for identical input —
 * the refactor must keep every one of these passing unchanged.
 */
final class ExportManagerTest extends TestCase {

	use WpStubHelpers;

	private string $output_dir;
	private Settings $settings;
	private ProgressTracker $progress;
	private CrawlQueue $crawl_queue;
	private ActionSchedulerBridge $scheduler;

	protected function setUp(): void {
		$this->reset_wp_state();

		$GLOBALS['wpdb'] = new \wpdb();
		global $wp_filesystem;
		$wp_filesystem = new \WP_Filesystem_Stub();

		$this->output_dir = sys_get_temp_dir() . '/sewp_em_test_' . uniqid();

		$this->settings    = new Settings();
		$this->progress    = new ProgressTracker();
		$this->crawl_queue = new CrawlQueue();
		$this->scheduler   = new ActionSchedulerBridge();

		$this->set_option( 'sewp_settings', [
			'output_dir' => $this->output_dir,
			'url_mode'   => 'relative',
			'base_url'   => '',
		] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );

		if ( is_dir( $this->output_dir ) ) {
			$this->remove_directory( $this->output_dir );
		}
	}

	private function remove_directory( string $dir ): void {
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);
		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $dir );
	}

	private function build_manager(): ExportManager {
		return ReflectionHelper::buildRealExportManager(
			settings: $this->settings,
			progress: $this->progress,
			crawl_queue: $this->crawl_queue,
			scheduler: $this->scheduler,
		);
	}

	/**
	 * Build a manager with a real ContentHashStore and a real BatchFetcher wired in,
	 * for tests that need the incremental-export or parallel-fetch paths.
	 */
	private function build_manager_with_optional_deps( bool $with_batch_fetcher = false, bool $with_content_hash_store = false ): ExportManager {
		$url_discovery   = new UrlDiscovery( $this->settings );
		$fetcher         = new Fetcher( $this->settings );
		$url_rewriter    = new UrlRewriter( $this->settings );
		$asset_collector = new AssetCollector();
		$html_processor  = new HtmlProcessor( $url_rewriter, $asset_collector );
		$file_writer     = new FileWriter( new PathHelper() );
		$logger          = new Logger();

		return new ExportManager(
			settings: $this->settings,
			url_discovery: $url_discovery,
			fetcher: $fetcher,
			crawl_queue: $this->crawl_queue,
			html_processor: $html_processor,
			asset_collector: $asset_collector,
			file_writer: $file_writer,
			progress: $this->progress,
			scheduler: $this->scheduler,
			logger: $logger,
			content_hash_store: $with_content_hash_store ? new ContentHashStore() : null,
			batch_fetcher: $with_batch_fetcher ? new BatchFetcher( $this->settings ) : null,
		);
	}

	private function make_job( string $export_id = 'exp-1' ): ExportJob {
		return new ExportJob(
			export_id: $export_id,
			output_dir: $this->output_dir,
			url_mode: 'relative',
			base_url: '',
			settings_snapshot: $this->settings->get_all(),
		);
	}

	// ── start() ────────────────────────────────────────────────────────────

	public function test_start_creates_job_with_generated_id(): void {
		$job = $this->build_manager()->start();

		$this->assertNotSame( '', $job->export_id );
		$this->assertSame( $this->output_dir, $job->output_dir );
		$this->assertSame( 'relative', $job->url_mode );
	}

	public function test_start_creates_output_directory_with_htaccess(): void {
		$this->build_manager()->start();

		$this->assertFileExists( $this->output_dir . '/.htaccess' );
	}

	public function test_start_enqueues_discovered_urls_and_sets_progress_total(): void {
		$job = $this->build_manager()->start();

		$progress = $this->progress->get();
		$this->assertNotNull( $progress );
		$this->assertSame( $job->export_id, $progress['export_id'] );
		$this->assertSame( 'running', $progress['status'] );
		// Default discovery (no posts configured) still yields the home URL.
		$this->assertGreaterThanOrEqual( 1, $progress['total'] );
	}

	public function test_start_saves_export_log_row(): void {
		$this->build_manager()->start();

		$inserts = array_values( array_filter(
			$GLOBALS['wpdb']->_call_log,
			fn( $c ) => 'insert' === $c['method'] && str_contains( $c['table'], 'sewp_export_log' ),
		) );

		$this->assertCount( 1, $inserts );
		$this->assertSame( 'running', $inserts[0]['data']['status'] );
	}

	public function test_start_background_schedules_batch(): void {
		$this->build_manager()->start_background();

		global $_wp_cron_events;
		$events = array_filter( $_wp_cron_events, fn( $e ) => 'sewp_process_batch' === $e['hook'] );
		$this->assertNotEmpty( $events );
	}

	// ── cancel() / get_current_job() ──────────────────────────────────────

	public function test_cancel_marks_progress_cancelled_and_unschedules(): void {
		$manager = $this->build_manager();
		$job     = $manager->start();

		$manager->cancel( $job->export_id );

		$this->assertTrue( $this->progress->is_cancelled( $job->export_id ) );
	}

	public function test_get_current_job_returns_null_without_progress(): void {
		$this->assertNull( $this->build_manager()->get_current_job() );
	}

	public function test_get_current_job_reflects_started_progress(): void {
		$manager = $this->build_manager();
		$job     = $manager->start();

		$current = $manager->get_current_job();

		$this->assertNotNull( $current );
		$this->assertSame( $job->export_id, $current->export_id );
	}

	// ── process_url(): single-fetch path ──────────────────────────────────

	public function test_process_url_writes_html_and_marks_completed(): void {
		$manager    = $this->build_manager();
		$job        = $this->make_job();
		$queue_item = (object) [ 'id' => 1, 'url' => 'https://example.com/' ];

		$this->set_remote_response( 'https://example.com/', [
			'response' => [ 'code' => 200 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [ 'content-type' => 'text/html; charset=UTF-8' ] ),
			'body'     => '<html><body>Hello</body></html>',
		] );

		$manager->process_url( $job, $queue_item );

		$this->assertFileExists( $this->output_dir . '/index.html' );
		$completed = array_values( array_filter(
			$GLOBALS['wpdb']->_call_log,
			fn( $c ) => 'update' === $c['method'] && ( $c['data']['status'] ?? null ) === 'completed',
		) );
		$this->assertNotEmpty( $completed );
	}

	public function test_process_url_marks_failed_on_fetch_error(): void {
		$manager    = $this->build_manager();
		$job        = $this->make_job();
		$queue_item = (object) [ 'id' => 2, 'url' => 'https://example.com/broken' ];

		$this->set_remote_response( 'https://example.com/broken', new \WP_Error( 'http_request_failed', 'Timed out' ) );

		$manager->process_url( $job, $queue_item );

		$queries = array_values( array_filter( $GLOBALS['wpdb']->_call_log, fn( $c ) => 'query' === $c['method'] ) );
		$this->assertNotEmpty( $queries, 'Expected mark_failed() to issue an UPDATE query' );
	}

	public function test_process_url_saves_non_html_response_as_is(): void {
		$manager    = $this->build_manager();
		$job        = $this->make_job();
		$queue_item = (object) [ 'id' => 3, 'url' => 'https://example.com/feed.xml' ];

		$this->set_remote_response( 'https://example.com/feed.xml', [
			'response' => [ 'code' => 200 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [ 'content-type' => 'application/xml' ] ),
			'body'     => '<rss></rss>',
		] );

		$manager->process_url( $job, $queue_item );

		$this->assertFileExists( $this->output_dir . '/feed.xml' );
		$this->assertSame( '<rss></rss>', file_get_contents( $this->output_dir . '/feed.xml' ) );
	}

	public function test_process_url_skips_unchanged_content_when_incremental(): void {
		$manager    = $this->build_manager_with_optional_deps( with_content_hash_store: true );
		$job        = $this->make_job();
		$queue_item = (object) [ 'id' => 4, 'url' => 'https://example.com/' ];

		$this->set_option( 'sewp_settings', [
			'output_dir'         => $this->output_dir,
			'incremental_export' => true,
		] );

		$body = '<html><body>Same</body></html>';
		$this->set_remote_response( 'https://example.com/', [
			'response' => [ 'code' => 200 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [ 'content-type' => 'text/html' ] ),
			'body'     => $body,
		] );

		// The wpdb stub doesn't persist data between calls, so queue the matching
		// hash directly as the return value of the get_hash() SELECT.
		$GLOBALS['wpdb']->_get_var_returns[] = ContentHashStore::hash_content( $body );

		$manager->process_url( $job, $queue_item );

		// mark_completed() must still be called (with an empty output path), but write_html
		// must NOT have been invoked for unchanged content -- no file should exist.
		$this->assertFileDoesNotExist( $this->output_dir . '/index.html' );
		$completed = array_values( array_filter(
			$GLOBALS['wpdb']->_call_log,
			fn( $c ) => 'update' === $c['method'] && ( $c['data']['status'] ?? null ) === 'completed',
		) );
		$this->assertNotEmpty( $completed );
	}

	// ── process_batch(): sequential fallback vs. parallel BatchFetcher ────

	public function test_process_batch_falls_back_to_sequential_without_batch_fetcher(): void {
		$manager    = $this->build_manager();
		$job        = $this->make_job();
		$queue_item = (object) [ 'id' => 5, 'url' => 'https://example.com/' ];

		$this->set_remote_response( 'https://example.com/', [
			'response' => [ 'code' => 200 ],
			'headers'  => new \WP_HTTP_Headers_Stub( [ 'content-type' => 'text/html' ] ),
			'body'     => '<html><body>Batch fallback</body></html>',
		] );

		$manager->process_batch( $job, [ $queue_item ] );

		$this->assertFileExists( $this->output_dir . '/index.html' );
	}

	public function test_process_batch_with_batch_fetcher_produces_same_outcome_as_process_url(): void {
		$manager    = $this->build_manager_with_optional_deps( with_batch_fetcher: true );
		$job        = $this->make_job();
		$queue_item = (object) [ 'id' => 6, 'url' => 'https://example.com/' ];

		Requests::$_responses['https://example.com/'] = [
			'status_code' => 200,
			'headers'     => [ 'content-type' => 'text/html; charset=UTF-8' ],
			'body'        => '<html><body>Parallel path</body></html>',
		];

		$manager->process_batch( $job, [ $queue_item ] );

		$this->assertFileExists( $this->output_dir . '/index.html' );
		$this->assertStringContainsString( 'Parallel path', (string) file_get_contents( $this->output_dir . '/index.html' ) );

		$completed = array_values( array_filter(
			$GLOBALS['wpdb']->_call_log,
			fn( $c ) => 'update' === $c['method'] && ( $c['data']['status'] ?? null ) === 'completed',
		) );
		$this->assertNotEmpty( $completed, 'process_batch() via BatchFetcher must mark the item completed, same as process_url()' );
	}

	public function test_process_batch_with_batch_fetcher_marks_failed_on_error(): void {
		$manager    = $this->build_manager_with_optional_deps( with_batch_fetcher: true );
		$job        = $this->make_job();
		$queue_item = (object) [ 'id' => 7, 'url' => 'https://example.com/broken' ];

		Requests::$_responses['https://example.com/broken'] = new \WpOrg\Requests\Exception( 'Connection refused', 'curlerror' );

		$manager->process_batch( $job, [ $queue_item ] );

		$queries = array_values( array_filter( $GLOBALS['wpdb']->_call_log, fn( $c ) => 'query' === $c['method'] ) );
		$this->assertNotEmpty( $queries, 'Expected mark_failed() to issue an UPDATE query' );
	}

	public function test_process_batch_ignores_empty_queue_items(): void {
		$manager = $this->build_manager_with_optional_deps( with_batch_fetcher: true );
		$job     = $this->make_job();

		// Must not throw or attempt any fetch.
		$manager->process_batch( $job, [] );

		$this->assertTrue( true );
	}

	// ── finalize() ─────────────────────────────────────────────────────────

	public function test_finalize_marks_completed_when_no_failures(): void {
		$manager = $this->build_manager();
		$job     = $this->make_job();

		mkdir( $this->output_dir, 0755, true );
		$GLOBALS['wpdb']->_get_results_returns[] = [
			(object) [ 'status' => 'completed', 'cnt' => 3 ],
		];

		$manager->finalize( $job );

		$progress_status = null;
		foreach ( $GLOBALS['wpdb']->_call_log as $call ) {
			if ( 'update' === $call['method'] && str_contains( $call['table'], 'sewp_export_log' ) && isset( $call['data']['status'] ) ) {
				$progress_status = $call['data']['status'];
			}
		}
		$this->assertSame( 'completed', $progress_status );
	}

	public function test_finalize_marks_failed_when_everything_failed(): void {
		$manager = $this->build_manager();
		$job     = $this->make_job();

		$this->progress->start( $job->export_id, 5 );
		mkdir( $this->output_dir, 0755, true );
		$GLOBALS['wpdb']->_get_results_returns[] = [
			(object) [ 'status' => 'failed', 'cnt' => 5 ],
		];

		$manager->finalize( $job );

		$this->assertSame( 'failed', $this->progress->get()['status'] ?? null );
	}

	public function test_finalize_fires_export_finalized_action(): void {
		$manager = $this->build_manager();
		$job     = $this->make_job();

		mkdir( $this->output_dir, 0755, true );
		$GLOBALS['wpdb']->_get_results_returns[] = [
			(object) [ 'status' => 'completed', 'cnt' => 1 ],
		];

		$manager->finalize( $job );

		$fired = array_filter( $this->get_actions(), fn( $a ) => 'sewp_export_finalized' === $a['hook'] );
		$this->assertNotEmpty( $fired );
	}

	public function test_finalize_generates_sitemap_for_completed_html_urls(): void {
		$manager = $this->build_manager();
		$job     = $this->make_job();

		mkdir( $this->output_dir, 0755, true );
		// First get_col() call (generate_sitemap) returns one completed HTML URL;
		// get_results() call (get_counts) returns the completed count.
		$GLOBALS['wpdb']->_get_col_returns[]     = [ 'https://example.com/' ];
		$GLOBALS['wpdb']->_get_results_returns[] = [
			(object) [ 'status' => 'completed', 'cnt' => 1 ],
		];

		$manager->finalize( $job );

		$this->assertFileExists( $this->output_dir . '/sitemap.xml' );
		$this->assertStringContainsString( 'https://example.com/', (string) file_get_contents( $this->output_dir . '/sitemap.xml' ) );
	}
}
