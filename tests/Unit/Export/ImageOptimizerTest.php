<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Export\ImageOptimizer;
use StaticExportWP\Utility\Logger;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class ImageOptimizerTest extends TestCase {

	use WpStubHelpers;

	private ImageOptimizer $optimizer;

	protected function setUp(): void {
		$this->reset_wp_state();
		// Use a real Logger -- it just calls error_log(), which is harmless in tests.
		$logger          = new Logger();
		$this->optimizer = new ImageOptimizer( 80, $logger );
	}

	public function test_is_optimizable_jpg(): void {
		$this->assertTrue( $this->optimizer->is_optimizable( 'photo.jpg' ) );
	}

	public function test_is_optimizable_jpeg(): void {
		$this->assertTrue( $this->optimizer->is_optimizable( 'photo.jpeg' ) );
	}

	public function test_is_optimizable_png(): void {
		$this->assertTrue( $this->optimizer->is_optimizable( 'icon.png' ) );
	}

	public function test_is_not_optimizable_gif(): void {
		$this->assertFalse( $this->optimizer->is_optimizable( 'animation.gif' ) );
	}

	public function test_is_not_optimizable_webp(): void {
		$this->assertFalse( $this->optimizer->is_optimizable( 'image.webp' ) );
	}

	public function test_is_not_optimizable_svg(): void {
		$this->assertFalse( $this->optimizer->is_optimizable( 'logo.svg' ) );
	}
}
