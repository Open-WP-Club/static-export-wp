<?php

declare(strict_types=1);

namespace StaticExportWP\Export;

final class ExportJob {

	public function __construct(
		public readonly string $export_id,
		public readonly string $output_dir,
		public readonly string $url_mode,
		public readonly string $base_url,
		public readonly array $settings_snapshot,
		public readonly ?string $started_at = null,
	) {}
}
