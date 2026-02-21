<?php

declare(strict_types=1);

namespace StaticExportWP\Export;

use StaticExportWP\Utility\PathHelper;

final class FileWriter {

	/** @var array<string, string|false> Tracks already-copied assets by relative path. */
	private array $asset_cache = [];

	public function __construct(
		private readonly PathHelper $path_helper,
	) {}

	/**
	 * Write HTML content to the output directory.
	 *
	 * @return string|false The relative file path on success, false on failure.
	 */
	public function write_html( string $output_dir, string $url, string $html ): string|false {
		$relative_path = $this->path_helper->url_to_filepath( $url );
		$full_path     = $this->path_helper->safe_path( $output_dir, $relative_path );

		if ( false === $full_path ) {
			return false;
		}

		$dir = dirname( $full_path );
		if ( ! $this->path_helper->ensure_directory( $dir ) ) {
			return false;
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem->put_contents( $full_path, $html, FS_CHMOD_FILE ) ) {
			return $relative_path;
		}

		return false;
	}

	/**
	 * Copy a local asset file to the output directory.
	 *
	 * @return string|false The relative file path on success, false on failure.
	 */
	public function copy_asset( string $output_dir, string $asset_url, string $site_url ): string|false {
		$relative_path = $this->asset_url_to_path( $asset_url, $site_url );

		if ( '' === $relative_path ) {
			return false;
		}

		// Never copy PHP files into static output.
		if ( str_ends_with( strtolower( $relative_path ), '.php' ) ) {
			return false;
		}

		// Check in-memory cache first — avoids file_exists() syscall.
		if ( array_key_exists( $relative_path, $this->asset_cache ) ) {
			return $this->asset_cache[ $relative_path ];
		}

		$full_path = $this->path_helper->safe_path( $output_dir, $relative_path );

		if ( false === $full_path ) {
			$this->asset_cache[ $relative_path ] = false;
			return false;
		}

		// Don't overwrite if already copied.
		if ( file_exists( $full_path ) ) {
			$this->asset_cache[ $relative_path ] = $relative_path;
			return $relative_path;
		}

		$local_path = $this->url_to_local_path( $asset_url, $site_url );

		$dir = dirname( $full_path );
		if ( ! $this->path_helper->ensure_directory( $dir ) ) {
			return false;
		}

		if ( null !== $local_path && file_exists( $local_path ) ) {
			// Local file — copy directly.
			if ( copy( $local_path, $full_path ) ) {
				$this->asset_cache[ $relative_path ] = $relative_path;
				return $relative_path;
			}
		} else {
			// Remote or not found locally — fetch via HTTP.
			$response = wp_remote_get( $asset_url, [
				'timeout'   => 30,
				'sslverify' => false,
			] );

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				global $wp_filesystem;
				if ( empty( $wp_filesystem ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();
				}

				if ( $wp_filesystem->put_contents( $full_path, wp_remote_retrieve_body( $response ), FS_CHMOD_FILE ) ) {
					$this->asset_cache[ $relative_path ] = $relative_path;
					return $relative_path;
				}
			}
		}

		$this->asset_cache[ $relative_path ] = false;
		return false;
	}

	/**
	 * Convert an asset URL to a relative file path.
	 */
	private function asset_url_to_path( string $url, string $site_url ): string {
		$site_path = wp_parse_url( $site_url, PHP_URL_PATH ) ?? '';
		$url_path  = wp_parse_url( $url, PHP_URL_PATH ) ?? '';

		if ( '' !== $site_path && str_starts_with( $url_path, $site_path ) ) {
			$url_path = substr( $url_path, strlen( $site_path ) );
		}

		return ltrim( $url_path, '/' );
	}

	/**
	 * Try to map a URL to a local filesystem path.
	 */
	private function url_to_local_path( string $url, string $site_url ): ?string {
		$site_path = wp_parse_url( $site_url, PHP_URL_PATH ) ?? '';
		$url_path  = wp_parse_url( $url, PHP_URL_PATH ) ?? '';

		if ( '' !== $site_path && str_starts_with( $url_path, $site_path ) ) {
			$url_path = substr( $url_path, strlen( $site_path ) );
		}

		$local = ABSPATH . ltrim( $url_path, '/' );

		return file_exists( $local ) ? $local : null;
	}

	/**
	 * Delete the output directory and all contents.
	 */
	public function clean_output( string $output_dir ): bool {
		if ( ! is_dir( $output_dir ) ) {
			return true;
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem->rmdir( $output_dir, true );
	}
}
