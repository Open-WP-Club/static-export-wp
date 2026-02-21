<?php
/**
 * Uninstall handler — runs when the plugin is deleted via WP admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sewp_crawl_queue" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sewp_export_log" );

// Remove options.
delete_option( 'sewp_settings' );
delete_option( 'sewp_export_progress' );
delete_option( 'sewp_db_version' );

// Remove any scheduled actions.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'sewp_process_batch' );
}

wp_clear_scheduled_hook( 'sewp_process_batch' );
