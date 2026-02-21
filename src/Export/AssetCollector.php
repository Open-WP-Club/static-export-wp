<?php

declare(strict_types=1);

namespace StaticExportWP\Export;

final class AssetCollector {

	/**
	 * Extract asset URLs from HTML content.
	 *
	 * @param string $html         The HTML content to analyze.
	 * @param string $site_url     The site URL for filtering.
	 * @return string[] Array of asset URLs.
	 */
	public function collect_from_html( string $html, string $site_url ): array {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		return $this->collect_from_doc( $doc, $site_url, $html );
	}

	/**
	 * Extract asset URLs from a pre-parsed DOMDocument.
	 *
	 * @param \DOMDocument $doc      The parsed document.
	 * @param string       $site_url The site URL for filtering.
	 * @param string       $html     Original HTML for inline CSS url() extraction.
	 * @return string[] Array of asset URLs.
	 */
	public function collect_from_doc( \DOMDocument $doc, string $site_url, string $html = '' ): array {
		$assets = [];

		$site_url = untrailingslashit( $site_url );

		// <link href="..."> (stylesheets, icons, etc.)
		foreach ( $doc->getElementsByTagName( 'link' ) as $el ) {
			$href = $el->getAttribute( 'href' );
			if ( $href ) {
				$assets[] = $href;
			}
		}

		// <script src="...">
		foreach ( $doc->getElementsByTagName( 'script' ) as $el ) {
			$src = $el->getAttribute( 'src' );
			if ( $src ) {
				$assets[] = $src;
			}
		}

		// <img src="..." srcset="...">
		foreach ( $doc->getElementsByTagName( 'img' ) as $el ) {
			$src = $el->getAttribute( 'src' );
			if ( $src ) {
				$assets[] = $src;
			}
			$srcset = $el->getAttribute( 'srcset' );
			if ( $srcset ) {
				$assets = array_merge( $assets, $this->parse_srcset( $srcset ) );
			}
		}

		// <source src="..." srcset="...">
		foreach ( $doc->getElementsByTagName( 'source' ) as $el ) {
			$src = $el->getAttribute( 'src' );
			if ( $src ) {
				$assets[] = $src;
			}
			$srcset = $el->getAttribute( 'srcset' );
			if ( $srcset ) {
				$assets = array_merge( $assets, $this->parse_srcset( $srcset ) );
			}
		}

		// <video src="..." poster="...">
		foreach ( $doc->getElementsByTagName( 'video' ) as $el ) {
			$src = $el->getAttribute( 'src' );
			if ( $src ) {
				$assets[] = $src;
			}
			$poster = $el->getAttribute( 'poster' );
			if ( $poster ) {
				$assets[] = $poster;
			}
		}

		// Extract url() references from inline styles.
		if ( '' === $html ) {
			$html = $doc->saveHTML() ?: '';
		}
		$assets = array_merge( $assets, $this->extract_css_urls( $html ) );

		// Filter and normalize.
		return $this->filter_assets( $assets, $site_url );
	}

	/**
	 * Extract asset URLs from a CSS file body.
	 *
	 * @return string[]
	 */
	public function collect_from_css( string $css, string $css_url, string $site_url ): array {
		$assets  = [];
		$css_dir = dirname( $css_url ) . '/';

		if ( preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $css, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				if ( str_starts_with( $url, 'data:' ) ) {
					continue;
				}

				// Resolve relative URLs against the CSS file's directory.
				if ( ! str_starts_with( $url, 'http' ) && ! str_starts_with( $url, '//' ) ) {
					$url = $css_dir . $url;
				}

				$assets[] = $url;
			}
		}

		return $this->filter_assets( $assets, $site_url );
	}

	/**
	 * Parse srcset attribute values.
	 *
	 * @return string[]
	 */
	private function parse_srcset( string $srcset ): array {
		$urls = [];
		foreach ( explode( ',', $srcset ) as $entry ) {
			$parts = preg_split( '/\s+/', trim( $entry ) );
			if ( ! empty( $parts[0] ) ) {
				$urls[] = $parts[0];
			}
		}
		return $urls;
	}

	/**
	 * Extract url() from inline CSS in HTML.
	 *
	 * @return string[]
	 */
	private function extract_css_urls( string $html ): array {
		$urls = [];
		if ( preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $html, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				if ( ! str_starts_with( $url, 'data:' ) ) {
					$urls[] = $url;
				}
			}
		}
		return $urls;
	}

	/**
	 * Filter to only site assets (not external CDN, etc.).
	 *
	 * @param string[] $assets
	 * @return string[]
	 */
	private function filter_assets( array $assets, string $site_url ): array {
		$site_url = untrailingslashit( $site_url );
		$result   = [];
		$seen     = [];

		foreach ( $assets as $url ) {
			// Make absolute.
			if ( str_starts_with( $url, '//' ) ) {
				$url = 'https:' . $url;
			} elseif ( str_starts_with( $url, '/' ) ) {
				$url = $site_url . $url;
			}

			// Strip query strings and fragments for dedup.
			$clean = strtok( $url, '?#' ) ?: $url;

			if ( ! str_starts_with( $clean, $site_url ) ) {
				continue;
			}

			if ( isset( $seen[ $clean ] ) ) {
				continue;
			}

			$seen[ $clean ] = true;
			$result[]       = $clean;
		}

		return $result;
	}
}
