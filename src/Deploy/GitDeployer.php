<?php
/**
 * Deploys exported static sites by pushing them to a Git remote.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Deploy;

use StaticExportWP\Core\Settings;
use StaticExportWP\Export\ExportJob;
use StaticExportWP\Utility\Logger;

/**
 * Initializes a throwaway Git repository in the output directory and force
 * pushes it to a configured remote branch (e.g. for GitHub Pages).
 */
final class GitDeployer implements Deployer {

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
	 * Push the export job's output directory to the configured Git remote.
	 *
	 * @param ExportJob $job Export job whose output_dir will be pushed.
	 * @return DeployResult Result of the deploy attempt.
	 */
	public function deploy( ExportJob $job ): DeployResult {
		$remote = $this->settings->get( 'deploy_git_remote', '' );
		$token  = $this->settings->get( 'deploy_git_token', '' );
		$branch = $this->settings->get( 'deploy_git_branch', 'main' );

		if ( '' === $remote ) {
			return DeployResult::fail( 'No Git remote URL configured.' );
		}

		if ( '' === $branch ) {
			$branch = 'main';
		}

		$git_path = $this->find_git();
		if ( null === $git_path ) {
			return DeployResult::fail( 'Git is not installed or not found in PATH.' );
		}

		$auth_url = $this->build_authenticated_url( $remote, $token );
		if ( null === $auth_url ) {
			return DeployResult::fail( 'Git remote must be an HTTPS URL.', array( 'remote' => $remote ) );
		}

		$output_dir = $job->output_dir;

		if ( ! is_dir( $output_dir ) ) {
			return DeployResult::fail( 'Output directory does not exist.', array( 'dir' => $output_dir ) );
		}

		// Create .nojekyll for GitHub Pages compatibility.
		$nojekyll = $output_dir . '/.nojekyll';
		if ( ! file_exists( $nojekyll ) ) {
			file_put_contents( $nojekyll, '' );
		}

		$this->logger->info(
			'Git deploy: starting push',
			array(
				'remote' => $remote,
				'branch' => $branch,
			)
		);

		$git = escapeshellarg( $git_path );
		$dir = escapeshellarg( $output_dir );

		// Security note: This is a PHP plugin (not JS/Node). All arguments are
		// escaped via escapeshellarg(). The git binary path comes from `which git`.
		// Deploy settings are admin-configured (manage_options capability) — same
		// trust level as plugin installation. This follows the same pattern as the
		// existing CommandDeployer and PagefindRunner.
		$commands = array(
			"{$git} init {$dir}",
			"{$git} -C {$dir} config user.email " . escapeshellarg( 'static-export-wp@localhost' ),
			"{$git} -C {$dir} config user.name " . escapeshellarg( 'Static Export WP' ),
			"{$git} -C {$dir} add -A",
			"{$git} -C {$dir} commit -m " . escapeshellarg( 'Deploy static export ' . gmdate( 'Y-m-d H:i:s' ) ),
			"{$git} -C {$dir} push --force " . escapeshellarg( $auth_url ) . ' HEAD:refs/heads/' . escapeshellarg( $branch ),
		);

		foreach ( $commands as $command ) {
			$output    = array();
			$exit_code = 0;

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			exec( $command . ' 2>&1', $output, $exit_code );

			if ( 0 !== $exit_code ) {
				$this->cleanup_git_dir( $output_dir );
				$safe_output = $this->sanitize_output( implode( "\n", $output ), $token );

				$this->logger->error(
					'Git deploy: command failed',
					array(
						'exit_code' => $exit_code,
						'output'    => $safe_output,
					)
				);

				return DeployResult::fail(
					'Git deploy failed.',
					array(
						'exit_code' => $exit_code,
						'output'    => $safe_output,
					)
				);
			}
		}

		$this->cleanup_git_dir( $output_dir );
		$this->logger->info( 'Git deploy: push completed successfully' );

		return DeployResult::ok(
			'Git push completed successfully.',
			array(
				'remote' => $remote,
				'branch' => $branch,
			)
		);
	}

	/**
	 * Human-readable label for this deploy method.
	 *
	 * @return string Deploy method label.
	 */
	public function label(): string {
		return 'Git Push';
	}

	/**
	 * Build an authenticated HTTPS URL with the token embedded.
	 *
	 * Token is injected at push time only — never stored in git config.
	 *
	 * @param string $remote Git remote URL.
	 * @param string $token  Access token to embed, or empty string for none.
	 * @return string|null Authenticated URL, or null when $remote is not HTTPS.
	 */
	private function build_authenticated_url( string $remote, string $token ): ?string {
		if ( ! str_starts_with( $remote, 'https://' ) ) {
			return null;
		}

		if ( '' === $token ) {
			return $remote;
		}

		// Insert x-access-token:TOKEN@ after the https:// prefix.
		$without_scheme = substr( $remote, 8 );

		// Strip any existing credentials.
		if ( str_contains( $without_scheme, '@' ) ) {
			$without_scheme = substr( $without_scheme, (int) strpos( $without_scheme, '@' ) + 1 );
		}

		return 'https://x-access-token:' . $token . '@' . $without_scheme;
	}

	/**
	 * Remove the .git directory from the output folder.
	 *
	 * Prevents token leakage via reflog and keeps the output dir clean.
	 *
	 * @param string $output_dir Export output directory containing the .git folder.
	 */
	private function cleanup_git_dir( string $output_dir ): void {
		$git_dir = $output_dir . '/.git';

		if ( ! is_dir( $git_dir ) ) {
			return;
		}

		$this->remove_directory( $git_dir );
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @param string $dir Directory path to remove.
	 */
	private function remove_directory( string $dir ): void {
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $dir );
	}

	/**
	 * Strip the access token from output to prevent leakage in logs.
	 *
	 * @param string $output Raw command output.
	 * @param string $token  Access token to redact, or empty string for none.
	 * @return string Output with any occurrence of the token replaced by '***'.
	 */
	private function sanitize_output( string $output, string $token ): string {
		if ( '' === $token ) {
			return $output;
		}

		return str_replace( $token, '***', $output );
	}

	/**
	 * Locate the git binary.
	 *
	 * @return string|null Absolute path to the git binary, or null if not found.
	 */
	private function find_git(): ?string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		$path = trim( (string) shell_exec( 'which git 2>/dev/null' ) );
		return '' !== $path ? $path : null;
	}
}
