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
			'export_mode'      => 'full',
			'selected_urls'    => [],
			'post_types'       => [ 'post', 'page' ],
			'rate_limit'       => 50,
			'batch_size'       => 10,
			'max_retries'      => 3,
			'timeout'          => 30,
			'extra_urls'       => [],
			'exclude_patterns' => [],
			'deploy_method'    => 'none',
			'deploy_command'   => '',
			'deploy_git_remote'      => '',
			'deploy_git_branch'      => 'main',
			'deploy_git_token'       => '',
			'deploy_netlify_token'   => '',
			'deploy_netlify_site_id' => '',
			'notify_enabled'   => false,
			'notify_email'     => '',
			'webhook_url'      => '',
			'webhook_secret'   => '',
			'webhook_events'   => [ 'completed', 'failed' ],
			'pagination_depth'   => 0,
			'incremental_export' => false,
			'pagefind_enabled' => false,
			'auto_export_on_publish' => false,
			'image_optimization'     => false,
			'image_quality'          => 80,
			'redirects_content'      => '',
			'headers_content'        => '',
			'minify_css'             => false,
			'minify_js'              => false,
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
			'export_mode'      => in_array( $values['export_mode'] ?? '', [ 'full', 'selective' ], true )
				? $values['export_mode']
				: 'full',
			'selected_urls'    => array_values( array_filter( array_map(
				'esc_url_raw',
				(array) ( $values['selected_urls'] ?? [] )
			) ) ),
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
			'deploy_method'    => in_array( $values['deploy_method'] ?? '', [ 'none', 'command', 'git', 'netlify' ], true )
				? $values['deploy_method']
				: 'none',
			'deploy_command'   => sanitize_text_field( $values['deploy_command'] ?? '' ),
			'deploy_git_remote'      => esc_url_raw( $values['deploy_git_remote'] ?? '' ),
			'deploy_git_branch'      => sanitize_text_field( $values['deploy_git_branch'] ?? 'main' ),
			'deploy_git_token'       => sanitize_text_field( $values['deploy_git_token'] ?? '' ),
			'deploy_netlify_token'   => sanitize_text_field( $values['deploy_netlify_token'] ?? '' ),
			'deploy_netlify_site_id' => sanitize_text_field( $values['deploy_netlify_site_id'] ?? '' ),
			'notify_enabled'   => (bool) ( $values['notify_enabled'] ?? false ),
			'notify_email'     => sanitize_email( $values['notify_email'] ?? '' ),
			'webhook_url'      => esc_url_raw( $values['webhook_url'] ?? '' ),
			'webhook_secret'   => sanitize_text_field( $values['webhook_secret'] ?? '' ),
			'webhook_events'   => array_values( array_intersect(
				(array) ( $values['webhook_events'] ?? [] ),
				[ 'completed', 'failed' ],
			) ),
			'pagination_depth'   => max( 0, (int) ( $values['pagination_depth'] ?? 0 ) ),
			'incremental_export' => (bool) ( $values['incremental_export'] ?? false ),
			'pagefind_enabled' => (bool) ( $values['pagefind_enabled'] ?? false ),
			'auto_export_on_publish' => (bool) ( $values['auto_export_on_publish'] ?? false ),
			'image_optimization'     => (bool) ( $values['image_optimization'] ?? false ),
			'image_quality'          => max( 1, min( 100, (int) ( $values['image_quality'] ?? 80 ) ) ),
			'redirects_content'      => sanitize_textarea_field( $values['redirects_content'] ?? '' ),
			'headers_content'        => sanitize_textarea_field( $values['headers_content'] ?? '' ),
			'minify_css'             => (bool) ( $values['minify_css'] ?? false ),
			'minify_js'              => (bool) ( $values['minify_js'] ?? false ),
		];
	}
}
