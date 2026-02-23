<?php

declare(strict_types=1);

namespace StaticExportWP\Background;

use StaticExportWP\Core\Settings;
use StaticExportWP\Export\ExportManager;

final class PostPublishTrigger {

	private const DEBOUNCE_KEY     = 'sewp_auto_export_debounce';
	private const DEBOUNCE_SECONDS = 30;

	public function __construct(
		private readonly ExportManager $export_manager,
		private readonly ProgressTracker $progress,
		private readonly Settings $settings,
	) {}

	public function register(): void {
		add_action( 'transition_post_status', [ $this, 'handle' ], 10, 3 );
	}

	/**
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public function handle( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'publish' !== $new_status ) {
			return;
		}

		if ( ! $this->settings->get( 'auto_export_on_publish', false ) ) {
			return;
		}

		$post_types = (array) $this->settings->get( 'post_types', [ 'post', 'page' ] );
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		if ( $this->progress->is_running() ) {
			return;
		}

		if ( get_transient( self::DEBOUNCE_KEY ) ) {
			return;
		}
		set_transient( self::DEBOUNCE_KEY, 1, self::DEBOUNCE_SECONDS );

		$this->export_manager->start_background();
	}
}
