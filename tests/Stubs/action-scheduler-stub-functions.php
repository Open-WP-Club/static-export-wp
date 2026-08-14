<?php
/**
 * Global-namespace Action Scheduler function stubs.
 *
 * Loaded on demand (via require_once, NOT from bootstrap.php) by tests that
 * need to simulate Action Scheduler being installed. Must live outside any
 * namespace so ActionSchedulerBridge::has_action_scheduler()'s
 * function_exists('as_enqueue_async_action') check — which resolves against
 * the global namespace — finds them, while the rest of the suite (which
 * never requires this file) keeps exercising the wp_cron fallback path.
 */

declare(strict_types=1);

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	/**
	 * @param string  $hook  Action hook name.
	 * @param mixed[] $args  Arguments passed to the hook.
	 * @param string  $group Action Scheduler group.
	 * @return int Fake action ID.
	 */
	function as_enqueue_async_action( string $hook, array $args, string $group ): int {
		global $_as_calls;
		$_as_calls[] = [ 'method' => 'as_enqueue_async_action', 'hook' => $hook, 'args' => $args, 'group' => $group ];
		return 1;
	}
}

if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
	/**
	 * @param string  $hook  Action hook name.
	 * @param mixed[] $args  Arguments passed to the hook.
	 * @param string  $group Action Scheduler group.
	 */
	function as_unschedule_all_actions( string $hook, array $args, string $group ): void {
		global $_as_calls;
		$_as_calls[] = [ 'method' => 'as_unschedule_all_actions', 'hook' => $hook, 'args' => $args, 'group' => $group ];
	}
}

if ( ! function_exists( 'as_has_scheduled_action' ) ) {
	/**
	 * @param string  $hook  Action hook name.
	 * @param mixed[] $args  Arguments passed to the hook.
	 * @param string  $group Action Scheduler group.
	 * @return bool Always true in the stub.
	 */
	function as_has_scheduled_action( string $hook, array $args, string $group ): bool {
		global $_as_calls;
		$_as_calls[] = [ 'method' => 'as_has_scheduled_action', 'hook' => $hook, 'args' => $args, 'group' => $group ];
		return true;
	}
}
