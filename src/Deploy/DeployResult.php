<?php
/**
 * Immutable value object describing the outcome of a deploy attempt.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Deploy;

/**
 * Result of a single Deployer::deploy() call.
 */
final readonly class DeployResult {

	/**
	 * Constructor.
	 *
	 * @param bool   $success Whether the deploy succeeded.
	 * @param string $message Human-readable outcome message.
	 * @param array  $context Additional structured context (e.g. exit_code, output).
	 */
	private function __construct(
		public bool $success,
		public string $message,
		public array $context = array(),
	) {}

	/**
	 * Build a successful deploy result.
	 *
	 * @param string $message Human-readable success message.
	 * @param array  $context Additional structured context.
	 * @return self Successful deploy result.
	 */
	public static function ok( string $message, array $context = array() ): self {
		return new self( true, $message, $context );
	}

	/**
	 * Build a failed deploy result.
	 *
	 * @param string $message Human-readable failure message.
	 * @param array  $context Additional structured context.
	 * @return self Failed deploy result.
	 */
	public static function fail( string $message, array $context = array() ): self {
		return new self( false, $message, $context );
	}
}
