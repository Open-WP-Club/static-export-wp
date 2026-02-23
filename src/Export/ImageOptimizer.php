<?php

declare(strict_types=1);

namespace StaticExportWP\Export;

use StaticExportWP\Utility\Logger;

final class ImageOptimizer {

	private const OPTIMIZABLE_EXTENSIONS = [ 'jpg', 'jpeg', 'png' ];

	/** @var array<string, string> Original relative path => WebP relative path. */
	private array $replacements = [];

	public function __construct(
		private readonly int $quality,
		private readonly Logger $logger,
	) {}

	/**
	 * Convert an image file to WebP using WordPress's image editor (GD/Imagick).
	 *
	 * Deletes the original on success. Returns the new path, or false on failure.
	 */
	public function optimize( string $file_path ): string|false {
		$editor = wp_get_image_editor( $file_path );

		if ( is_wp_error( $editor ) ) {
			$this->logger->warning( 'Image editor unavailable', [
				'file'  => $file_path,
				'error' => $editor->get_error_message(),
			] );
			return false;
		}

		$editor->set_quality( $this->quality );

		$webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file_path );

		$saved = $editor->save( $webp_path, 'image/webp' );

		if ( is_wp_error( $saved ) ) {
			$this->logger->warning( 'WebP conversion failed', [
				'file'  => $file_path,
				'error' => $saved->get_error_message(),
			] );
			return false;
		}

		// Delete the original now that the WebP exists.
		wp_delete_file( $file_path );

		return $webp_path;
	}

	/**
	 * Convert an asset and track the original→WebP mapping for later HTML rewriting.
	 *
	 * Handles the cross-batch/shared-image case: if the original is already gone
	 * but the .webp version exists on disk, just record the mapping.
	 */
	public function optimize_and_track( string $output_dir, string $relative_path ): void {
		$absolute_path = trailingslashit( $output_dir ) . $relative_path;
		$webp_relative = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $relative_path );

		// Already processed in a previous batch/page?
		if ( isset( $this->replacements[ $relative_path ] ) ) {
			return;
		}

		// Original exists — convert it.
		if ( file_exists( $absolute_path ) ) {
			$result = $this->optimize( $absolute_path );

			if ( false === $result ) {
				return; // Graceful degradation: keep original, no mapping.
			}

			$this->replacements[ $relative_path ] = $webp_relative;
			return;
		}

		// Original gone but WebP exists (cross-batch or shared image).
		$webp_absolute = trailingslashit( $output_dir ) . $webp_relative;
		if ( file_exists( $webp_absolute ) ) {
			$this->replacements[ $relative_path ] = $webp_relative;
		}
	}

	/**
	 * Get all original→WebP path mappings accumulated so far.
	 *
	 * @return array<string, string> Original relative path => WebP relative path.
	 */
	public function get_replacements(): array {
		return $this->replacements;
	}

	/**
	 * Whether the given path is an optimizable image (JPEG or PNG).
	 */
	public function is_optimizable( string $path ): bool {
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return in_array( $extension, self::OPTIMIZABLE_EXTENSIONS, true );
	}
}
