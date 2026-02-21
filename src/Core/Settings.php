<?php

declare(strict_types=1);

namespace StaticExportWP\Core;

final class Settings {

	public const OPTION_KEY = 'sewp_settings';

	public function defaults(): array {
		$upload_dir = wp_upload_dir();

		return [
			'output_dir'       => trailingslashit( $upload_dir['basedir'] ) . 'static-export',
			'url_mode'         => 'relative',
			'base_url'         => '',
			'post_types'       => [ 'post', 'page' ],
			'rate_limit'       => 50,
			'batch_size'       => 10,
			'max_retries'      => 3,
			'timeout'          => 30,
			'extra_urls'       => [],
			'exclude_patterns' => [],
		];
	}

	public function get_all(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		return wp_parse_args( $saved, $this->defaults() );
	}

	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->get_all();
		return $all[ $key ] ?? $default;
	}

	public function update( array $values ): bool {
		$current   = $this->get_all();
		$sanitized = $this->sanitize( wp_parse_args( $values, $current ) );
		return update_option( self::OPTION_KEY, $sanitized );
	}

	public function sanitize( array $values ): array {
		$defaults = $this->defaults();

		return [
			'output_dir'       => sanitize_text_field( $values['output_dir'] ?? $defaults['output_dir'] ),
			'url_mode'         => in_array( $values['url_mode'] ?? '', [ 'relative', 'absolute' ], true )
				? $values['url_mode']
				: 'relative',
			'base_url'         => esc_url_raw( $values['base_url'] ?? '' ),
			'post_types'       => array_map( 'sanitize_key', (array) ( $values['post_types'] ?? $defaults['post_types'] ) ),
			'rate_limit'       => max( 0, (int) ( $values['rate_limit'] ?? $defaults['rate_limit'] ) ),
			'batch_size'       => max( 1, min( 100, (int) ( $values['batch_size'] ?? $defaults['batch_size'] ) ) ),
			'max_retries'      => max( 0, min( 10, (int) ( $values['max_retries'] ?? $defaults['max_retries'] ) ) ),
			'timeout'          => max( 5, min( 120, (int) ( $values['timeout'] ?? $defaults['timeout'] ) ) ),
			'extra_urls'       => array_values( array_filter( array_map(
				'esc_url_raw',
				(array) ( $values['extra_urls'] ?? [] )
			) ) ),
			'exclude_patterns' => array_values( array_filter( array_map(
				'sanitize_text_field',
				(array) ( $values['exclude_patterns'] ?? [] )
			) ) ),
		];
	}
}
