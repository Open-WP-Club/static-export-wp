<?php

declare(strict_types=1);

namespace StaticExportWP\Crawler;

use StaticExportWP\Core\Settings;

/**
 * Parallel HTTP fetcher using WP's Requests library (curl_multi under the hood).
 */
final class BatchFetcher {

	public function __construct(
		private readonly Settings $settings,
	) {}

	/**
	 * Fetch multiple URLs in parallel.
	 *
	 * @param string[] $urls URLs to fetch.
	 * @return FetchResult[] Keyed by URL.
	 */
	public function fetch_batch( array $urls ): array {
		if ( empty( $urls ) ) {
			return [];
		}

		$timeout = (int) $this->settings->get( 'timeout', 30 );
		$options = [
			'timeout'          => $timeout,
			'connect_timeout'  => min( $timeout, 10 ),
			'follow_redirects' => true,
			'redirects'        => 5,
			'verify'           => false,
		];

		$headers = [
			'User-Agent' => 'StaticExportWP/' . SEWP_VERSION,
		];

		// Build requests array for Requests::request_multiple().
		$requests = [];
		foreach ( $urls as $url ) {
			$requests[ $url ] = [
				'url'     => $url,
				'type'    => \WpOrg\Requests\Requests::GET,
				'headers' => $headers,
				'options' => $options,
			];
		}

		$responses = \WpOrg\Requests\Requests::request_multiple( $requests );

		$results = [];
		foreach ( $responses as $url => $response ) {
			if ( $response instanceof \WpOrg\Requests\Exception ) {
				$results[ $url ] = new FetchResult(
					url: $url,
					http_status: 0,
					content_type: '',
					body: '',
					headers: [],
					error: $response->getMessage(),
				);
				continue;
			}

			$content_type = $response->headers['content-type'] ?? '';

			$results[ $url ] = new FetchResult(
				url: $url,
				http_status: (int) $response->status_code,
				content_type: is_array( $content_type ) ? $content_type[0] : (string) $content_type,
				body: $response->body,
				headers: $response->headers->getAll(),
			);
		}

		return $results;
	}
}
