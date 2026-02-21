<?php

declare(strict_types=1);

namespace StaticExportWP\Core;

use StaticExportWP\Admin\AdminPage;
use StaticExportWP\Admin\RestApi;
use StaticExportWP\Background\ActionSchedulerBridge;
use StaticExportWP\Background\BatchProcessor;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\CLI\StaticExportCommand;
use StaticExportWP\Crawler\BatchFetcher;
use StaticExportWP\Crawler\CrawlQueue;
use StaticExportWP\Crawler\Fetcher;
use StaticExportWP\Crawler\UrlDiscovery;
use StaticExportWP\Export\AssetCollector;
use StaticExportWP\Export\ContentHashStore;
use StaticExportWP\Export\ExportManager;
use StaticExportWP\Export\FileWriter;
use StaticExportWP\Export\HtmlProcessor;
use StaticExportWP\Export\UrlRewriter;
use StaticExportWP\Notification\ExportNotifier;
use StaticExportWP\Search\PagefindRunner;
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
		$batch_fetcher    = new BatchFetcher( $this->settings );
		$url_rewriter     = new UrlRewriter( $this->settings );
		$asset_collector  = new AssetCollector();
		$html_processor   = new HtmlProcessor( $url_rewriter, $asset_collector );
		$file_writer      = new FileWriter( $path_helper );
		$progress_tracker   = new ProgressTracker();
		$scheduler          = new ActionSchedulerBridge();
		$content_hash_store = new ContentHashStore();

		$this->export_manager = new ExportManager(
			$this->settings,
			$url_discovery,
			$fetcher,
			$crawl_queue,
			$html_processor,
			$asset_collector,
			$file_writer,
			$progress_tracker,
			$scheduler,
			$logger,
			$content_hash_store,
			$batch_fetcher,
		);

		$batch_processor = new BatchProcessor(
			$this->export_manager,
			$crawl_queue,
			$progress_tracker,
			$scheduler,
			$this->settings,
		);

		add_action( 'sewp_process_batch', [ $batch_processor, 'handle' ] );

		// Notifications.
		$notifier = new ExportNotifier( $this->settings );
		add_action( 'sewp_export_finalized', [ $notifier, 'notify' ], 10, 4 );

		// Pagefind search indexing.
		$pagefind = new PagefindRunner( $this->settings, $logger );
		add_action( 'sewp_post_export_process', [ $pagefind, 'run' ] );

		// DB migration check for updates without re-activation.
		$this->maybe_upgrade_db();

		if ( is_admin() ) {
			$admin_page = new AdminPage();
			$admin_page->register();
		}

		// REST API must be available for both admin and frontend REST requests.
		$rest_api = new RestApi( $this->export_manager, $this->settings, $url_discovery, $progress_tracker );
		add_action( 'rest_api_init', [ $rest_api, 'register_routes' ] );

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

	private function maybe_upgrade_db(): void {
		$installed_version = get_option( 'sewp_db_version', '0' );
		if ( version_compare( $installed_version, Schema::DB_VERSION, '<' ) ) {
			Schema::create_tables();
		}
	}
}
