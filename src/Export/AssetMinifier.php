<?php

declare(strict_types=1);

namespace StaticExportWP\Export;

use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use StaticExportWP\Utility\Logger;

final class AssetMinifier {

	public function __construct(
		private readonly Logger $logger,
	) {}

	/**
	 * Minify a CSS file in-place.
	 */
	public function minify_css( string $file_path ): bool {
		$content = file_get_contents( $file_path );

		if ( false === $content ) {
			$this->logger->warning( 'AssetMinifier: could not read CSS file', [ 'file' => $file_path ] );
			return false;
		}

		try {
			$minifier = new CSS( $content );
			$minified = $minifier->minify();
		} catch ( \Throwable $e ) {
			$this->logger->warning( 'AssetMinifier: CSS minification failed', [
				'file'  => $file_path,
				'error' => $e->getMessage(),
			] );
			return false;
		}

		if ( false === file_put_contents( $file_path, $minified ) ) {
			$this->logger->warning( 'AssetMinifier: could not write minified CSS', [ 'file' => $file_path ] );
			return false;
		}

		return true;
	}

	/**
	 * Minify a JS file in-place.
	 */
	public function minify_js( string $file_path ): bool {
		$content = file_get_contents( $file_path );

		if ( false === $content ) {
			$this->logger->warning( 'AssetMinifier: could not read JS file', [ 'file' => $file_path ] );
			return false;
		}

		try {
			$minifier = new JS( $content );
			$minified = $minifier->minify();
		} catch ( \Throwable $e ) {
			$this->logger->warning( 'AssetMinifier: JS minification failed', [
				'file'  => $file_path,
				'error' => $e->getMessage(),
			] );
			return false;
		}

		if ( false === file_put_contents( $file_path, $minified ) ) {
			$this->logger->warning( 'AssetMinifier: could not write minified JS', [ 'file' => $file_path ] );
			return false;
		}

		return true;
	}

	/**
	 * Dispatch minification by file extension.
	 *
	 * @param string $file_path   Absolute path to the asset file.
	 * @param bool   $css_enabled Whether CSS minification is enabled.
	 * @param bool   $js_enabled  Whether JS minification is enabled.
	 */
	public function minify_asset( string $file_path, bool $css_enabled, bool $js_enabled ): bool {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		return match ( $extension ) {
			'css'  => $css_enabled ? $this->minify_css( $file_path ) : false,
			'js'   => $js_enabled ? $this->minify_js( $file_path ) : false,
			default => false,
		};
	}
}
