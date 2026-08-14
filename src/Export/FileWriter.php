<?php
/**
 * Writes exported HTML pages and copied assets to the output directory.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Export;

use StaticExportWP\Utility\Logger;
use StaticExportWP\Utility\PathHelper;

/**
 * Handles all filesystem writes for a static export: output directory setup,
 * HTML page writes, asset copying, and cleanup.
 */
final class FileWriter {

	/**
	 * Tracks already-copied assets by relative path.
	 *
	 * @var array<string, string|false>
	 */
	private array $asset_cache = array();

	/**
	 * Construct the file writer.
	 *
	 * @param PathHelper $path_helper Resolves and validates filesystem paths.
	 * @param Logger     $logger      Logger for recording write failures.
	 */
	public function __construct(
		private readonly PathHelper $path_helper,
		private readonly Logger $logger = new Logger(),
	) {}

	/**
	 * Prepare the output directory: create it and write a .htaccess that prevents directory listing.
	 *
	 * @param string $output_dir Absolute path to the export output directory.
	 */
	public function initialize_output_dir( string $output_dir ): void {
		$this->path_helper->ensure_directory( $output_dir );

		$htaccess = trailingslashit( $output_dir ) . '.htaccess';
		if ( file_exists( $htaccess ) ) {
			return;
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$wp_filesystem->put_contents( $htaccess, "Options -Indexes\n", FS_CHMOD_FILE );
	}

	/**
	 * Write HTML content to the output directory.
	 *
	 * @param string $output_dir Absolute path to the export output directory.
	 * @param string $url        URL the HTML was rendered for, used to derive the file path.
	 * @param string $html       HTML content to write.
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

		$this->logger->error(
			'Failed to write HTML file',
			array(
				'path' => $full_path,
				'url'  => $url,
			)
		);
		return false;
	}

	/**
	 * Copy a local asset file to the output directory.
	 *
	 * @param string $output_dir Absolute path to the export output directory.
	 * @param string $asset_url  URL of the asset to copy.
	 * @param string $site_url   Site's own URL, used to resolve the asset's local path.
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
			$response = wp_remote_get(
				$asset_url,
				array(
					'timeout'   => 30,
					'sslverify' => (bool) apply_filters( 'sewp_sslverify', true ),
				)
			);

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
	 *
	 * @param string $url      Asset URL to convert.
	 * @param string $site_url Site's own URL, stripped from the asset URL's path.
	 * @return string Relative file path, without a leading slash.
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
	 *
	 * @param string $url      Asset URL to resolve.
	 * @param string $site_url Site's own URL, stripped from the asset URL's path.
	 * @return string|null Absolute local path if the file exists, null otherwise.
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
	 *
	 * @param string $output_dir Absolute path to the export output directory.
	 * @return bool True if the directory was removed (or did not exist), false on failure.
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
