<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class CrawlQueueTest extends TestCase {

	use WpStubHelpers;

	private CrawlQueue $queue;
	private \wpdb $wpdb;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->wpdb           = new \wpdb();
		$GLOBALS['wpdb']      = $this->wpdb;
		$this->queue          = new CrawlQueue();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
	}

	public function test_enqueue_returns_zero_for_empty(): void {
		$result = $this->queue->enqueue( 'exp-1', [] );
		$this->assertSame( 0, $result );
	}

	public function test_enqueue_calls_wpdb_with_correct_sql(): void {
		$this->wpdb->_query_returns = [ 2 ];

		$result = $this->queue->enqueue( 'exp-1', [ 'https://example.com/', 'https://example.com/about/' ] );

		$this->assertSame( 2, $result );
		$calls = array_filter( $this->wpdb->_call_log, fn( $c ) => $c['method'] === 'query' );
		$this->assertNotEmpty( $calls );
	}

	public function test_enqueue_chunks_large_batches(): void {
		// Create 250 URLs to force chunking (chunk_size = 100).
		$urls = [];
		for ( $i = 0; $i < 250; $i++ ) {
			$urls[] = "https://example.com/page-{$i}/";
		}

		$this->wpdb->_query_returns = [ 100, 100, 50 ];

		$result = $this->queue->enqueue( 'exp-1', $urls );

		$this->assertSame( 250, $result );
		$query_calls = array_filter( $this->wpdb->_call_log, fn( $c ) => $c['method'] === 'query' );
		$this->assertCount( 3, $query_calls );
	}

	public function test_get_next_batch_returns_rows_and_marks_processing(): void {
		$rows = [
			(object) [ 'id' => 1, 'url' => 'https://example.com/', 'status' => 'pending' ],
			(object) [ 'id' => 2, 'url' => 'https://example.com/about/', 'status' => 'pending' ],
		];
		$this->wpdb->_get_results_returns = [ $rows ];

		$batch = $this->queue->get_next_batch( 'exp-1', 10 );

		$this->assertCount( 2, $batch );
		// Verify an UPDATE query was made to mark as processing.
		$query_calls = array_filter( $this->wpdb->_call_log, fn( $c ) => $c['method'] === 'query' );
		$this->assertNotEmpty( $query_calls );
	}

	public function test_mark_completed(): void {
		$this->queue->mark_completed( 1, 200, 'text/html', '/output/index.html' );

		$update_calls = array_filter( $this->wpdb->_call_log, fn( $c ) => $c['method'] === 'update' );
		$this->assertCount( 1, $update_calls );
		$call = array_values( $update_calls )[0];
		$this->assertSame( 'completed', $call['data']['status'] );
	}

	public function test_mark_failed(): void {
		$this->queue->mark_failed( 1, 'Connection timeout', 0 );

		$query_calls = array_filter( $this->wpdb->_call_log, fn( $c ) => $c['method'] === 'query' );
		$this->assertNotEmpty( $query_calls );
	}

	public function test_retry_failed(): void {
		$this->wpdb->_query_returns = [ 3 ];

		$count = $this->queue->retry_failed( 'exp-1', 3 );

		$this->assertSame( 3, $count );
	}

	public function test_get_counts(): void {
		$this->wpdb->_get_results_returns = [ [
			(object) [ 'status' => 'completed', 'cnt' => '50' ],
			(object) [ 'status' => 'failed', 'cnt' => '5' ],
			(object) [ 'status' => 'pending', 'cnt' => '10' ],
		] ];

		$counts = $this->queue->get_counts( 'exp-1' );

		$this->assertSame( 50, $counts['completed'] );
		$this->assertSame( 5, $counts['failed'] );
		$this->assertSame( 10, $counts['pending'] );
		$this->assertSame( 65, $counts['total'] );
	}

	public function test_has_pending_true(): void {
		$this->wpdb->_get_var_returns = [ '5' ];

		$this->assertTrue( $this->queue->has_pending( 'exp-1' ) );
	}

	public function test_has_pending_false(): void {
		$this->wpdb->_get_var_returns = [ '0' ];

		$this->assertFalse( $this->queue->has_pending( 'exp-1' ) );
	}

	public function test_get_broken_links(): void {
		$links = [
			(object) [ 'url' => 'https://example.com/missing', 'http_status' => 404, 'error_message' => 'Not Found', 'referrer' => 'https://example.com/' ],
		];
		$this->wpdb->_get_results_returns = [ $links ];

		$result = $this->queue->get_broken_links( 'exp-1' );

		$this->assertCount( 1, $result );
		$this->assertSame( 'https://example.com/missing', $result[0]->url );
	}

	public function test_clear(): void {
		$this->queue->clear( 'exp-1' );

		$delete_calls = array_filter( $this->wpdb->_call_log, fn( $c ) => $c['method'] === 'delete' );
		$this->assertCount( 1, $delete_calls );
	}
}
