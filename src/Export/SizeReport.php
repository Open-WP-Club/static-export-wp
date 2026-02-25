<?php

declare(strict_types=1);

namespace StaticExportWP\Export;

final class SizeReport {

	private const array CATEGORY_MAP = [
		// HTML.
		'html' => 'html',
		'htm'  => 'html',
		// CSS.
		'css' => 'css',
		// JS.
		'js' => 'js',
		// Images.
		'jpg'  => 'images',
		'jpeg' => 'images',
		'png'  => 'images',
		'gif'  => 'images',
		'svg'  => 'images',
		'webp' => 'images',
		'ico'  => 'images',
		'avif' => 'images',
		// Fonts.
		'woff'  => 'fonts',
		'woff2' => 'fonts',
		'ttf'   => 'fonts',
		'eot'   => 'fonts',
	];

	/**
	 * Scan an output directory and return file sizes grouped by category.
	 *
	 * @param string $output_dir Absolute path to the export output directory.
	 * @return array{html: int, css: int, js: int, images: int, fonts: int, other: int, total: int}
	 */
	public static function scan( string $output_dir ): array {
		$sizes = [
			'html'   => 0,
			'css'    => 0,
			'js'     => 0,
			'images' => 0,
			'fonts'  => 0,
			'other'  => 0,
			'total'  => 0,
		];

		if ( ! is_dir( $output_dir ) ) {
			return $sizes;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $output_dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY,
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$size      = $file->getSize();
			$extension = strtolower( $file->getExtension() );
			$category  = self::CATEGORY_MAP[ $extension ] ?? 'other';

			$sizes[ $category ] += $size;
			$sizes['total']     += $size;
		}

		return $sizes;
	}
}
