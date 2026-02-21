<?php

declare(strict_types=1);

namespace StaticExportWP\Utility;

final class PathHelper {

	/**
	 * Convert a URL path to a filesystem path.
	 *
	 * Examples:
	 *   /              -> index.html
	 *   /about/        -> about/index.html
	 *   /style.css     -> style.css
	 *   /feed/         -> feed/index.html
	 */
	public function url_to_filepath( string $url_path ): string {
		$path = trim( parse_url( $url_path, PHP_URL_PATH ) ?? '/', '/' );

		if ( '' === $path ) {
			return 'index.html';
		}

		// If path already has a file extension, keep it as-is.
		$extension = pathinfo( $path, PATHINFO_EXTENSION );
		if ( '' !== $extension ) {
			return $path;
		}

		// Directory-like path: append index.html.
		return trailingslashit( $path ) . 'index.html';
	}

	/**
	 * Ensure a path doesn't escape the output directory (directory traversal prevention).
	 */
	public function safe_path( string $base_dir, string $relative_path ): string|false {
		$base_dir = rtrim( realpath( $base_dir ) ?: $base_dir, '/' );
		$full     = $base_dir . '/' . $relative_path;

		// Resolve any ../ segments.
		$resolved = $this->resolve_path( $full );

		if ( ! str_starts_with( $resolved, $base_dir . '/' ) && $resolved !== $base_dir ) {
			return false;
		}

		return $resolved;
	}

	/**
	 * Resolve a path without requiring the file to exist.
	 */
	public function resolve_path( string $path ): string {
		$parts    = explode( '/', str_replace( '\\', '/', $path ) );
		$resolved = [];

		foreach ( $parts as $part ) {
			if ( '..' === $part ) {
				array_pop( $resolved );
			} elseif ( '.' !== $part && '' !== $part ) {
				$resolved[] = $part;
			}
		}

		$prefix = str_starts_with( $path, '/' ) ? '/' : '';
		return $prefix . implode( '/', $resolved );
	}

	/**
	 * Ensure a directory exists.
	 */
	public function ensure_directory( string $path ): bool {
		if ( is_dir( $path ) ) {
			return true;
		}
		return wp_mkdir_p( $path );
	}
}
