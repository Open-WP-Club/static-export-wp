<?php

declare(strict_types=1);

namespace StaticExportWP\Admin;

final class AdminPage {

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_post_sewp_download_export', [ $this, 'handle_download' ] );
	}

	public function add_menu_page(): void {
		$hook = add_menu_page(
			__( 'Static Export', 'static-export-wp' ),
			__( 'Static Export', 'static-export-wp' ),
			'manage_options',
			'static-export-wp',
			[ $this, 'render' ],
			'dashicons-upload',
			80,
		);

		add_action( "admin_enqueue_scripts", function ( string $current_hook ) use ( $hook ) {
			if ( $current_hook !== $hook ) {
				return;
			}
			$this->enqueue_assets();
		} );
	}

	public function render(): void {
		echo '<div id="sewp-admin-root"></div>';
	}

	private function enqueue_assets(): void {
		$asset_file = SEWP_PATH . 'build/admin.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'sewp-admin',
			SEWP_URL . 'build/admin.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? SEWP_VERSION,
			true,
		);

		wp_enqueue_style(
			'sewp-admin',
			SEWP_URL . 'build/admin.css',
			[ 'wp-components' ],
			$asset['version'] ?? SEWP_VERSION,
		);

		wp_localize_script( 'sewp-admin', 'sewpConfig', [
			'restUrl'     => rest_url( 'sewp/v1/' ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'version'     => SEWP_VERSION,
			'adminUrl'    => admin_url(),
			'downloadUrl' => wp_nonce_url( admin_url( 'admin-post.php?action=sewp_download_export' ), 'sewp_download_export' ),
		] );
	}

	/**
	 * Handle the ZIP download via admin-post.php.
	 */
	public function handle_download(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to download exports.', 'static-export-wp' ) );
		}

		check_admin_referer( 'sewp_download_export' );

		$settings   = \StaticExportWP\Core\Plugin::instance()->settings();
		$output_dir = $settings->get( 'output_dir' );

		if ( ! is_dir( $output_dir ) ) {
			wp_die( esc_html__( 'No export found. Run an export first.', 'static-export-wp' ) );
		}

		$zip_path = $this->create_zip( $output_dir );

		if ( false === $zip_path ) {
			wp_die( esc_html__( 'Failed to create ZIP file.', 'static-export-wp' ) );
		}

		$filename = 'static-export-' . gmdate( 'Y-m-d-His' ) . '.zip';

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $zip_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		readfile( $zip_path );
		unlink( $zip_path );
		exit;
	}

	/**
	 * Create a ZIP from the output directory.
	 *
	 * @return string|false Path to the temporary ZIP file, or false on failure.
	 */
	private function create_zip( string $source_dir ): string|false {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$zip_path = wp_tempnam( 'sewp-export' ) . '.zip';
		$zip      = new \ZipArchive();

		if ( true !== $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return false;
		}

		$source_dir = rtrim( realpath( $source_dir ), '/' );
		$iterator   = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source_dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY,
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$relative = substr( $file->getPathname(), strlen( $source_dir ) + 1 );
				$zip->addFile( $file->getPathname(), $relative );
			}
		}

		$zip->close();

		return file_exists( $zip_path ) ? $zip_path : false;
	}
}
