<?php

declare(strict_types=1);

namespace StaticExportWP\Crawler;

final readonly class FetchResult {

	public function __construct(
		public string $url,
		public int $http_status,
		public string $content_type,
		public string $body,
		public array $headers,
		public ?string $error = null,
	) {}

	public function is_success(): bool {
		return $this->http_status >= 200 && $this->http_status < 400 && null === $this->error;
	}

	public function is_html(): bool {
		return str_contains( $this->content_type, 'text/html' );
	}
}
