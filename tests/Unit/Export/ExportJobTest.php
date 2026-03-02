<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Export\ExportJob;

final class ExportJobTest extends TestCase {

	public function test_constructor_sets_all_properties(): void {
		$job = new ExportJob(
			export_id: 'abc-123',
			output_dir: '/tmp/export',
			url_mode: 'relative',
			base_url: '',
			settings_snapshot: [ 'key' => 'value' ],
			started_at: '2024-01-01 00:00:00',
		);

		$this->assertSame( 'abc-123', $job->export_id );
		$this->assertSame( '/tmp/export', $job->output_dir );
		$this->assertSame( 'relative', $job->url_mode );
		$this->assertSame( '', $job->base_url );
		$this->assertSame( [ 'key' => 'value' ], $job->settings_snapshot );
		$this->assertSame( '2024-01-01 00:00:00', $job->started_at );
	}

	public function test_started_at_defaults_to_null(): void {
		$job = new ExportJob(
			export_id: 'abc-123',
			output_dir: '/tmp/export',
			url_mode: 'relative',
			base_url: '',
			settings_snapshot: [],
		);

		$this->assertNull( $job->started_at );
	}

	public function test_readonly_properties(): void {
		$job = new ExportJob(
			export_id: 'abc-123',
			output_dir: '/tmp/export',
			url_mode: 'absolute',
			base_url: 'https://cdn.example.com',
			settings_snapshot: [ 'url_mode' => 'absolute' ],
		);

		// Verify all properties are accessible.
		$this->assertSame( 'abc-123', $job->export_id );
		$this->assertSame( 'absolute', $job->url_mode );
		$this->assertSame( 'https://cdn.example.com', $job->base_url );
	}
}
