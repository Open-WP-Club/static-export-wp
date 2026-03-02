<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Helpers;

use StaticExportWP\Background\ActionSchedulerBridge;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Core\Settings;
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

/**
 * Helper for creating real instances of complex classes with all dependencies
 * wired to WP stub-backed implementations.
 */
class ReflectionHelper {

	/**
	 * Build a real ExportManager with all dependencies backed by WP stubs.
	 *
	 * The returned ExportManager is fully functional but operates against
	 * the in-memory WP stub state (options, wpdb, HTTP responses, etc.).
	 */
	public static function buildRealExportManager(
		?Settings $settings = null,
		?ProgressTracker $progress = null,
		?CrawlQueue $crawl_queue = null,
		?ActionSchedulerBridge $scheduler = null,
		?Logger $logger = null,
	): ExportManager {
		$settings    ??= new Settings();
		$progress    ??= new ProgressTracker();
		$crawl_queue ??= new CrawlQueue();
		$scheduler   ??= new ActionSchedulerBridge();
		$logger      ??= new Logger();

		$url_discovery   = new UrlDiscovery( $settings );
		$fetcher         = new Fetcher( $settings );
		$url_rewriter    = new UrlRewriter( $settings );
		$asset_collector = new AssetCollector();
		$html_processor  = new HtmlProcessor( $url_rewriter, $asset_collector );
		$path_helper     = new PathHelper();
		$file_writer     = new FileWriter( $path_helper );

		return new ExportManager(
			settings: $settings,
			url_discovery: $url_discovery,
			fetcher: $fetcher,
			crawl_queue: $crawl_queue,
			html_processor: $html_processor,
			asset_collector: $asset_collector,
			file_writer: $file_writer,
			progress: $progress,
			scheduler: $scheduler,
			logger: $logger,
		);
	}
}
