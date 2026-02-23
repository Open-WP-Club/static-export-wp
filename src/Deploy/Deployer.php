<?php

declare(strict_types=1);

namespace StaticExportWP\Deploy;

use StaticExportWP\Export\ExportJob;

interface Deployer {

	/**
	 * Deploy the exported site.
	 */
	public function deploy( ExportJob $job ): DeployResult;

	/**
	 * Human-readable label for this deploy method.
	 */
	public function label(): string;
}
