<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Plugin;
use StaticExportWP\Export\ExportManager;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

/**
 * Tests for the Plugin composition-root singleton.
 *
 * Plugin::instance() is a process-wide static singleton, and boot() has
 * far-reaching side effects (constructs the whole service graph, registers
 * every WordPress hook). Both tests here run in isolated processes so they
 * neither pollute nor are polluted by the rest of the suite.
 */
final class PluginTest extends TestCase {

	use WpStubHelpers;

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_instance_returns_the_same_object(): void {
		$a = Plugin::instance();
		$b = Plugin::instance();

		$this->assertSame( $a, $b );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_boot_wires_services_and_registers_hooks(): void {
		$this->reset_wp_state();
		$GLOBALS['wpdb'] = new \wpdb();

		if ( ! defined( 'SEWP_FILE' ) ) {
			define( 'SEWP_FILE', __DIR__ . '/../../../static-export-wp.php' );
		}

		// Skip AdminPage registration (would need SEWP_PATH/SEWP_URL for asset enqueuing).
		$this->set_is_admin( false );

		Plugin::instance()->boot();

		$this->assertInstanceOf( ExportManager::class, Plugin::instance()->export_manager() );

		global $_wp_action_hooks;
		$hooks = array_column( $_wp_action_hooks, 'hook' );

		$this->assertContains( 'sewp_process_batch', $hooks );
		$this->assertContains( 'sewp_export_finalized', $hooks );
		$this->assertContains( 'sewp_post_export_process', $hooks );

		global $_wp_rest_routes;
		// register_routes() itself is only wired via the rest_api_init hook, not called directly --
		// confirm that wiring happened instead of the routes being registered eagerly.
		$this->assertSame( [], $_wp_rest_routes );
		$this->assertContains( 'rest_api_init', $hooks );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_boot_upgrades_db_schema_when_version_is_outdated(): void {
		$this->reset_wp_state();
		$GLOBALS['wpdb'] = new \wpdb();

		if ( ! defined( 'SEWP_FILE' ) ) {
			define( 'SEWP_FILE', __DIR__ . '/../../../static-export-wp.php' );
		}

		$this->set_is_admin( false );
		// No 'sewp_db_version' option set -- defaults to '0', which is older
		// than Schema::DB_VERSION, so boot() must run the schema upgrade.

		Plugin::instance()->boot();

		global $_wp_dbdelta_calls;
		$this->assertNotEmpty( $_wp_dbdelta_calls );
	}
}
