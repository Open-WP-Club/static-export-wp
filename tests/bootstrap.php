<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads WordPress stubs before the Composer autoloader so that
 * the plugin source can reference WP classes/functions.
 */

// Load WordPress class stubs.
require_once __DIR__ . '/Stubs/class-wp-error.php';
require_once __DIR__ . '/Stubs/class-wp-rest-request.php';
require_once __DIR__ . '/Stubs/class-wp-rest-response.php';
require_once __DIR__ . '/Stubs/class-wp-rest-server.php';
require_once __DIR__ . '/Stubs/class-wp-post.php';
require_once __DIR__ . '/Stubs/class-wpdb.php';

// Load WordPress function stubs.
require_once __DIR__ . '/Stubs/wordpress-stubs.php';

// Initialize global $wp_filesystem and $wpdb stubs.
global $wp_filesystem, $wpdb;
$wp_filesystem = new WP_Filesystem_Stub();
$wpdb          = new wpdb();

// Load Composer autoloader (plugin classes + test helpers).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
