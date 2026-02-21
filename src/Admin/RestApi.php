<?php

declare(strict_types=1);

namespace StaticExportWP\Admin;

use StaticExportWP\Admin\Controllers\ExportController;
use StaticExportWP\Admin\Controllers\LogController;
use StaticExportWP\Admin\Controllers\SettingsController;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\UrlDiscovery;
use StaticExportWP\Export\ExportManager;

final class RestApi {

	private const NAMESPACE = 'sewp/v1';

	public function __construct(
		private readonly ExportManager $export_manager,
		private readonly Settings $settings,
		private readonly UrlDiscovery $url_discovery,
		private readonly ProgressTracker $progress,
	) {}

	public function register_routes(): void {
		$settings_controller = new SettingsController( $this->settings );
		$export_controller   = new ExportController( $this->export_manager, $this->progress );
		$log_controller      = new LogController();

		// Settings.
		register_rest_route( self::NAMESPACE, '/settings', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $settings_controller, 'get' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $settings_controller, 'update' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );

		// Export actions.
		register_rest_route( self::NAMESPACE, '/export/start', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $export_controller, 'start' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/export/cancel', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $export_controller, 'cancel' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/export/status', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $export_controller, 'status' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/export/clean', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $export_controller, 'clean' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/export/discover-urls', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => function () {
				$urls = $this->url_discovery->discover();
				return new \WP_REST_Response( [
					'urls'  => $urls,
					'count' => count( $urls ),
				] );
			},
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		// Export log.
		register_rest_route( self::NAMESPACE, '/export/log', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $log_controller, 'index' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );
	}

	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}
}
