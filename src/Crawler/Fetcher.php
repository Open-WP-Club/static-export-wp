<?php
/**
 * Performs single-URL HTTP fetches for the crawler.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Crawler;

use StaticExportWP\Core\Settings;

/**
 * Fetches a single URL over HTTP using WordPress's HTTP API.
 */
final class Fetcher {

	/**
	 * Create the fetcher.
	 *
	 * @param Settings $settings Plugin settings used to control fetch behaviour (e.g. timeout).
	 */
	public function __construct(
		private readonly Settings $settings,
	) {}

	/**
	 * Fetch a single URL and wrap the outcome in a FetchResult.
	 *
	 * @param string $url The URL to fetch.
	 * @return FetchResult The result of the fetch, including status, body and any error.
	 */
	public function fetch( string $url ): FetchResult {
		$args = array(
			'timeout'     => $this->settings->get( 'timeout', 30 ),
			'redirection' => 5,
			'sslverify'   => (bool) apply_filters( 'sewp_sslverify', true ),
			'headers'     => array(
				'User-Agent' => 'StaticExportWP/' . SEWP_VERSION,
			),
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new FetchResult(
				url: $url,
				http_status: 0,
				content_type: '',
				body: '',
				headers: array(),
				error: $response->get_error_message(),
			);
		}

		$status       = wp_remote_retrieve_response_code( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$body         = wp_remote_retrieve_body( $response );
		$headers      = wp_remote_retrieve_headers( $response )->getAll();

		return new FetchResult(
			url: $url,
			http_status: (int) $status,
			content_type: (string) $content_type,
			body: $body,
			headers: $headers,
		);
	}
}
