<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Export\AssetCollector;
use StaticExportWP\Export\HtmlProcessor;
use StaticExportWP\Export\UrlRewriter;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class HtmlProcessorTest extends TestCase {

	use WpStubHelpers;

	private HtmlProcessor $processor;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->set_home_url( 'https://example.com' );

		// Real Settings backed by the WP options stub.
		$this->set_option( 'sewp_settings', [
			'url_mode' => 'relative',
			'base_url' => '',
		] );

		$settings        = new Settings();
		$url_rewriter    = new UrlRewriter( $settings );
		$asset_collector = new AssetCollector();

		$this->processor = new HtmlProcessor( $url_rewriter, $asset_collector );
	}

	public function test_process_returns_html_assets_discovered(): void {
		$html   = '<html><body><a href="https://example.com/about/">About</a></body></html>';
		$result = $this->processor->process( $html, 'https://example.com/', 'relative', '' );

		$this->assertArrayHasKey( 'html', $result );
		$this->assertArrayHasKey( 'assets', $result );
		$this->assertArrayHasKey( 'discovered_urls', $result );
	}

	public function test_rewrites_a_href(): void {
		$html   = '<html><body><a href="https://example.com/about/">About</a></body></html>';
		$result = $this->processor->process( $html, 'https://example.com/', 'relative', '' );

		// The rewritten URL should no longer contain the original absolute URL.
		$this->assertStringNotContainsString( 'https://example.com/about/', $result['html'] );
		// It should now contain a relative path ending in index.html.
		$this->assertStringContainsString( 'index.html', $result['html'] );
	}

	public function test_rewrites_link_href(): void {
		$html   = '<html><head><link rel="stylesheet" href="https://example.com/style.css"></head><body></body></html>';
		$result = $this->processor->process( $html, 'https://example.com/', 'relative', '' );

		// The original absolute URL should be rewritten.
		$this->assertStringNotContainsString( 'https://example.com/style.css', $result['html'] );
	}

	public function test_rewrites_script_src(): void {
		$html   = '<html><head><script src="https://example.com/app.js"></script></head><body></body></html>';
		$result = $this->processor->process( $html, 'https://example.com/', 'relative', '' );

		$this->assertStringNotContainsString( 'https://example.com/app.js', $result['html'] );
	}

	public function test_rewrites_img_src(): void {
		$html   = '<html><body><img src="https://example.com/photo.jpg"></body></html>';
		$result = $this->processor->process( $html, 'https://example.com/', 'relative', '' );

		$this->assertStringNotContainsString( 'https://example.com/photo.jpg', $result['html'] );
	}

	public function test_rewrites_srcset(): void {
		$html   = '<html><body><img srcset="https://example.com/small.jpg 300w, https://example.com/large.jpg 1024w"></body></html>';
		$result = $this->processor->process( $html, 'https://example.com/', 'relative', '' );

		$this->assertStringNotContainsString( 'https://example.com/small.jpg', $result['html'] );
		$this->assertStringNotContainsString( 'https://example.com/large.jpg', $result['html'] );
	}

	public function test_discovers_internal_links_only(): void {
		$html = '<html><body>
			<a href="https://example.com/about/">About</a>
			<a href="https://external.com/">External</a>
			<a href="https://example.com/wp-admin/">Admin</a>
		</body></html>';

		$result = $this->processor->process( $html, 'https://example.com/', 'relative', '' );

		$this->assertContains( 'https://example.com/about/', $result['discovered_urls'] );
		$this->assertNotContains( 'https://external.com/', $result['discovered_urls'] );
		$this->assertNotContains( 'https://example.com/wp-admin/', $result['discovered_urls'] );
	}

	public function test_filters_php_urls(): void {
		$html   = '<html><body><a href="https://example.com/wp-login.php">Login</a></body></html>';
		$result = $this->processor->process( $html, 'https://example.com/', 'relative', '' );

		$this->assertEmpty( $result['discovered_urls'] );
	}

	public function test_collects_assets(): void {
		$html = '<html><head><link rel="stylesheet" href="/style.css"></head><body>
			<img src="/photo.jpg">
		</body></html>';

		$result = $this->processor->process( $html, 'https://example.com/', 'relative', '' );

		$this->assertNotEmpty( $result['assets'] );
	}

	public function test_rewrites_form_action(): void {
		$html   = '<html><body><form action="https://example.com/search/"></form></body></html>';
		$result = $this->processor->process( $html, 'https://example.com/', 'relative', '' );

		$this->assertStringNotContainsString( 'https://example.com/search/', $result['html'] );
	}
}
