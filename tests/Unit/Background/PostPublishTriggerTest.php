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

	// two rapid publishes must not start two exports.

	public function test_second_call_blocked_by_debounce_after_first_fires(): void {
		$this->set_option( 'sewp_settings', [
			'auto_export_on_publish' => true,
			'post_types'             => [ 'post', 'page' ],
		] );

		$post = new \WP_Post();

		// First publish — should start an export and set the debounce transient.
		$this->trigger->handle( 'publish', 'draft', $post );
		$first_export = $this->progress->get();
		$this->assertNotNull( $first_export, 'First publish should start an export' );
		$first_export_id = $first_export['export_id'];

		// Simulate the export finishing so is_running() returns false again.
		$this->progress->finish( $first_export_id, 'completed' );
		$this->assertFalse( $this->progress->is_running() );

		// Second publish arrives within the debounce window — debounce transient is still set.
		// A new export must NOT start.
		$this->trigger->handle( 'publish', 'draft', $post );
		$after_second = $this->progress->get();

		// Progress should reflect the completed first export, not a new running one.
		$this->assertNotSame( 'running', $after_second['status'] ?? null,
			'Second publish within debounce window must not start a new export' );
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
