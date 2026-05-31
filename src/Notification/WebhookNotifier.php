<?php

declare(strict_types=1);

namespace StaticExportWP\Notification;

use StaticExportWP\Core\Settings;

final class WebhookNotifier {

	public function __construct(
		private readonly Settings $settings,
	) {}

	/**
	 * Handle the sewp_export_finalized action.
	 */
	public function notify( string $export_id, string $status, array $counts, string $duration ): void {
		$url = $this->settings->get( 'webhook_url', '' );
		if ( '' === $url ) {
			return;
		}

		$events = $this->settings->get( 'webhook_events', [ 'completed', 'failed' ] );
		if ( ! in_array( $status, $events, true ) ) {
			return;
		}

		$event   = 'export.' . $status;
		$payload = [
			'event'     => $event,
			'export_id' => $export_id,
			'status'    => $status,
			'site_url'  => home_url(),
			'site_name' => get_bloginfo( 'name' ),
			'counts'    => [
				'total'     => $counts['total'] ?? 0,
				'completed' => $counts['completed'] ?? 0,
				'failed'    => $counts['failed'] ?? 0,
			],
			'duration'  => $duration,
			'timestamp' => gmdate( 'c' ),
		];

		$this->send( $url, $event, $payload );
	}

	/**
	 * Send a test webhook to verify the configured URL.
	 *
	 * @return \WP_REST_Response
	 */
	public function send_test(): \WP_REST_Response {
		$url = $this->settings->get( 'webhook_url', '' );
		if ( '' === $url ) {
			return new \WP_REST_Response(
				[ 'success' => false, 'message' => __( 'No webhook URL configured.', 'static-export-wp' ) ],
				400,
			);
		}

		$payload = [
			'event'     => 'test',
			'site_url'  => home_url(),
			'site_name' => get_bloginfo( 'name' ),
			'message'   => __( 'Webhook test from Static Export WP.', 'static-export-wp' ),
			'timestamp' => gmdate( 'c' ),
		];

		$result = $this->send( $url, 'test', $payload );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				[ 'success' => false, 'message' => $result->get_error_message() ],
				502,
			);
		}

		$code = wp_remote_retrieve_response_code( $result );
		if ( $code >= 200 && $code < 300 ) {
			return new \WP_REST_Response( [ 'success' => true, 'status_code' => $code ] );
		}

		return new \WP_REST_Response(
			[
				'success'     => false,
				'status_code' => $code,
				'message'     => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Webhook endpoint returned HTTP %d.', 'static-export-wp' ),
					$code,
				),
			],
			502,
		);
	}

	/**
	 * Returns false if the URL targets a private or reserved IP address (SSRF protection).
	 *
	 * Literal IPs are checked directly. Hostnames are resolved; if DNS fails we
	 * allow the request — the HTTP stack will reject it naturally. This avoids
	 * false-positives in test environments while still blocking obvious attacks
	 * like http://169.254.169.254/ (AWS metadata) or http://192.168.1.1/.
	 */
	private function is_safe_url( string $url ): bool {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return false;
		}

		// Strip IPv6 brackets if present.
		$host = trim( $host, '[]' );

		// If the host is already an IP address, check it directly.
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return (bool) filter_var(
				$host,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
			);
		}

		// Resolve hostname; if DNS fails allow the request (HTTP will fail naturally).
		$ip = gethostbyname( $host );
		if ( $ip === $host ) {
			return true;
		}

		return (bool) filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
		);
	}

	/**
	 * Send a webhook payload.
	 *
	 * @return array|\WP_Error
	 */
	private function send( string $url, string $event, array $payload ): array|\WP_Error {
		if ( ! $this->is_safe_url( $url ) ) {
			return new \WP_Error(
				'sewp_ssrf_blocked',
				__( 'Webhook URL resolves to a private or reserved address.', 'static-export-wp' ),
			);
		}

		$body = wp_json_encode( $payload );

		$headers = [
			'Content-Type' => 'application/json',
			'User-Agent'   => 'StaticExportWP/1.0',
			'X-SEWP-Event' => $event,
		];

		$secret = $this->settings->get( 'webhook_secret', '' );
		if ( '' !== $secret ) {
			$signature              = hash_hmac( 'sha256', $body, $secret );
			$headers['X-SEWP-Signature'] = 'sha256=' . $signature;
		}

		return wp_remote_post( $url, [
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 15,
		] );
	}
}
