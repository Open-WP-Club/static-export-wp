<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Export\UrlRewriter;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class UrlRewriterTest extends TestCase {

	use WpStubHelpers;

	private UrlRewriter $rewriter;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->set_home_url( 'https://example.com' );

		// Use a real Settings instance backed by the WP options stub.
		$this->set_option( 'sewp_settings', [
			'url_mode' => 'relative',
			'base_url' => '',
		] );

		$settings       = new Settings();
		$this->rewriter = new UrlRewriter( $settings );
	}

	// url_to_path tests.

	public function test_url_to_path_root(): void {
		$this->assertSame( 'index.html', $this->rewriter->url_to_path( '/' ) );
	}

	public function test_url_to_path_page(): void {
		$this->assertSame( 'about/index.html', $this->rewriter->url_to_path( '/about/' ) );
	}

	public function test_url_to_path_nested(): void {
		$this->assertSame( 'blog/post/index.html', $this->rewriter->url_to_path( '/blog/post/' ) );
	}

	public function test_url_to_path_with_extension(): void {
		$this->assertSame( 'wp-content/style.css', $this->rewriter->url_to_path( '/wp-content/style.css' ) );
	}

	public function test_url_to_path_empty(): void {
		$this->assertSame( 'index.html', $this->rewriter->url_to_path( '' ) );
	}

	// rewrite skip tests.

	public function test_rewrite_skips_empty(): void {
		$this->assertSame( '', $this->rewriter->rewrite( '', 'https://example.com/' ) );
	}

	public function test_rewrite_skips_anchor(): void {
		$this->assertSame( '#section', $this->rewriter->rewrite( '#section', 'https://example.com/' ) );
	}

	public function test_rewrite_skips_data_uri(): void {
		$data = 'data:image/png;base64,abc123';
		$this->assertSame( $data, $this->rewriter->rewrite( $data, 'https://example.com/' ) );
	}

	public function test_rewrite_skips_javascript(): void {
		$js = 'javascript:void(0)';
		$this->assertSame( $js, $this->rewriter->rewrite( $js, 'https://example.com/' ) );
	}

	public function test_rewrite_skips_mailto(): void {
		$mailto = 'mailto:test@example.com';
		$this->assertSame( $mailto, $this->rewriter->rewrite( $mailto, 'https://example.com/' ) );
	}

	public function test_rewrite_skips_external(): void {
		$external = 'https://other-site.com/page';
		$this->assertSame( $external, $this->rewriter->rewrite( $external, 'https://example.com/' ) );
	}

	// Relative mode tests.

	public function test_rewrite_relative_same_directory(): void {
		$result = $this->rewriter->rewrite(
			'https://example.com/about/',
			'https://example.com/contact/',
			'relative',
			'',
		);
		$this->assertSame( '../about/index.html', $result );
	}

	public function test_rewrite_relative_parent_directory(): void {
		$result = $this->rewriter->rewrite(
			'https://example.com/',
			'https://example.com/blog/post/',
			'relative',
			'',
		);
		$this->assertSame( '../../index.html', $result );
	}

	// Absolute mode tests.

	public function test_rewrite_absolute_with_base_url(): void {
		$result = $this->rewriter->rewrite(
			'https://example.com/about/',
			'https://example.com/',
			'absolute',
			'https://cdn.example.com',
		);
		$this->assertSame( 'https://cdn.example.com/about/index.html', $result );
	}

	// Fragment preservation.

	public function test_rewrite_preserves_fragment(): void {
		$result = $this->rewriter->rewrite(
			'https://example.com/about/#team',
			'https://example.com/',
			'relative',
			'',
		);
		$this->assertStringContainsString( '#team', $result );
	}
}
