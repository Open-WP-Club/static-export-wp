<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use StaticExportWP\Core\Deactivator;
use StaticExportWP\Tests\Helpers\WpStubHelpers;

final class DeactivatorTest extends TestCase {

	use WpStubHelpers;

	protected function setUp(): void {
		$this->reset_wp_state();
	}

	public function test_deactivate_clears_scheduled_batch_hook(): void {
		wp_schedule_single_event( time(), 'sewp_process_batch', [ 'exp-1' ] );

		Deactivator::deactivate();

		$this->assertFalse( wp_next_scheduled( 'sewp_process_batch', [ 'exp-1' ] ) );
	}

	public function test_deactivate_deletes_export_progress_option(): void {
		$this->set_option( 'sewp_export_progress', [ 'export_id' => 'exp-1', 'status' => 'running' ] );

		Deactivator::deactivate();

		$this->assertFalse( get_option( 'sewp_export_progress' ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_deactivate_unschedules_via_action_scheduler_when_available(): void {
		global $_as_calls;
		$_as_calls = [];

		require_once __DIR__ . '/../../Stubs/action-scheduler-stub-functions.php';

		$this->reset_wp_state();
		Deactivator::deactivate();

		$this->assertNotEmpty( $_as_calls );
		$this->assertSame( 'as_unschedule_all_actions', $_as_calls[0]['method'] );
		$this->assertSame( 'sewp_process_batch', $_as_calls[0]['hook'] );
	}
}
