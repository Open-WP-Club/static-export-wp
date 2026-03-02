<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Background;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Background\PostPublishTrigger;
use StaticExportWP\Background\ProgressTracker;
use StaticExportWP\Core\Settings;
use StaticExportWP\Tests\Helpers\ReflectionHelper;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

/**
 * Tests for PostPublishTrigger.
 *
 * Uses real instances of all final classes, controlling behaviour through
 * the WP stub layer. To detect whether start_background() was triggered,
 * we check for observable side effects: cron events scheduled, progress
 * state changes, etc.
 */
final class PostPublishTriggerTest extends TestCase {

	use WpStubHelpers;

	private ProgressTracker $progress;
	private PostPublishTrigger $trigger;

	protected function setUp(): void {
		$this->reset_wp_state();

		$GLOBALS['wpdb'] = new \wpdb();

		$this->progress = new ProgressTracker();
		$settings       = new Settings();

		$export_manager = ReflectionHelper::buildRealExportManager(
			settings: $settings,
			progress: $this->progress,
		);

		$this->trigger = new PostPublishTrigger(
			$export_manager,
			$this->progress,
			$settings,
		);
	}

	public function test_ignores_non_publish_status(): void {
		$post = new \WP_Post();
		$this->trigger->handle( 'draft', 'publish', $post );

		// No export should have been triggered -- progress should be null.
		$this->assertNull( $this->progress->get() );
	}

	public function test_ignores_when_auto_export_disabled(): void {
		$this->set_option( 'sewp_settings', [
			'auto_export_on_publish' => false,
		] );

		$post = new \WP_Post();
		$this->trigger->handle( 'publish', 'draft', $post );

		$this->assertNull( $this->progress->get() );
	}

	public function test_ignores_excluded_post_type(): void {
		$this->set_option( 'sewp_settings', [
			'auto_export_on_publish' => true,
			'post_types'             => [ 'post', 'page' ],
		] );

		$post             = new \WP_Post();
		$post->post_type  = 'custom_cpt';
		$this->trigger->handle( 'publish', 'draft', $post );

		$this->assertNull( $this->progress->get() );
	}

	public function test_ignores_when_export_running(): void {
		$this->set_option( 'sewp_settings', [
			'auto_export_on_publish' => true,
			'post_types'             => [ 'post', 'page' ],
		] );

		// Mark an export as running.
		$this->progress->start( 'existing-export', 10 );

		$post = new \WP_Post();
		$this->trigger->handle( 'publish', 'draft', $post );

		// The existing export should remain unchanged -- no new export started.
		$data = $this->progress->get();
		$this->assertSame( 'existing-export', $data['export_id'] );
	}

	public function test_ignores_when_debounce_active(): void {
		global $_wp_transients;
		$_wp_transients['sewp_auto_export_debounce'] = 1;

		$this->set_option( 'sewp_settings', [
			'auto_export_on_publish' => true,
			'post_types'             => [ 'post', 'page' ],
		] );

		$post = new \WP_Post();
		$this->trigger->handle( 'publish', 'draft', $post );

		// Debounce should have prevented the export.
		// Progress may or may not be null since we didn't start one, but
		// the key is no NEW export was started.
		$this->assertNull( $this->progress->get() );
	}

	public function test_starts_background_export_on_valid_trigger(): void {
		$this->set_option( 'sewp_settings', [
			'auto_export_on_publish' => true,
			'post_types'             => [ 'post', 'page' ],
		] );

		$post = new \WP_Post();
		$this->trigger->handle( 'publish', 'draft', $post );

		// Verify that start_background() was called by checking observable effects:
		// 1. Progress should be set (start_background -> start -> progress.start)
		$data = $this->progress->get();
		$this->assertNotNull( $data, 'Expected progress to be started after triggering export' );
		$this->assertSame( 'running', $data['status'] );

		// 2. A cron event should have been scheduled.
		global $_wp_cron_events;
		$batch_events = array_filter( $_wp_cron_events, fn( $e ) => $e['hook'] === 'sewp_process_batch' );
		$this->assertNotEmpty( $batch_events, 'Expected a cron event for batch processing' );
	}
}
