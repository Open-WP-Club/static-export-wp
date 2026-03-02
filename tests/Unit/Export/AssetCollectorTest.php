<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Export\AssetCollector;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class AssetCollectorTest extends TestCase {

	use WpStubHelpers;

	private AssetCollector $collector;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->collector = new AssetCollector();
	}

	public function test_extracts_link_href(): void {
		$html   = '<html><head><link rel="stylesheet" href="/wp-content/style.css"></head><body></body></html>';
		$assets = $this->collector->collect_from_html( $html, 'https://example.com' );

		$this->assertContains( 'https://example.com/wp-content/style.css', $assets );
	}

	public function test_extracts_script_src(): void {
		$html   = '<html><head><script src="/wp-includes/js/jquery.js"></script></head><body></body></html>';
		$assets = $this->collector->collect_from_html( $html, 'https://example.com' );

		$this->assertContains( 'https://example.com/wp-includes/js/jquery.js', $assets );
	}

	public function test_extracts_img_src(): void {
		$html   = '<html><body><img src="/wp-content/uploads/photo.jpg"></body></html>';
		$assets = $this->collector->collect_from_html( $html, 'https://example.com' );

		$this->assertContains( 'https://example.com/wp-content/uploads/photo.jpg', $assets );
	}

	public function test_extracts_srcset(): void {
		$html   = '<html><body><img srcset="/img/small.jpg 300w, /img/large.jpg 1024w"></body></html>';
		$assets = $this->collector->collect_from_html( $html, 'https://example.com' );

		$this->assertContains( 'https://example.com/img/small.jpg', $assets );
		$this->assertContains( 'https://example.com/img/large.jpg', $assets );
	}

	public function test_extracts_source_element(): void {
		$html   = '<html><body><picture><source srcset="/img/hero.webp"></picture></body></html>';
		$assets = $this->collector->collect_from_html( $html, 'https://example.com' );

		$this->assertContains( 'https://example.com/img/hero.webp', $assets );
	}

	public function test_extracts_video_poster(): void {
		$html   = '<html><body><video src="/media/video.mp4" poster="/media/poster.jpg"></video></body></html>';
		$assets = $this->collector->collect_from_html( $html, 'https://example.com' );

		$this->assertContains( 'https://example.com/media/video.mp4', $assets );
		$this->assertContains( 'https://example.com/media/poster.jpg', $assets );
	}

	public function test_extracts_inline_css_urls(): void {
		$html   = '<html><body><div style="background: url(\'/images/bg.jpg\')"></div></body></html>';
		$assets = $this->collector->collect_from_html( $html, 'https://example.com' );

		$this->assertContains( 'https://example.com/images/bg.jpg', $assets );
	}

	public function test_skips_data_uris(): void {
		$html   = '<html><body><img src="data:image/png;base64,abc123"></body></html>';
		$assets = $this->collector->collect_from_html( $html, 'https://example.com' );

		$this->assertEmpty( $assets );
	}

	public function test_filters_external_urls(): void {
		$html   = '<html><head><script src="https://cdn.external.com/lib.js"></script></head><body></body></html>';
		$assets = $this->collector->collect_from_html( $html, 'https://example.com' );

		$this->assertEmpty( $assets );
	}

	public function test_deduplicates(): void {
		$html   = '<html><body><img src="/img/photo.jpg"><img src="/img/photo.jpg"></body></html>';
		$assets = $this->collector->collect_from_html( $html, 'https://example.com' );

		$this->assertCount( 1, $assets );
	}

	public function test_handles_protocol_relative(): void {
		$html   = '<html><body><img src="//example.com/img/photo.jpg"></body></html>';
		$assets = $this->collector->collect_from_html( $html, 'https://example.com' );

		$this->assertContains( 'https://example.com/img/photo.jpg', $assets );
	}

	// CSS collection tests.

	public function test_css_extracts_url(): void {
		$css    = "body { background: url('images/bg.jpg'); }";
		$assets = $this->collector->collect_from_css( $css, 'https://example.com/wp-content/style.css', 'https://example.com' );

		$this->assertContains( 'https://example.com/wp-content/images/bg.jpg', $assets );
	}

	public function test_css_resolves_relative_urls(): void {
		$css    = ".icon { background: url('images/icon.png'); }";
		$assets = $this->collector->collect_from_css( $css, 'https://example.com/wp-content/themes/style.css', 'https://example.com' );

		$this->assertContains( 'https://example.com/wp-content/themes/images/icon.png', $assets );
	}

	public function test_css_skips_data_uris(): void {
		$css    = "body { background: url('data:image/svg+xml;base64,abc'); }";
		$assets = $this->collector->collect_from_css( $css, 'https://example.com/style.css', 'https://example.com' );

		$this->assertEmpty( $assets );
	}
}
