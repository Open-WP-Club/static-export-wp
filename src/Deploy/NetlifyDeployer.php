<?php

declare(strict_types=1);

namespace StaticExportWP\Deploy;

use StaticExportWP\Core\Settings;
use StaticExportWP\Export\ExportJob;
use StaticExportWP\Utility\Logger;

final class NetlifyDeployer implements Deployer {

	private const API_BASE = 'https://api.netlify.com/api/v1';

	public function __construct(
		private readonly Settings $settings,
		private readonly Logger $logger,
	) {}

	public function deploy( ExportJob $job ): DeployResult {
		$token   = $this->settings->get( 'deploy_netlify_token', '' );
		$site_id = $this->settings->get( 'deploy_netlify_site_id', '' );

		if ( '' === $token ) {
			return DeployResult::fail( 'No Netlify access token configured.' );
		}

		if ( '' === $site_id ) {
			return DeployResult::fail( 'No Netlify site ID configured.' );
		}

		$output_dir = $job->output_dir;

		if ( ! is_dir( $output_dir ) ) {
			return DeployResult::fail( 'Output directory does not exist.', [ 'dir' => $output_dir ] );
		}

		// 1. Build file manifest: path → SHA1.
		$files = $this->build_file_manifest( $output_dir );

		if ( empty( $files ) ) {
			return DeployResult::fail( 'No files found in output directory.' );
		}

		$this->logger->info( 'Netlify deploy: starting', [ 'files' => count( $files ) ] );

		// 2. Create deploy with file hashes.
		$deploy = $this->create_deploy( $site_id, $token, $files );

		if ( null === $deploy ) {
			return DeployResult::fail( 'Netlify deploy: failed to create deploy.' );
		}

		$deploy_id = $deploy['id'] ?? '';
		$required  = $deploy['required'] ?? [];

		$this->logger->info( 'Netlify deploy: created', [
			'deploy_id' => $deploy_id,
			'required'  => count( $required ),
			'total'     => count( $files ),
		] );

		// 3. Upload required files.
		$hash_to_paths = $this->build_hash_to_paths_map( $files );
		$upload_errors = 0;

		foreach ( $required as $hash ) {
			$path = $hash_to_paths[ $hash ] ?? null;

			if ( null === $path ) {
				++$upload_errors;
				continue;
			}

			$full_path = $output_dir . '/' . ltrim( $path, '/' );

			if ( ! file_exists( $full_path ) ) {
				$this->logger->warning( 'Netlify deploy: file not found', [ 'path' => $path ] );
				++$upload_errors;
				continue;
			}

			$uploaded = $this->upload_file( $deploy_id, $token, $path, $full_path );

			if ( ! $uploaded ) {
				$this->logger->warning( 'Netlify deploy: upload failed', [ 'path' => $path ] );
				++$upload_errors;
			}
		}

		$uploaded_count = count( $required ) - $upload_errors;

		if ( $upload_errors > 0 ) {
			$this->logger->warning( 'Netlify deploy: completed with errors', [
				'uploaded'     => $uploaded_count,
				'upload_errors' => $upload_errors,
			] );
		}

		$message = sprintf(
			'Netlify deploy completed. %d/%d files uploaded (%d already cached).',
			$uploaded_count,
			count( $files ),
			count( $files ) - count( $required ),
		);

		if ( $upload_errors > 0 ) {
			$message .= sprintf( ' %d upload errors.', $upload_errors );
		}

		return $upload_errors > 0 && $uploaded_count === 0
			? DeployResult::fail( $message, [ 'deploy_id' => $deploy_id ] )
			: DeployResult::ok( $message, [ 'deploy_id' => $deploy_id ] );
	}

	public function label(): string {
		return 'Netlify API';
	}

	/**
	 * Build a manifest of relative file paths to their SHA1 hashes.
	 *
	 * @return array<string, string> Map of "/path/file" => "sha1hash".
	 */
	private function build_file_manifest( string $output_dir ): array {
		$files    = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $output_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$full_path = $file->getPathname();
			$relative  = '/' . ltrim( str_replace( $output_dir, '', $full_path ), '/' );
			$hash      = sha1_file( $full_path );

			if ( false !== $hash ) {
				$files[ $relative ] = $hash;
			}
		}

		return $files;
	}

	/**
	 * Invert the file manifest to map hashes back to paths.
	 *
	 * @return array<string, string> Map of "sha1hash" => "/path/file".
	 */
	private function build_hash_to_paths_map( array $files ): array {
		$map = [];

		foreach ( $files as $path => $hash ) {
			// First path wins if multiple files share the same hash.
			if ( ! isset( $map[ $hash ] ) ) {
				$map[ $hash ] = $path;
			}
		}

		return $map;
	}

	/**
	 * Create a deploy on Netlify with the file digest.
	 *
	 * @return array|null The deploy response, or null on failure.
	 */
	private function create_deploy( string $site_id, string $token, array $files ): ?array {
		$url = self::API_BASE . '/sites/' . $site_id . '/deploys';

		$response = wp_remote_post( $url, [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( [ 'files' => $files ] ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Netlify deploy: API error', [ 'error' => $response->get_error_message() ] );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$this->logger->error( 'Netlify deploy: API returned error', [
				'status' => $code,
				'body'   => wp_remote_retrieve_body( $response ),
			] );
			return null;
		}

		return is_array( $body ) ? $body : null;
	}

	/**
	 * Upload a single file to a Netlify deploy.
	 */
	private function upload_file( string $deploy_id, string $token, string $path, string $full_path ): bool {
		$url = self::API_BASE . '/deploys/' . $deploy_id . '/files' . $path;

		$response = wp_remote_request( $url, [
			'method'  => 'PUT',
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/octet-stream',
			],
			'body'    => file_get_contents( $full_path ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}
}
