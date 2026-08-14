<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Activator;
use StaticExportWP\Core\Settings;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class ActivatorTest extends TestCase {

	use WpStubHelpers;

	protected function setUp(): void {
		$this->reset_wp_state();
		$GLOBALS['wpdb'] = new \wpdb();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
	}

	public function test_activate_creates_database_tables(): void {
		Activator::activate();

		global $_wp_dbdelta_calls;
		$this->assertCount( 1, $_wp_dbdelta_calls );
	}

	public function test_activate_seeds_default_settings_when_none_exist(): void {
		Activator::activate();

		$saved = get_option( Settings::OPTION_KEY );
		$this->assertIsArray( $saved );
		$this->assertSame( ( new Settings() )->defaults(), $saved );
	}

	public function test_activate_does_not_overwrite_existing_settings(): void {
		$this->set_option( Settings::OPTION_KEY, [ 'output_dir' => '/custom/path' ] );

		Activator::activate();

		$saved = get_option( Settings::OPTION_KEY );
		$this->assertSame( [ 'output_dir' => '/custom/path' ], $saved );
	}
}
