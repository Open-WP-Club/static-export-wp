<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Schema;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class SchemaTest extends TestCase {

	use WpStubHelpers;

	protected function setUp(): void {
		$this->reset_wp_state();
		$GLOBALS['wpdb'] = new \wpdb();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
	}

	public function test_create_tables_runs_dbdelta_with_all_expected_tables(): void {
		Schema::create_tables();

		global $_wp_dbdelta_calls;
		$this->assertCount( 1, $_wp_dbdelta_calls );

		$sql = $_wp_dbdelta_calls[0];
		$this->assertStringContainsString( 'CREATE TABLE wp_sewp_crawl_queue', $sql );
		$this->assertStringContainsString( 'CREATE TABLE wp_sewp_export_log', $sql );
		$this->assertStringContainsString( 'CREATE TABLE wp_sewp_content_hashes', $sql );
	}

	public function test_create_tables_records_db_version(): void {
		Schema::create_tables();

		$this->assertSame( Schema::DB_VERSION, get_option( 'sewp_db_version' ) );
	}
}
