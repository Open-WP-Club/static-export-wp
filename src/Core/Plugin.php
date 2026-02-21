<?php

declare(strict_types=1);

namespace StaticExportWP\Core;

use StaticExportWP\Admin\AdminPage;
use StaticExportWP\Admin\RestApi;
use StaticExportWP\Background\ActionSchedulerBridge;
use StaticExportWP\Background\BatchProcessor;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\CLI\StaticExportCommand;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Crawler\Fetcher;
use StaticExportWP\Crawler\UrlDiscovery;
use StaticExportWP\Export\AssetCollector;
use StaticExportWP\Export\ExportManager;
use StaticExportWP\Export\FileWriter;
use StaticExportWP\Export\HtmlProcessor;
use StaticExportWP\Export\UrlRewriter;
use StaticExportWP\Utility\Logger;
use StaticExportWP\Utility\PathHelper;

final class Plugin {

	private static ?self $instance = null;

	private Settings $settings;
	private ExportManager $export_manager;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		$this->settings = new Settings();

		$path_helper      = new PathHelper();
		$logger           = new Logger();
		$crawl_queue      = new CrawlQueue();
		$url_discovery    = new UrlDiscovery( $this->settings );
		$fetcher          = new Fetcher( $this->settings );
		$url_rewriter     = new UrlRewriter( $this->settings );
		$asset_collector  = new AssetCollector();
		$html_processor   = new HtmlProcessor( $url_rewriter, $asset_collector );
		$file_writer      = new FileWriter( $path_helper );
		$progress_tracker = new ProgressTracker();
		$scheduler        = new ActionSchedulerBridge();

		$this->export_manager = new ExportManager(
			$this->settings,
			$url_discovery,
			$fetcher,
			$crawl_queue,
			$html_processor,
			$file_writer,
			$progress_tracker,
			$scheduler,
			$logger,
		);

		$batch_processor = new BatchProcessor(
			$this->export_manager,
			$crawl_queue,
			$progress_tracker,
			$scheduler,
			$this->settings,
		);

		add_action( 'sewp_process_batch', [ $batch_processor, 'handle' ] );

		if ( is_admin() ) {
			$admin_page = new AdminPage();
			$admin_page->register();

			$rest_api = new RestApi( $this->export_manager, $this->settings, $url_discovery, $progress_tracker );
			add_action( 'rest_api_init', [ $rest_api, 'register_routes' ] );
		}

		// REST API must also be available for non-admin REST requests.
		if ( ! is_admin() ) {
			$rest_api = new RestApi( $this->export_manager, $this->settings, $url_discovery, $progress_tracker );
			add_action( 'rest_api_init', [ $rest_api, 'register_routes' ] );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'static-export', new StaticExportCommand(
				$this->export_manager,
				$this->settings,
				$url_discovery,
				$progress_tracker,
			) );
		}

		load_plugin_textdomain( 'static-export-wp', false, dirname( plugin_basename( SEWP_FILE ) ) . '/languages' );
	}

	public function settings(): Settings {
		return $this->settings;
	}

	public function export_manager(): ExportManager {
		return $this->export_manager;
	}
}
