<?php

/**
 * PHPStan bootstrap — defines plugin constants only.
 * WordPress classes/functions come from php-stubs/wordpress-stubs via the extension.
 */

defined( 'SEWP_VERSION' )    || define( 'SEWP_VERSION', '0.0.0' );
defined( 'ABSPATH' )         || define( 'ABSPATH', '/tmp/wp/' );
defined( 'FS_CHMOD_FILE' )   || define( 'FS_CHMOD_FILE', 0644 );
defined( 'SEWP_PLUGIN_FILE' ) || define( 'SEWP_PLUGIN_FILE', __DIR__ . '/static-export-wp.php' );
defined( 'SEWP_FILE' )       || define( 'SEWP_FILE', __DIR__ . '/static-export-wp.php' );
defined( 'SEWP_PATH' )       || define( 'SEWP_PATH', __DIR__ . '/' );
defined( 'SEWP_URL' )        || define( 'SEWP_URL', 'http://example.com/wp-content/plugins/static-export-wp/' );
