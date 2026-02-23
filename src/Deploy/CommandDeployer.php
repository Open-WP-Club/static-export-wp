<?php

declare(strict_types=1);

namespace StaticExportWP\Deploy;

use StaticExportWP\Core\Settings;
use StaticExportWP\Export\ExportJob;
use StaticExportWP\Utility\Logger;

final class CommandDeployer implements Deployer {

	public function __construct(
		private readonly Settings $settings,
		private readonly Logger $logger,
	) {}

	public function deploy( ExportJob $job ): DeployResult {
		$command = $this->settings->get( 'deploy_command', '' );

		if ( '' === $command ) {
			return DeployResult::fail( 'No deploy command configured.' );
		}

		// Replace placeholder with escaped output directory path.
		$command = str_replace( '{{output_dir}}', escapeshellarg( $job->output_dir ), $command );

		$this->logger->info( 'Running deploy command', [ 'command' => $command ] );

		// Security note: The deploy command is set by a user with manage_options
		// capability (the same trust level as installing plugins). The output_dir
		// is escaped via escapeshellarg. This is a PHP plugin using exec() —
		// not a JS context where execFile would apply.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		$output    = [];
		$exit_code = 0;
		exec( $command . ' 2>&1', $output, $exit_code );

		if ( 0 === $exit_code ) {
			return DeployResult::ok( 'Deploy command completed successfully.' );
		}

		return DeployResult::fail( 'Deploy command failed.', [
			'exit_code' => $exit_code,
			'output'    => implode( "\n", $output ),
		] );
	}

	public function label(): string {
		return 'Shell Command';
	}
}
