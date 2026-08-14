<?php
/**
 * Deploys exported static sites by running a user-configured shell command.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Deploy;

use StaticExportWP\Core\Settings;
use StaticExportWP\Export\ExportJob;
use StaticExportWP\Utility\Logger;

/**
 * Runs an admin-configured shell command against the export output directory.
 */
final class CommandDeployer implements Deployer {

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings accessor.
	 * @param Logger   $logger   Logger for deploy progress and errors.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly Logger $logger,
	) {}

	/**
	 * Run the configured deploy command against the export job's output directory.
	 *
	 * @param ExportJob $job Export job whose output_dir will be passed to the command.
	 * @return DeployResult Result of the deploy attempt.
	 */
	public function deploy( ExportJob $job ): DeployResult {
		$command = $this->settings->get( 'deploy_command', '' );

		if ( '' === $command ) {
			return DeployResult::fail( 'No deploy command configured.' );
		}

		// Replace placeholder with escaped output directory path.
		$command = str_replace( '{{output_dir}}', escapeshellarg( $job->output_dir ), $command );

		$this->logger->info( 'Running deploy command', array( 'command' => $command ) );

		// Security note: The deploy command is set by a user with manage_options
		// capability (the same trust level as installing plugins). The output_dir
		// is escaped via escapeshellarg. This is a PHP plugin using exec() —
		// not a JS context where execFile would apply.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		$output    = array();
		$exit_code = 0;
		exec( $command . ' 2>&1', $output, $exit_code );

		if ( 0 === $exit_code ) {
			return DeployResult::ok( 'Deploy command completed successfully.' );
		}

		return DeployResult::fail(
			'Deploy command failed.',
			array(
				'exit_code' => $exit_code,
				'output'    => implode( "\n", $output ),
			)
		);
	}

	/**
	 * Human-readable label for this deploy method.
	 *
	 * @return string Deploy method label.
	 */
	public function label(): string {
		return 'Shell Command';
	}
}
