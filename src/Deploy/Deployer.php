<?php
/**
 * Contract implemented by all static site deploy targets.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Deploy;

use StaticExportWP\Export\ExportJob;

/**
 * A deploy target that ships an export job's output directory somewhere.
 */
interface Deployer {

	/**
	 * Deploy the exported site.
	 *
	 * @param ExportJob $job Export job whose output_dir should be deployed.
	 * @return DeployResult Result of the deploy attempt.
	 */
	public function deploy( ExportJob $job ): DeployResult;

	/**
	 * Human-readable label for this deploy method.
	 *
	 * @return string Deploy method label.
	 */
	public function label(): string;
}
