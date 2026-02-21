<?php

declare(strict_types=1);

namespace StaticExportWP\Utility;

final class Logger {

	public function info( string $message, array $context = [] ): void {
		$this->log( 'info', $message, $context );
	}

	public function error( string $message, array $context = [] ): void {
		$this->log( 'error', $message, $context );
	}

	public function warning( string $message, array $context = [] ): void {
		$this->log( 'warning', $message, $context );
	}

	private function log( string $level, string $message, array $context ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$context_str = ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '';
		error_log( sprintf( '[SEWP][%s] %s%s', strtoupper( $level ), $message, $context_str ) );
	}
}
