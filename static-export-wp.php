<?php
/**
 * Plugin Name:       Static Export WP
 * Plugin URI:        https://github.com/open-wp-club/static-export-wp
 * Description:       Export your WordPress site as static HTML files. Crawls every page and saves a complete static copy with all assets.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.3
 * Author:            Static Export WP Contributors
 * Author URI:        https://github.com/open-wp-club/static-export-wp
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       static-export-wp
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEWP_VERSION', '1.1.0' );
define( 'SEWP_FILE', __FILE__ );
define( 'SEWP_PATH', plugin_dir_path( __FILE__ ) );
define( 'SEWP_URL', plugin_dir_url( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.3', '<' ) ) {
	add_action( 'admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Static Export WP requires PHP 8.3 or higher.', 'static-export-wp' )
		);
	} );
	return;
}

if ( file_exists( SEWP_PATH . 'vendor/autoload.php' ) ) {
	require_once SEWP_PATH . 'vendor/autoload.php';
}

register_activation_hook( SEWP_FILE, [ \StaticExportWP\Core\Activator::class, 'activate' ] );
register_deactivation_hook( SEWP_FILE, [ \StaticExportWP\Core\Deactivator::class, 'deactivate' ] );

add_action( 'plugins_loaded', function () {
	\StaticExportWP\Core\Plugin::instance()->boot();
} );
