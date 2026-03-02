<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Helpers;

/**
 * Non-final fake of ActionSchedulerBridge for tests.
 *
 * Records calls so tests can verify scheduling behaviour without
 * needing the real Action Scheduler or wp_cron stubs.
 */
class FakeActionSchedulerBridge {

	/** @var array<string, int> Call counters keyed by method name. */
	public array $calls = [];

	/** @var array<string, list<array>> Call arguments keyed by method name. */
	public array $call_args = [];

	/** @var bool Value returned by has_pending(). */
	public bool $has_pending = false;

	public function schedule_batch( string $export_id ): void {
		$this->record( 'schedule_batch', [ $export_id ] );
	}

	public function unschedule_all(): void {
		$this->record( 'unschedule_all' );
	}

	public function has_pending( string $export_id ): bool {
		$this->record( 'has_pending', [ $export_id ] );
		return $this->has_pending;
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
