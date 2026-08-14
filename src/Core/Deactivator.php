<?php
/**
 * Runs plugin deactivation tasks.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Core;

/**
 * Handles cleanup performed when the plugin is deactivated.
 */
final class Deactivator {

	/**
	 * Cancel any running background exports and clear stored progress.
	 */
	public static function deactivate(): void {
		// Cancel any running background exports.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sewp_process_batch' );
		}

		wp_clear_scheduled_hook( 'sewp_process_batch' );

		delete_option( 'sewp_export_progress' );
	}
}
