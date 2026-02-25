<?php

declare(strict_types=1);

namespace StaticExportWP\Deploy;

final readonly class DeployResult {

	private function __construct(
		public bool $success,
		public string $message,
		public array $context = [],
	) {}

	public static function ok( string $message, array $context = [] ): self {
		return new self( true, $message, $context );
	}

	public static function fail( string $message, array $context = [] ): self {
		return new self( false, $message, $context );
	}
}
