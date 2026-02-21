<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for HTML processing logic.
 *
 * These test the core HTML parsing/extraction patterns used by HtmlProcessor
 * without requiring a full WordPress environment.
 */
final class HtmlProcessorTest extends TestCase {

	public function test_extract_links_from_html(): void {
		$html = '<html><body>
			<a href="https://example.com/about/">About</a>
			<a href="https://example.com/contact/">Contact</a>
			<a href="https://external.com/">External</a>
		</body></html>';

		$links    = $this->extract_links( $html, 'https://example.com' );
		$expected = [
			'https://example.com/about/',
			'https://example.com/contact/',
		];

		$this->assertSame( $expected, $links );
	}

	public function test_extract_asset_urls(): void {
		$html = '<html><head>
			<link rel="stylesheet" href="/wp-content/themes/style.css">
			<script src="/wp-includes/js/jquery.js"></script>
		</head><body>
			<img src="/wp-content/uploads/photo.jpg">
		</body></html>';

		$assets = $this->extract_assets( $html );
		$this->assertCount( 3, $assets );
		$this->assertContains( '/wp-content/themes/style.css', $assets );
		$this->assertContains( '/wp-includes/js/jquery.js', $assets );
		$this->assertContains( '/wp-content/uploads/photo.jpg', $assets );
	}

	public function test_extract_srcset_urls(): void {
		$html = '<img srcset="/img/small.jpg 300w, /img/large.jpg 1024w">';

		$assets = $this->extract_srcset( $html );
		$this->assertContains( '/img/small.jpg', $assets );
		$this->assertContains( '/img/large.jpg', $assets );
	}

	public function test_extract_css_url_references(): void {
		$css = "body { background: url('/images/bg.jpg'); }
				.icon { background-image: url('/images/icon.svg'); }";

		$urls = $this->extract_css_urls( $css );
		$this->assertContains( '/images/bg.jpg', $urls );
		$this->assertContains( '/images/icon.svg', $urls );
	}

	public function test_skip_data_uris_in_css(): void {
		$css = "body { background: url('data:image/svg+xml;base64,abc123'); }";

		$urls = $this->extract_css_urls( $css );
		$this->assertEmpty( $urls );
	}

	/**
	 * Extract internal links from HTML.
	 */
	private function extract_links( string $html, string $site_url ): array {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$urls = [];
		foreach ( $doc->getElementsByTagName( 'a' ) as $el ) {
			$href = $el->getAttribute( 'href' );
			if ( str_starts_with( $href, $site_url ) ) {
				$urls[] = $href;
			}
		}

		return $urls;
	}

	/**
	 * Extract asset URLs from HTML.
	 */
	private function extract_assets( string $html ): array {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$assets = [];

		foreach ( $doc->getElementsByTagName( 'link' ) as $el ) {
			$href = $el->getAttribute( 'href' );
			if ( $href ) {
				$assets[] = $href;
			}
		}

		foreach ( $doc->getElementsByTagName( 'script' ) as $el ) {
			$src = $el->getAttribute( 'src' );
			if ( $src ) {
				$assets[] = $src;
			}
		}

		foreach ( $doc->getElementsByTagName( 'img' ) as $el ) {
			$src = $el->getAttribute( 'src' );
			if ( $src ) {
				$assets[] = $src;
			}
		}

		return $assets;
	}

	/**
	 * Extract URLs from srcset attributes.
	 */
	private function extract_srcset( string $html ): array {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<html><body>' . $html . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$urls = [];
		foreach ( $doc->getElementsByTagName( 'img' ) as $el ) {
			$srcset = $el->getAttribute( 'srcset' );
			if ( ! $srcset ) {
				continue;
			}
			foreach ( explode( ',', $srcset ) as $entry ) {
				$parts = preg_split( '/\s+/', trim( $entry ) );
				if ( ! empty( $parts[0] ) ) {
					$urls[] = $parts[0];
				}
			}
		}

		return $urls;
	}

	/**
	 * Extract url() references from CSS.
	 */
	private function extract_css_urls( string $css ): array {
		$urls = [];
		if ( preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $css, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				if ( ! str_starts_with( $url, 'data:' ) ) {
					$urls[] = $url;
				}
			}
		}
		return $urls;
	}
}
