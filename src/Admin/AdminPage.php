<?php

declare(strict_types=1);

namespace StaticExportWP\Admin;

final class AdminPage {

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
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
			'restUrl'  => rest_url( 'sewp/v1/' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'version'  => SEWP_VERSION,
			'adminUrl' => admin_url(),
		] );
	}
}
