<?php
/**
 * Filesystem path helpers used across the export process.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Utility;

/**
 * Provides URL-to-filepath conversion, directory-traversal-safe path
 * resolution, and directory creation helpers used by the export writers.
 */
final class PathHelper {

	/**
	 * Convert a URL path to a filesystem path.
	 *
	 * Examples:
	 *   /              -> index.html
	 *   /about/        -> about/index.html
	 *   /style.css     -> style.css
	 *   /feed/         -> feed/index.html
	 *
	 * @param string $url_path The URL path to convert.
	 * @return string The resulting filesystem-relative path.
	 */
	public function url_to_filepath( string $url_path ): string {
		$path = trim( wp_parse_url( $url_path, PHP_URL_PATH ) ?? '/', '/' );

		if ( '' === $path ) {
			return 'index.html';
		}

		// If path already has a file extension, keep it as-is.
		$extension = pathinfo( $path, PATHINFO_EXTENSION );
		if ( '' !== $extension ) {
			return $path;
		}

		// Directory-like path: append index.html.
		return rtrim( $path, '/' ) . '/index.html';
	}

	/**
	 * Ensure a path doesn't escape the output directory (directory traversal prevention).
	 *
	 * @param string $base_dir      Absolute base directory that the result must stay within.
	 * @param string $relative_path Relative path to resolve against the base directory.
	 * @return string|false The resolved absolute path, or false if it escapes the base directory.
	 */
	public function safe_path( string $base_dir, string $relative_path ): string|false {
		$resolved_base = realpath( $base_dir );
		$base_dir      = rtrim( $resolved_base ? $resolved_base : $base_dir, '/' );
		$full          = $base_dir . '/' . $relative_path;

		// Resolve any ../ segments.
		$resolved = $this->resolve_path( $full );

		if ( ! str_starts_with( $resolved, $base_dir . '/' ) && $resolved !== $base_dir ) {
			return false;
		}

		return $resolved;
	}

	/**
	 * Resolve a path without requiring the file to exist.
	 *
	 * @param string $path The path to resolve (may contain "." and ".." segments).
	 * @return string The resolved path with "." and ".." segments collapsed.
	 */
	public function resolve_path( string $path ): string {
		$parts    = explode( '/', str_replace( '\\', '/', $path ) );
		$resolved = array();

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
	 *
	 * @param string $path The directory path to check/create.
	 * @return bool True if the directory exists or was successfully created.
	 */
	public function ensure_directory( string $path ): bool {
		if ( is_dir( $path ) ) {
			return true;
		}
		return wp_mkdir_p( $path );
	}
}
