<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Admin\AdminPage;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

/**
 * Tests for AdminPage.
 *
 * enqueue_assets()/handle_download()'s success path and get_preview_url()
 * go through the StaticExportWP\Core\Plugin singleton and (for downloads)
 * a raw exit; they're integration-level and are exercised manually / by the
 * `run` skill rather than here. This covers the parts with real branching
 * logic that are safe to invoke directly under PHPUnit.
 */
final class AdminPageTest extends TestCase {

	use WpStubHelpers;

	private AdminPage $admin_page;

	protected function setUp(): void {
		$this->reset_wp_state();
		$this->admin_page = new AdminPage();
	}

	public function test_render_outputs_mount_point(): void {
		ob_start();
		$this->admin_page->render();
		$output = ob_get_clean();

		$this->assertSame( '<div id="sewp-admin-root"></div>', $output );
	}

	public function test_register_hooks_admin_menu(): void {
		$this->admin_page->register();

		global $_wp_action_hooks;
		$hooks = array_column( $_wp_action_hooks, 'hook' );

		$this->assertContains( 'admin_menu', $hooks );
	}

	public function test_register_hooks_download_handler(): void {
		$this->admin_page->register();

		global $_wp_action_hooks;
		$registration = array_values( array_filter(
			$_wp_action_hooks,
			fn( $h ) => 'admin_post_sewp_download_export' === $h['hook'],
		) )[0] ?? null;

		$this->assertNotNull( $registration, 'Expected admin_post_sewp_download_export to be registered' );
		$this->assertSame( [ $this->admin_page, 'handle_download' ], $registration['callback'] );
	}

	public function test_handle_download_denies_without_manage_options(): void {
		$this->set_user_can( 'manage_options', false );

		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'You do not have permission to download exports.' );

		$this->admin_page->handle_download();
	}
}
