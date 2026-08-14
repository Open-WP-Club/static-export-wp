<?php
/**
 * Runs the Pagefind CLI against a completed static export to build a search index.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Search;

use StaticExportWP\Core\Settings;
use StaticExportWP\Export\ExportJob;
use StaticExportWP\Utility\Logger;

/**
 * Invokes the Pagefind Node.js tool via npx to index an export's output directory.
 */
final class PagefindRunner {

	/**
	 * Construct the runner.
	 *
	 * @param Settings $settings Plugin settings, used to check whether Pagefind is enabled.
	 * @param Logger   $logger   Logger for recording progress and failures.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly Logger $logger,
	) {}

	/**
	 * Run Pagefind on the export output directory.
	 *
	 * Hooked to sewp_post_export_process.
	 *
	 * @param ExportJob $job The completed export job whose output directory should be indexed.
	 */
	public function run( ExportJob $job ): void {
		if ( ! $this->settings->get( 'pagefind_enabled', false ) ) {
			return;
		}

		// Check that npx is available.
		$npx_path = $this->find_npx();
		if ( null === $npx_path ) {
			$this->logger->warning( 'Pagefind: npx not found, skipping search index generation.' );
			return;
		}

		$output_dir = $job->output_dir;

		if ( ! is_dir( $output_dir ) ) {
			$this->logger->warning( 'Pagefind: output directory does not exist.', array( 'dir' => $output_dir ) );
			return;
		}

		// Security note: Both npx_path (from `which npx`) and output_dir
		// (admin-configured, same trust level as plugin installation) are escaped
		// via escapeshellarg. This follows the same pattern as ExportManager::run_deploy().
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		$command   = escapeshellarg( $npx_path ) . ' -y pagefind --site ' . escapeshellarg( $output_dir );
		$output    = array();
		$exit_code = 0;

		$this->logger->info( 'Pagefind: starting index generation.', array( 'command' => $command ) );

		exec( $command . ' 2>&1', $output, $exit_code );

		if ( 0 === $exit_code ) {
			$this->logger->info( 'Pagefind: search index generated successfully.' );
		} else {
			$this->logger->error(
				'Pagefind: index generation failed.',
				array(
					'exit_code' => $exit_code,
					'output'    => implode( "\n", $output ),
				)
			);
		}
	}

	/**
	 * Locate the npx binary.
	 */
	private function find_npx(): ?string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		$path = trim( (string) shell_exec( 'which npx 2>/dev/null' ) );
		return '' !== $path ? $path : null;
	}
}
