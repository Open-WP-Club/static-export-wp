<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\UrlDiscovery;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class UrlDiscoveryTest extends TestCase {

	use WpStubHelpers;

	private Settings $settings;
	private UrlDiscovery $discovery;

	protected function setUp(): void {
		$this->reset_wp_state();

		$GLOBALS['wpdb'] = new \wpdb();

		global $_wp_query_posts_by_type, $_wp_taxonomies, $_wp_terms_by_taxonomy,
			$_wp_archive_post_types, $_wp_users;
		$_wp_query_posts_by_type = [];
		$_wp_taxonomies          = [];
		$_wp_terms_by_taxonomy   = [];
		$_wp_archive_post_types  = [];
		$_wp_users               = [];

		$this->settings  = new Settings();
		$this->discovery = new UrlDiscovery( $this->settings );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
	}

	public function test_discover_always_includes_home_url(): void {
		$urls = $this->discovery->discover();

		$this->assertContains( 'https://example.com/', $urls );
	}

	public function test_discover_includes_urls_for_configured_post_types(): void {
		global $_wp_query_posts_by_type;
		$_wp_query_posts_by_type = [
			'post' => [ 1, 2 ],
			'page' => [ 10 ],
		];

		$this->set_option( 'sewp_settings', [
			'post_types' => [ 'post', 'page' ],
		] );

		$urls = $this->discovery->discover();

		$this->assertContains( 'https://example.com/?p=1', $urls );
		$this->assertContains( 'https://example.com/?p=2', $urls );
		$this->assertContains( 'https://example.com/?p=10', $urls );
	}

	public function test_discover_only_queries_configured_post_types(): void {
		global $_wp_query_posts_by_type;
		$_wp_query_posts_by_type = [
			'post'       => [ 1 ],
			'attachment' => [ 99 ],
		];

		$this->set_option( 'sewp_settings', [
			'post_types' => [ 'post' ],
		] );

		$urls = $this->discovery->discover();

		$this->assertContains( 'https://example.com/?p=1', $urls );
		$this->assertNotContains( 'https://example.com/?p=99', $urls );
	}

	public function test_discover_includes_taxonomy_term_urls(): void {
		global $_wp_taxonomies, $_wp_terms_by_taxonomy;
		$_wp_taxonomies        = [ 'category' ];
		$_wp_terms_by_taxonomy = [ 'category' => [ 5 ] ];

		$urls = $this->discovery->discover();

		$this->assertContains( 'https://example.com/term/5/', $urls );
	}

	public function test_discover_includes_post_type_archive_urls(): void {
		global $_wp_archive_post_types;
		$_wp_archive_post_types = [ 'product' ];

		$urls = $this->discovery->discover();

		$this->assertContains( 'https://example.com/product/', $urls );
	}

	public function test_discover_includes_author_archive_urls(): void {
		global $_wp_users;
		$_wp_users = [ 3 ];

		$urls = $this->discovery->discover();

		$this->assertContains( 'https://example.com/author/3/', $urls );
	}

	public function test_discover_deduplicates_case_and_trailing_slash_variants(): void {
		global $_wp_query_posts_by_type;
		// A permalink pointing at the same page as another discovered URL.
		$_wp_query_posts_by_type = [ 'post' => [ 1 ] ];

		$this->set_option( 'sewp_settings', [
			'post_types' => [ 'post' ],
			'extra_urls' => [ 'https://example.com/?p=1' ],
		] );

		$urls   = $this->discovery->discover();
		$counts = array_count_values( array_map( 'strtolower', $urls ) );

		$this->assertSame( 1, $counts[ 'https://example.com/?p=1' ] );
	}

	public function test_discover_extra_urls_restricted_to_current_site(): void {
		$this->set_option( 'sewp_settings', [
			'extra_urls' => [ 'https://example.com/special/', 'https://evil.com/hack/' ],
		] );

		$urls = $this->discovery->discover();

		$this->assertContains( 'https://example.com/special/', $urls );
		$this->assertNotContains( 'https://evil.com/hack/', $urls );
	}

	public function test_discover_applies_exclude_patterns(): void {
		$this->set_option( 'sewp_settings', [
			'extra_urls'       => [ 'https://example.com/private/', 'https://example.com/public/' ],
			'exclude_patterns' => [ '*/private/*' ],
		] );

		$urls = $this->discovery->discover();

		$this->assertNotContains( 'https://example.com/private/', $urls );
		$this->assertContains( 'https://example.com/public/', $urls );
	}

	public function test_discover_selective_mode_returns_only_selected_urls(): void {
		$this->set_option( 'sewp_settings', [
			'export_mode'   => 'selective',
			'selected_urls' => [ 'https://example.com/one/', 'https://example.com/two/' ],
		] );

		global $_wp_query_posts_by_type;
		$_wp_query_posts_by_type = [ 'post' => [ 1 ] ];

		$urls = $this->discovery->discover();

		$this->assertSame( [ 'https://example.com/one/', 'https://example.com/two/' ], $urls );
	}

	public function test_discover_selective_mode_falls_back_to_full_when_no_urls_selected(): void {
		$this->set_option( 'sewp_settings', [
			'export_mode'   => 'selective',
			'selected_urls' => [],
		] );

		$urls = $this->discovery->discover();

		// Falls back to full discovery, which always includes the home URL.
		$this->assertContains( 'https://example.com/', $urls );
	}

	public function test_discover_applies_sewp_discovered_urls_filter(): void {
		add_filter( 'sewp_discovered_urls', function ( array $urls ): array {
			$urls[] = 'https://example.com/injected/';
			return $urls;
		} );

		$urls = $this->discovery->discover();

		$this->assertContains( 'https://example.com/injected/', $urls );
	}
}
