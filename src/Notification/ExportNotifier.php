<?php

declare(strict_types=1);

namespace StaticExportWP\Notification;

use StaticExportWP\Core\Settings;

final class ExportNotifier {

	public function __construct(
		private readonly Settings $settings,
	) {}

	/**
	 * Handle the sewp_export_finalized action.
	 *
	 * @param string $export_id Export UUID.
	 * @param string $status    'completed' or 'failed'.
	 * @param array  $counts    {total, completed, failed}.
	 * @param string $duration  Human-readable duration.
	 */
	public function notify( string $export_id, string $status, array $counts, string $duration ): void {
		if ( ! $this->settings->get( 'notify_enabled', false ) ) {
			return;
		}

		$to = $this->settings->get( 'notify_email', '' );
		if ( '' === $to ) {
			$to = get_option( 'admin_email' );
		}

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf(
			/* translators: 1: site name, 2: export status */
			__( '[%1$s] Static export %2$s', 'static-export-wp' ),
			$site_name,
			$status,
		);

		$body = sprintf(
			"Export ID: %s\nStatus: %s\n\nTotal URLs: %d\nCompleted: %d\nFailed: %d\n\nDuration: %s",
			$export_id,
			$status,
			$counts['total'] ?? 0,
			$counts['completed'] ?? 0,
			$counts['failed'] ?? 0,
			$duration,
		);

		wp_mail( $to, $subject, $body );
	}
}
