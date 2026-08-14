<?php
/**
 * Value object describing a single static export run.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Export;

/**
 * Immutable snapshot of the parameters and identity of an export job.
 */
final readonly class ExportJob {

	/**
	 * Construct a new export job value object.
	 *
	 * @param string      $export_id         Unique identifier for the export run.
	 * @param string      $output_dir        Absolute path the export is written to.
	 * @param string      $url_mode          URL rewrite mode: relative or absolute.
	 * @param string      $base_url          Base URL used when rewriting in absolute mode.
	 * @param array       $settings_snapshot Copy of the plugin settings in effect for this run.
	 * @param string|null $started_at        ISO-8601 timestamp the export started, or null if not yet started.
	 */
	public function __construct(
		public string $export_id,
		public string $output_dir,
		public string $url_mode,
		public string $base_url,
		public array $settings_snapshot,
		public ?string $started_at = null,
	) {}
}
