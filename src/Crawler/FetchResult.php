<?php

declare(strict_types=1);

namespace StaticExportWP\Crawler;

final class FetchResult {

	public function __construct(
		public readonly string $url,
		public readonly int $http_status,
		public readonly string $content_type,
		public readonly string $body,
		public readonly array $headers,
		public readonly ?string $error = null,
	) {}

	public function is_success(): bool {
		return $this->http_status >= 200 && $this->http_status < 400 && null === $this->error;
	}

	public function is_html(): bool {
		return str_contains( $this->content_type, 'text/html' );
	}
}
