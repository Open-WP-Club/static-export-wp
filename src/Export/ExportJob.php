<?php

declare(strict_types=1);

namespace StaticExportWP\Export;

final readonly class ExportJob {

	public function __construct(
		public string $export_id,
		public string $output_dir,
		public string $url_mode,
		public string $base_url,
		public array $settings_snapshot,
		public ?string $started_at = null,
	) {}
}
