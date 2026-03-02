<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Settings;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class SettingsTest extends TestCase {

	use WpStubHelpers;

	private Settings $settings;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->settings = new Settings();
	}

	public function test_defaults_has_all_keys(): void {
		$defaults = $this->settings->defaults();

		$expected_keys = [
			'output_dir', 'url_mode', 'base_url', 'export_mode', 'selected_urls',
			'post_types', 'rate_limit', 'batch_size', 'max_retries', 'timeout',
			'extra_urls', 'exclude_patterns', 'deploy_method', 'deploy_command',
			'deploy_git_remote', 'deploy_git_branch', 'deploy_git_token',
			'deploy_netlify_token', 'deploy_netlify_site_id', 'notify_enabled',
			'notify_email', 'webhook_url', 'webhook_secret', 'webhook_events',
			'pagination_depth', 'incremental_export', 'pagefind_enabled',
			'auto_export_on_publish', 'image_optimization', 'image_quality',
			'redirects_content', 'headers_content', 'minify_css', 'minify_js',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $defaults, "Missing key: {$key}" );
		}
	}

	public function test_defaults_url_mode_is_relative(): void {
		$this->assertSame( 'relative', $this->settings->defaults()['url_mode'] );
	}

	public function test_defaults_batch_size_is_10(): void {
		$this->assertSame( 10, $this->settings->defaults()['batch_size'] );
	}

	public function test_get_all_merges_saved_with_defaults(): void {
		$this->set_option( Settings::OPTION_KEY, [ 'url_mode' => 'absolute', 'base_url' => 'https://cdn.test' ] );

		$all = $this->settings->get_all();

		$this->assertSame( 'absolute', $all['url_mode'] );
		$this->assertSame( 'https://cdn.test', $all['base_url'] );
		// Defaults are preserved.
		$this->assertSame( 10, $all['batch_size'] );
	}

	public function test_get_returns_specific_key(): void {
		$this->set_option( Settings::OPTION_KEY, [ 'batch_size' => 25 ] );

		$this->assertSame( 25, $this->settings->get( 'batch_size' ) );
	}

	public function test_get_returns_default_for_missing(): void {
		$this->assertSame( 'fallback', $this->settings->get( 'nonexistent_key', 'fallback' ) );
	}

	public function test_update_saves_sanitized_values(): void {
		$this->settings->update( [ 'url_mode' => 'absolute', 'batch_size' => 50 ] );

		$saved = $this->settings->get_all();
		$this->assertSame( 'absolute', $saved['url_mode'] );
		$this->assertSame( 50, $saved['batch_size'] );
	}

	public function test_sanitize_clamps_rate_limit_to_zero(): void {
		$result = $this->settings->sanitize( [ 'rate_limit' => -5 ] );
		$this->assertSame( 0, $result['rate_limit'] );
	}

	public function test_sanitize_clamps_batch_size_min(): void {
		$result = $this->settings->sanitize( [ 'batch_size' => 0 ] );
		$this->assertSame( 1, $result['batch_size'] );
	}

	public function test_sanitize_clamps_batch_size_max(): void {
		$result = $this->settings->sanitize( [ 'batch_size' => 200 ] );
		$this->assertSame( 100, $result['batch_size'] );
	}

	public function test_sanitize_clamps_max_retries(): void {
		$result = $this->settings->sanitize( [ 'max_retries' => 50 ] );
		$this->assertSame( 10, $result['max_retries'] );
	}

	public function test_sanitize_clamps_timeout(): void {
		$result = $this->settings->sanitize( [ 'timeout' => 1 ] );
		$this->assertSame( 5, $result['timeout'] );

		$result = $this->settings->sanitize( [ 'timeout' => 999 ] );
		$this->assertSame( 120, $result['timeout'] );
	}

	public function test_sanitize_validates_url_mode_enum(): void {
		$result = $this->settings->sanitize( [ 'url_mode' => 'invalid' ] );
		$this->assertSame( 'relative', $result['url_mode'] );
	}

	public function test_sanitize_validates_export_mode_enum(): void {
		$result = $this->settings->sanitize( [ 'export_mode' => 'invalid' ] );
		$this->assertSame( 'full', $result['export_mode'] );
	}

	public function test_sanitize_validates_deploy_method_enum(): void {
		$result = $this->settings->sanitize( [ 'deploy_method' => 'invalid' ] );
		$this->assertSame( 'none', $result['deploy_method'] );

		$result = $this->settings->sanitize( [ 'deploy_method' => 'git' ] );
		$this->assertSame( 'git', $result['deploy_method'] );
	}

	public function test_sanitize_casts_booleans(): void {
		$result = $this->settings->sanitize( [ 'notify_enabled' => 1, 'pagefind_enabled' => 0 ] );
		$this->assertTrue( $result['notify_enabled'] );
		$this->assertFalse( $result['pagefind_enabled'] );
	}

	public function test_sanitize_filters_webhook_events(): void {
		$result = $this->settings->sanitize( [ 'webhook_events' => [ 'completed', 'invalid', 'failed' ] ] );
		$this->assertSame( [ 'completed', 'failed' ], $result['webhook_events'] );
	}
}
