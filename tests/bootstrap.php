<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads WordPress stubs before the Composer autoloader so that
 * the plugin source can reference WP classes/functions.
 */

// Define ABSPATH as a real, writable temp directory (not the wordpress-stubs.php
// default of /var/www/html/) with the wp-admin/includes files some plugin code
// require_once()s unconditionally (e.g. Schema::create_tables()), so those code
// paths are actually exercisable under PHPUnit instead of fatal-erroring.
if ( ! defined( 'ABSPATH' ) ) {
	$sewp_test_abspath = sys_get_temp_dir() . '/sewp-test-abspath/';
	if ( ! is_dir( $sewp_test_abspath . 'wp-admin/includes' ) ) {
		mkdir( $sewp_test_abspath . 'wp-admin/includes', 0755, true );
	}
	foreach ( [ 'upgrade.php', 'file.php' ] as $sewp_stub_file ) {
		$sewp_stub_path = $sewp_test_abspath . 'wp-admin/includes/' . $sewp_stub_file;
		if ( ! file_exists( $sewp_stub_path ) ) {
			file_put_contents( $sewp_stub_path, "<?php\n" );
		}
	}
	define( 'ABSPATH', $sewp_test_abspath );
	unset( $sewp_test_abspath, $sewp_stub_file, $sewp_stub_path );
}

// Load WordPress class stubs.
require_once __DIR__ . '/Stubs/class-wp-error.php';
require_once __DIR__ . '/Stubs/class-wp-rest-request.php';
require_once __DIR__ . '/Stubs/class-wp-rest-response.php';
require_once __DIR__ . '/Stubs/class-wp-rest-server.php';
require_once __DIR__ . '/Stubs/class-wp-post.php';
require_once __DIR__ . '/Stubs/class-wpdb.php';
require_once __DIR__ . '/Stubs/class-wp-requests.php';
require_once __DIR__ . '/Stubs/class-wp-cli.php';

// Load WordPress function stubs.
require_once __DIR__ . '/Stubs/wordpress-stubs.php';

// Initialize global $wp_filesystem and $wpdb stubs.
global $wp_filesystem, $wpdb;
$wp_filesystem = new WP_Filesystem_Stub();
$wpdb          = new wpdb();

// Load Composer autoloader (plugin classes + test helpers).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
