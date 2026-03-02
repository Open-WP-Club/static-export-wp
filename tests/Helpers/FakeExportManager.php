<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Helpers;

use StaticExportWP\Export\ExportJob;

/**
 * Non-final fake of ExportManager for tests that need to verify interactions.
 *
 * Since the real ExportManager is `final` and PHPUnit 11 cannot mock it,
 * we inject this lightweight stand-in via Reflection. It records calls
 * so tests can assert against them.
 */
class FakeExportManager {

	/** @var ExportJob|null Value returned by get_current_job(). */
	public ?ExportJob $current_job = null;

	/** @var ExportJob|null Value returned by start_background(). */
	public ?ExportJob $background_job = null;

	/** @var array<string, int> Call counters keyed by method name. */
	public array $calls = [];

	/** @var array<string, list<array>> Call arguments keyed by method name. */
	public array $call_args = [];

	public function get_current_job(): ?ExportJob {
		$this->record( 'get_current_job' );
		return $this->current_job;
	}

	public function start_background( array $overrides = [] ): ExportJob {
		$this->record( 'start_background', [ $overrides ] );
		return $this->background_job ?? new ExportJob( 'fake-id', '/tmp', 'relative', '', [] );
	}

	public function process_batch( ExportJob $job, array $queue_items ): void {
		$this->record( 'process_batch', [ $job, $queue_items ] );
	}

	public function finalize( ExportJob $job ): void {
		$this->record( 'finalize', [ $job ] );
	}

	public function cancel( string $export_id ): void {
		$this->record( 'cancel', [ $export_id ] );
	}

	// ── Assertion helpers ──────────────────────────────────────────────────

	public function call_count( string $method ): int {
		return $this->calls[ $method ] ?? 0;
	}

	public function was_called( string $method ): bool {
		return ( $this->calls[ $method ] ?? 0 ) > 0;
	}

	public function was_not_called( string $method ): bool {
		return ( $this->calls[ $method ] ?? 0 ) === 0;
	}

	private function record( string $method, array $args = [] ): void {
		$this->calls[ $method ]    = ( $this->calls[ $method ] ?? 0 ) + 1;
		$this->call_args[ $method ] ??= [];
		$this->call_args[ $method ][] = $args;
	}
}
