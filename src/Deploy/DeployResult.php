<?php

declare(strict_types=1);

namespace StaticExportWP\Deploy;

final class DeployResult {

	private function __construct(
		public readonly bool $success,
		public readonly string $message,
		public readonly array $context = [],
	) {}

	public static function ok( string $message, array $context = [] ): self {
		return new self( true, $message, $context );
	}

	public static function fail( string $message, array $context = [] ): self {
		return new self( false, $message, $context );
	}
}
