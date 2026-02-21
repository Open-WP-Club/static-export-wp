<?php

declare(strict_types=1);

namespace StaticExportWP\Export;

use StaticExportWP\Core\Settings;

final class UrlRewriter {

	private string $site_url;

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

	private function rewrite_absolute( string $url, string $base_url ): string {
		$base_url = untrailingslashit( $base_url );
		$path     = $this->get_site_relative_path( $url );
		return $base_url . '/' . ltrim( $this->path_to_html( $path ), '/' );
	}

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

		$ups     = count( $from_parts ) - $common;
		$downs   = array_slice( $to_parts, $common );
		$parts   = array_merge( array_fill( 0, $ups, '..' ), $downs );

		return implode( '/', $parts ) ?: '.';
	}

	private function get_site_relative_path( string $url ): string {
		$site_path = wp_parse_url( $this->site_url, PHP_URL_PATH ) ?? '';
		$url_path  = wp_parse_url( $url, PHP_URL_PATH ) ?? '/';

		if ( '' !== $site_path && str_starts_with( $url_path, $site_path ) ) {
			$url_path = substr( $url_path, strlen( $site_path ) );
		}

		return '/' . ltrim( $url_path, '/' );
	}

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

	private function should_skip( string $url ): bool {
		if ( '' === $url ) {
			return true;
		}

		// Skip anchors, data URIs, javascript, mailto, tel.
		foreach ( [ '#', 'data:', 'javascript:', 'mailto:', 'tel:' ] as $prefix ) {
			if ( str_starts_with( $url, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

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
