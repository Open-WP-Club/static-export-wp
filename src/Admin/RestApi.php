<?php
/**
 * Registers the plugin's REST API routes.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Admin;

use StaticExportWP\Admin\Controllers\BrokenLinkController;
use StaticExportWP\Admin\Controllers\ExportController;
use StaticExportWP\Admin\Controllers\LogController;
use StaticExportWP\Admin\Controllers\SettingsController;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Core\Settings;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Crawler\UrlDiscovery;
use StaticExportWP\Export\ExportManager;
use StaticExportWP\Notification\WebhookNotifier;

/**
 * Registers all `sewp/v1` REST API routes and wires up their controllers.
 */
final class RestApi {

	private const string NAMESPACE = 'sewp/v1';

	/**
	 * Create the REST API registrar.
	 *
	 * @param ExportManager   $export_manager Manages starting, cancelling and cleaning exports.
	 * @param Settings        $settings       Plugin settings, exposed via the settings routes.
	 * @param UrlDiscovery    $url_discovery  Discovers site URLs for the discover-urls route.
	 * @param ProgressTracker $progress       Tracks export progress, used by the export controller.
	 * @param CrawlQueue      $crawl_queue    Crawl queue, used by the broken-links route.
	 */
	public function __construct(
		private readonly ExportManager $export_manager,
		private readonly Settings $settings,
		private readonly UrlDiscovery $url_discovery,
		private readonly ProgressTracker $progress,
		private readonly CrawlQueue $crawl_queue,
	) {}

	/**
	 * Register all REST API routes exposed by the plugin.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$settings_controller = new SettingsController( $this->settings );
		$export_controller   = new ExportController( $this->export_manager, $this->progress );
		$log_controller      = new LogController();

		// Settings.
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $settings_controller, 'get' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $settings_controller, 'update' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// Export actions.
		register_rest_route(
			self::NAMESPACE,
			'/export/start',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $export_controller, 'start' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/export/cancel',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $export_controller, 'cancel' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/export/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $export_controller, 'status' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/export/clean',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $export_controller, 'clean' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/export/discover-urls',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => function () {
					$urls = $this->url_discovery->discover();
					return new \WP_REST_Response(
						array(
							'urls'  => $urls,
							'count' => count( $urls ),
						)
					);
				},
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Post types.
		register_rest_route(
			self::NAMESPACE,
			'/post-types',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => function () {
					$types  = get_post_types( array( 'public' => true ), 'objects' );
					$result = array();
					foreach ( $types as $type ) {
						$result[] = array(
							'name'  => $type->name,
							'label' => $type->label,
						);
					}
					return new \WP_REST_Response( $result );
				},
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Export log.
		register_rest_route(
			self::NAMESPACE,
			'/export/log',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $log_controller, 'index' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Size report.
		register_rest_route(
			self::NAMESPACE,
			'/export/size-report',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $log_controller, 'size_report' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Webhook test.
		register_rest_route(
			self::NAMESPACE,
			'/webhook/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => function () {
					$notifier = new WebhookNotifier( $this->settings );
					return $notifier->send_test();
				},
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Broken links.
		$broken_link_controller = new BrokenLinkController( $this->crawl_queue );
		register_rest_route(
			self::NAMESPACE,
			'/export/broken-links',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $broken_link_controller, 'index' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'export_id' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * Permission callback used by all routes: requires the manage_options capability.
	 *
	 * @return bool True if the current user may manage the plugin, false otherwise.
	 */
	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}
}
