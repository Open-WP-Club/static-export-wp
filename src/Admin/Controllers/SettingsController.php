<?php

declare(strict_types=1);

namespace StaticExportWP\Admin\Controllers;

use StaticExportWP\Core\Settings;

final class SettingsController {

	public function __construct(
		private readonly Settings $settings,
	) {}

	public function get(): \WP_REST_Response {
		return new \WP_REST_Response( $this->settings->get_all() );
	}

	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$this->settings->update( $params );

		return new \WP_REST_Response( [
			'success'  => true,
			'settings' => $this->settings->get_all(),
		] );
	}
}
