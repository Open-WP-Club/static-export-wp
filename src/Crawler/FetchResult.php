<?php
/**
 * Value object representing the outcome of fetching a single URL.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Crawler;

/**
 * Immutable result of an HTTP fetch performed by Fetcher or BatchFetcher.
 */
final readonly class FetchResult {

	/**
	 * Create a new fetch result.
	 *
	 * @param string      $url          The URL that was fetched.
	 * @param int         $http_status  The HTTP response status code, or 0 on transport failure.
	 * @param string      $content_type The response Content-Type header value.
	 * @param string      $body         The response body.
	 * @param array       $headers      The response headers, keyed by header name.
	 * @param string|null $error        Error message if the request failed, or null on success.
	 */
	public function __construct(
		public string $url,
		public int $http_status,
		public string $content_type,
		public string $body,
		public array $headers,
		public ?string $error = null,
	) {}

	/**
	 * Determine whether the fetch resulted in a successful (2xx/3xx) response.
	 *
	 * @return bool True if the fetch succeeded, false otherwise.
	 */
	public function is_success(): bool {
		return $this->http_status >= 200 && $this->http_status < 400 && null === $this->error;
	}

	/**
	 * Determine whether the response body is HTML content.
	 *
	 * @return bool True if the content type indicates HTML, false otherwise.
	 */
	public function is_html(): bool {
		return str_contains( $this->content_type, 'text/html' );
	}
}
