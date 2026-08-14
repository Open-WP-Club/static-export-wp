<?php
/**
 * Lightweight debug logger for the plugin's export and background processes.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Utility;

/**
 * Writes leveled log messages to PHP's error log when WP_DEBUG is enabled.
 */
final class Logger {

	/**
	 * Log an informational message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Optional contextual data to include with the message.
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Optional contextual data to include with the message.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Optional contextual data to include with the message.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Write a leveled message to the PHP error log, if WP_DEBUG is enabled.
	 *
	 * @param string $level   The log level ('info', 'error', 'warning').
	 * @param string $message The message to log.
	 * @param array  $context Optional contextual data to include with the message.
	 */
	private function log( string $level, string $message, array $context ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '';
		error_log( sprintf( '[SEWP][%s] %s%s', strtoupper( $level ), $message, $context_str ) );
	}
}
