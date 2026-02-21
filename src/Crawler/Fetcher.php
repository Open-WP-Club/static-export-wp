<?php

declare(strict_types=1);

namespace StaticExportWP\Crawler;

use StaticExportWP\Core\Settings;

final class Fetcher {

	public function __construct(
		private readonly Settings $settings,
	) {}

	public function fetch( string $url ): FetchResult {
		$this->maybe_rate_limit();

		$args = [
			'timeout'     => $this->settings->get( 'timeout', 30 ),
			'redirection' => 5,
			'sslverify'   => false,
			'headers'     => [
				'User-Agent' => 'StaticExportWP/' . SEWP_VERSION,
			],
		];

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new FetchResult(
				url: $url,
				http_status: 0,
				content_type: '',
				body: '',
				headers: [],
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

	private function maybe_rate_limit(): void {
		$rate_limit = (int) $this->settings->get( 'rate_limit', 50 );

		if ( $rate_limit <= 0 ) {
			return;
		}

		// Rate limit in milliseconds between requests.
		$delay_ms = (int) ( 1000 / $rate_limit );
		if ( $delay_ms > 0 ) {
			usleep( $delay_ms * 1000 );
		}
	}
}
