<?php
/**
 * Rewrites URLs found in exported HTML to relative or absolute static paths.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Export;

use StaticExportWP\Core\Settings;

/**
 * Rewrites in-site URLs found while processing HTML so they resolve correctly
 * within the static export, either as paths relative to the current page or
 * as absolute URLs against a configured base URL.
 */
final class UrlRewriter {

	/**
	 * The WordPress site URL (no trailing slash), used to identify in-site URLs.
	 *
	 * @var string
	 */
	private string $site_url;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings accessor.
	 */
	public function __construct(
		private readonly Settings $settings,
	) {
		$this->site_url = untrailingslashit( home_url() );
	}

	/**
	 * Rewrite a URL found in HTML.
	 *
	 * @param string $url             The original URL from the HTML.
	 * @param string $current_page_url The URL of the page being processed (for relative mode).
	 * @param string $url_mode        'relative' or 'absolute'.
	 * @param string $base_url        Custom base URL (for absolute mode).
	 * @return string Rewritten URL.
	 */
	public function rewrite( string $url, string $current_page_url, string $url_mode = '', string $base_url = '' ): string {
		if ( '' === $url_mode ) {
			$url_mode = $this->settings->get( 'url_mode', 'relative' );
		}

		if ( '' === $base_url ) {
			$base_url = $this->settings->get( 'base_url', '' );
		}

		// Skip external URLs, anchors, data URIs, protocol-relative.
		if ( $this->should_skip( $url ) ) {
			return $url;
		}

		// Make URL absolute if it's relative.
		$absolute_url = $this->make_absolute( $url );

		// Only rewrite URLs from our site.
		if ( ! str_starts_with( $absolute_url, $this->site_url ) ) {
			return $url;
		}

		if ( 'absolute' === $url_mode && '' !== $base_url ) {
			return $this->rewrite_absolute( $absolute_url, $base_url );
		}

		return $this->rewrite_relative( $absolute_url, $current_page_url );
	}

	/**
	 * Map a URL to its static file path.
	 *
	 * @param string $url The URL to map.
	 * @return string Relative file path (e.g., "about/index.html").
	 */
	public function url_to_path( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH ) ?? '/';
		$path = trim( $path, '/' );

		if ( '' === $path ) {
			return 'index.html';
		}

		$ext = pathinfo( $path, PATHINFO_EXTENSION );
		if ( '' !== $ext ) {
			return $path;
		}

		return $path . '/index.html';
	}

	/**
	 * Rewrite an absolute in-site URL against a custom base URL.
	 *
	 * @param string $url      The absolute in-site URL to rewrite.
	 * @param string $base_url Custom base URL to rewrite against.
	 * @return string The rewritten absolute URL.
	 */
	private function rewrite_absolute( string $url, string $base_url ): string {
		$base_url = untrailingslashit( $base_url );
		$path     = $this->get_site_relative_path( $url );
		return $base_url . '/' . ltrim( $this->path_to_html( $path ), '/' );
	}

	/**
	 * Rewrite an absolute in-site URL as a path relative to the current page.
	 *
	 * @param string $url              The absolute in-site URL to rewrite.
	 * @param string $current_page_url The URL of the page being processed.
	 * @return string The rewritten relative URL (preserving query/fragment).
	 */
	private function rewrite_relative( string $url, string $current_page_url ): string {
		$from_path = $this->url_to_path( $current_page_url );
		$to_path   = $this->url_to_path( $url );

		// Preserve query strings and fragments.
		$fragment = '';
		if ( str_contains( $url, '#' ) ) {
			$fragment = '#' . ( wp_parse_url( $url, PHP_URL_FRAGMENT ) ?? '' );
		}

		$from_dir = dirname( $from_path );
		$to_dir   = dirname( $to_path );
		$to_file  = basename( $to_path );

		if ( $from_dir === $to_dir ) {
			return $to_file . $fragment;
		}

		$relative = $this->compute_relative_path( $from_dir, $to_dir );
		return $relative . '/' . $to_file . $fragment;
	}

	/**
	 * Compute a relative filesystem path from one directory to another.
	 *
	 * @param string $from Source directory path.
	 * @param string $to   Target directory path.
	 * @return string The relative path (e.g., "../other-dir"), or "." if identical.
	 */
	private function compute_relative_path( string $from, string $to ): string {
		$from_parts = array_filter( explode( '/', $from ), fn( $p ) => '' !== $p && '.' !== $p );
		$to_parts   = array_filter( explode( '/', $to ), fn( $p ) => '' !== $p && '.' !== $p );

		$from_parts = array_values( $from_parts );
		$to_parts   = array_values( $to_parts );

		$common = 0;
		$max    = min( count( $from_parts ), count( $to_parts ) );

		while ( $common < $max && $from_parts[ $common ] === $to_parts[ $common ] ) {
			++$common;
		}

		$ups   = count( $from_parts ) - $common;
		$downs = array_slice( $to_parts, $common );
		$parts = array_merge( array_fill( 0, $ups, '..' ), $downs );

		$joined = implode( '/', $parts );
		return $joined ? $joined : '.';
	}

	/**
	 * Strip the site's base path from a URL's path, leaving a site-relative path.
	 *
	 * @param string $url The absolute URL to convert.
	 * @return string The site-relative path, always starting with "/".
	 */
	private function get_site_relative_path( string $url ): string {
		$site_path = wp_parse_url( $this->site_url, PHP_URL_PATH ) ?? '';
		$url_path  = wp_parse_url( $url, PHP_URL_PATH ) ?? '/';

		if ( '' !== $site_path && str_starts_with( $url_path, $site_path ) ) {
			$url_path = substr( $url_path, strlen( $site_path ) );
		}

		return '/' . ltrim( $url_path, '/' );
	}

	/**
	 * Convert a site-relative path to its static HTML file path.
	 *
	 * @param string $path The site-relative path to convert.
	 * @return string The static file path (e.g., "about/index.html").
	 */
	private function path_to_html( string $path ): string {
		$path = trim( $path, '/' );

		if ( '' === $path ) {
			return 'index.html';
		}

		$ext = pathinfo( $path, PATHINFO_EXTENSION );
		if ( '' !== $ext ) {
			return $path;
		}

		return $path . '/index.html';
	}

	/**
	 * Determine whether a URL should be left untouched (anchors, data URIs, etc.).
	 *
	 * @param string $url The URL to check.
	 * @return bool True if the URL should be skipped.
	 */
	private function should_skip( string $url ): bool {
		if ( '' === $url ) {
			return true;
		}

		// Skip anchors, data URIs, javascript, mailto, tel.
		foreach ( array( '#', 'data:', 'javascript:', 'mailto:', 'tel:' ) as $prefix ) {
			if ( str_starts_with( $url, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Make a protocol-relative or site-relative URL absolute.
	 *
	 * @param string $url The URL to make absolute.
	 * @return string The absolute URL, or the original URL if already absolute.
	 */
	private function make_absolute( string $url ): string {
		if ( str_starts_with( $url, '//' ) ) {
			return 'https:' . $url;
		}

		if ( str_starts_with( $url, '/' ) ) {
			return $this->site_url . $url;
		}

		return $url;
	}
}
