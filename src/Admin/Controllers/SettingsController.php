<?php
/**
 * REST controller for reading and updating plugin settings.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Admin\Controllers;

use StaticExportWP\Core\Settings;

/**
 * Handles the `/settings` REST route.
 */
final class SettingsController {

	/**
	 * Create the controller.
	 *
	 * @param Settings $settings Plugin settings being read and updated.
	 */
	public function __construct(
		private readonly Settings $settings,
	) {}

	/**
	 * Get all current plugin settings.
	 *
	 * @return \WP_REST_Response Response containing all settings.
	 */
	public function get(): \WP_REST_Response {
		return new \WP_REST_Response( $this->settings->get_all() );
	}

	/**
	 * Update plugin settings from the request body.
	 *
	 * @param \WP_REST_Request $request The REST request, containing the settings to update as JSON.
	 * @return \WP_REST_Response Response with the updated settings.
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$this->settings->update( $params );

		return new \WP_REST_Response(
			array(
				'success'  => true,
				'settings' => $this->settings->get_all(),
			)
		);
	}
}
