<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Export\ContentHashStore;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class ContentHashStoreTest extends TestCase {

	use WpStubHelpers;

	private ContentHashStore $store;
	private \wpdb $wpdb;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->wpdb      = new \wpdb();
		$GLOBALS['wpdb'] = $this->wpdb;
		$this->store     = new ContentHashStore();
	}

	// ── hash_content (pure, no DB) ─────────────────────────────────────────

	public function test_hash_content_returns_sha256(): void {
		$hash = ContentHashStore::hash_content( 'hello world' );

		$this->assertSame( hash( 'sha256', 'hello world' ), $hash );
		$this->assertSame( 64, strlen( $hash ) );
	}

	public function test_same_input_same_hash(): void {
		$hash1 = ContentHashStore::hash_content( 'test content' );
		$hash2 = ContentHashStore::hash_content( 'test content' );

		$this->assertSame( $hash1, $hash2 );
	}

	public function test_different_input_different_hash(): void {
		$hash1 = ContentHashStore::hash_content( 'content A' );
		$hash2 = ContentHashStore::hash_content( 'content B' );

		$this->assertNotSame( $hash1, $hash2 );
	}

	// ── get_hash (DB-backed) ───────────────────────────────────────────────

	/**
	 * Issue 4: get_hash must return null for an unknown URL so that the
	 * incremental-export path never incorrectly skips a first-time crawl.
	 */
	public function test_get_hash_returns_null_when_url_not_found(): void {
		// wpdb stub returns null by default.
		$result = $this->store->get_hash( 'https://example.com/new-page/' );

		$this->assertNull( $result );
	}

	public function test_get_hash_returns_stored_hash(): void {
		$expected = hash( 'sha256', 'page content' );
		$this->wpdb->_get_var_returns = [ $expected ];

		$result = $this->store->get_hash( 'https://example.com/about/' );

		$this->assertSame( $expected, $result );
	}

	public function test_get_hash_queries_by_url_hash_not_raw_url(): void {
		$this->store->get_hash( 'https://example.com/about/' );

		$var_calls = array_filter( $this->wpdb->_call_log, fn( $c ) => $c['method'] === 'get_var' );
		$this->assertNotEmpty( $var_calls );

		$sql = array_values( $var_calls )[0]['query'];
		// The raw URL must NOT appear in the query — only its SHA256 hash.
		$this->assertStringNotContainsString( 'https://example.com/about/', $sql );
		$this->assertStringContainsString( hash( 'sha256', 'https://example.com/about/' ), $sql );
	}

	// ── store_hash (DB-backed) ─────────────────────────────────────────────

	public function test_store_hash_issues_upsert_query(): void {
		$this->store->store_hash(
			'https://example.com/about/',
			hash( 'sha256', 'content' ),
			'about/index.html',
			'exp-123',
		);

		$query_calls = array_filter( $this->wpdb->_call_log, fn( $c ) => $c['method'] === 'query' );
		$this->assertNotEmpty( $query_calls );

		$sql = array_values( $query_calls )[0]['query'];
		$this->assertStringContainsString( 'INSERT', strtoupper( $sql ) );
		$this->assertStringContainsString( 'ON DUPLICATE KEY UPDATE', strtoupper( $sql ) );
	}

	public function test_store_hash_uses_sha256_of_url_as_key(): void {
		$url = 'https://example.com/contact/';
		$this->store->store_hash( $url, hash( 'sha256', 'body' ), 'contact/index.html', 'exp-1' );

		$query_calls = array_filter( $this->wpdb->_call_log, fn( $c ) => $c['method'] === 'query' );
		$sql         = array_values( $query_calls )[0]['query'];

		$this->assertStringContainsString( hash( 'sha256', $url ), $sql );
	}

	// ── Incremental export skip logic ──────────────────────────────────────

	/**
	 * Issue 4: when the same content is fetched twice, get_hash returns the
	 * previously stored hash, which must equal the new hash so the page is skipped.
	 */
	public function test_unchanged_content_produces_equal_hashes(): void {
		$content      = '<html><body>Stable content</body></html>';
		$hash_first   = ContentHashStore::hash_content( $content );

		// Simulate content unchanged on second fetch.
		$hash_second  = ContentHashStore::hash_content( $content );

		$this->assertSame( $hash_first, $hash_second,
			'Identical content must produce equal hashes so incremental export can skip it' );
	}

	public function test_changed_content_produces_different_hashes(): void {
		$hash_v1 = ContentHashStore::hash_content( '<html><body>Version 1</body></html>' );
		$hash_v2 = ContentHashStore::hash_content( '<html><body>Version 2</body></html>' );

		$this->assertNotSame( $hash_v1, $hash_v2,
			'Changed content must produce a different hash so incremental export re-exports it' );
	}
}
