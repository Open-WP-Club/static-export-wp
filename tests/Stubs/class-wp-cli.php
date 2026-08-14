<?php
/**
 * Minimal WP-CLI stubs for unit testing StaticExportCommand.
 *
 * WP_CLI::error() mirrors real WP-CLI by halting execution — the stub
 * throws WP_CLI_ExitException instead of exiting the process, so tests
 * can assert on the "halted" path with expectException().
 */

declare(strict_types=1);

namespace {
	if ( ! class_exists( 'WP_CLI_ExitException' ) ) {
		class WP_CLI_ExitException extends \Exception {}
	}

	if ( ! class_exists( 'WP_CLI' ) ) {
		class WP_CLI {

			/** @var array<int, array{level: string, message: string}> Log of all WP_CLI:: calls. */
			public static array $_log = [];

			public static function error( string $message ): void {
				self::$_log[] = [ 'level' => 'error', 'message' => $message ];
				throw new WP_CLI_ExitException( $message );
			}

			public static function success( string $message ): void {
				self::$_log[] = [ 'level' => 'success', 'message' => $message ];
			}

			public static function warning( string $message ): void {
				self::$_log[] = [ 'level' => 'warning', 'message' => $message ];
			}

			public static function log( string $message ): void {
				self::$_log[] = [ 'level' => 'log', 'message' => $message ];
			}

			/**
			 * @param array<string, mixed> $assoc_args Command associative arguments.
			 */
			public static function confirm( string $question, array $assoc_args = [] ): void {
				self::$_log[] = [ 'level' => 'confirm', 'message' => $question ];
				// In tests, confirmation always "succeeds" (as if --yes was passed).
			}

			public static function add_command( string $name, mixed $callable ): void {
				self::$_log[] = [ 'level' => 'add_command', 'message' => $name ];
			}
		}
	}
}

namespace WP_CLI\Utils {
	if ( ! function_exists( 'WP_CLI\Utils\get_flag_value' ) ) {
		function get_flag_value( array $assoc_args, string $flag, mixed $default = null ): mixed {
			return $assoc_args[ $flag ] ?? $default;
		}
	}

	if ( ! function_exists( 'WP_CLI\Utils\make_progress_bar' ) ) {
		function make_progress_bar( string $message, int $count ): object {
			global $_wp_cli_progress_bars;
			$_wp_cli_progress_bars[] = [ 'message' => $message, 'count' => $count, 'ticks' => 0 ];

			return new class( count( $_wp_cli_progress_bars ) - 1 ) {
				public function __construct( private readonly int $index ) {}

				public function tick(): void {
					global $_wp_cli_progress_bars;
					++$_wp_cli_progress_bars[ $this->index ]['ticks'];
				}

				public function finish(): void {
					global $_wp_cli_progress_bars;
					$_wp_cli_progress_bars[ $this->index ]['finished'] = true;
				}
			};
		}
	}

	if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
		/**
		 * @param array<int, array<string, mixed>> $items  Rows to format.
		 * @param string[]                         $fields Field names to include.
		 */
		function format_items( string $format, array $items, array $fields ): void {
			global $_wp_cli_formatted_items;
			$_wp_cli_formatted_items = [ 'format' => $format, 'items' => $items, 'fields' => $fields ];
		}
	}
}
