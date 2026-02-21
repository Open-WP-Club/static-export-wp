<?php

declare(strict_types=1);

namespace StaticExportWP\Core;

final class Deactivator {

	public static function deactivate(): void {
		// Cancel any running background exports.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sewp_process_batch' );
		}

		wp_clear_scheduled_hook( 'sewp_process_batch' );

		delete_option( 'sewp_export_progress' );
	}
}
