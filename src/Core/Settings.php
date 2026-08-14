<?php
/**
 * Stores and sanitizes the plugin's persisted settings.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Core;

/**
 * Provides default values, retrieval, and sanitized persistence for the
 * plugin's settings, which are stored as a single WordPress option.
 */
final class Settings {

	public const string OPTION_KEY = 'sewp_settings';

	/**
	 * Get the default settings values.
	 *
	 * @return array Default settings, keyed by setting name.
	 */
	public function defaults(): array {
		$upload_dir = wp_upload_dir();

		return array(
			'output_dir'             => trailingslashit( $upload_dir['basedir'] ) . 'static-export',
			'url_mode'               => 'relative',
			'base_url'               => '',
			'export_mode'            => 'full',
			'selected_urls'          => array(),
			'post_types'             => array( 'post', 'page' ),
			'rate_limit'             => 50,
			'batch_size'             => 10,
			'max_retries'            => 3,
			'timeout'                => 30,
			'extra_urls'             => array(),
			'exclude_patterns'       => array(),
			'deploy_method'          => 'none',
			'deploy_command'         => '',
			'deploy_git_remote'      => '',
			'deploy_git_branch'      => 'main',
			'deploy_git_token'       => '',
			'deploy_netlify_token'   => '',
			'deploy_netlify_site_id' => '',
			'notify_enabled'         => false,
			'notify_email'           => '',
			'webhook_url'            => '',
			'webhook_secret'         => '',
			'webhook_events'         => array( 'completed', 'failed' ),
			'pagination_depth'       => 0,
			'incremental_export'     => false,
			'pagefind_enabled'       => false,
			'auto_export_on_publish' => false,
			'image_optimization'     => false,
			'image_quality'          => 80,
			'redirects_content'      => '',
			'headers_content'        => '',
			'minify_css'             => false,
			'minify_js'              => false,
		);
	}

	/**
	 * Get all settings, merged with defaults for any missing keys.
	 *
	 * @return array Settings values, keyed by setting name.
	 */
	public function get_all(): array {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $saved, $this->defaults() );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key      Setting name.
	 * @param mixed  $fallback Value to return if the setting is not set.
	 *
	 * @return mixed The setting value, or $fallback if not found.
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		$all = $this->get_all();
		return $all[ $key ] ?? $fallback;
	}

	/**
	 * Merge, sanitize, and persist new settings values.
	 *
	 * @param array $values New settings values to merge over the current ones.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function update( array $values ): bool {
		$current   = $this->get_all();
		$sanitized = $this->sanitize( wp_parse_args( $values, $current ) );
		return update_option( self::OPTION_KEY, $sanitized );
	}

	/**
	 * Sanitize a full set of settings values, falling back to defaults for
	 * any values that are missing or invalid.
	 *
	 * @param array $values Raw settings values to sanitize.
	 *
	 * @return array Sanitized settings, keyed by setting name.
	 */
	public function sanitize( array $values ): array {
		$defaults = $this->defaults();

		return array(
			'output_dir'             => sanitize_text_field( $values['output_dir'] ?? $defaults['output_dir'] ),
			'url_mode'               => in_array( $values['url_mode'] ?? '', array( 'relative', 'absolute' ), true )
				? $values['url_mode']
				: 'relative',
			'base_url'               => esc_url_raw( $values['base_url'] ?? '' ),
			'export_mode'            => in_array( $values['export_mode'] ?? '', array( 'full', 'selective' ), true )
				? $values['export_mode']
				: 'full',
			'selected_urls'          => array_values(
				array_filter(
					array_map(
						'esc_url_raw',
						(array) ( $values['selected_urls'] ?? array() )
					)
				)
			),
			'post_types'             => array_map( 'sanitize_key', (array) ( $values['post_types'] ?? $defaults['post_types'] ) ),
			'rate_limit'             => max( 0, (int) ( $values['rate_limit'] ?? $defaults['rate_limit'] ) ),
			'batch_size'             => max( 1, min( 100, (int) ( $values['batch_size'] ?? $defaults['batch_size'] ) ) ),
			'max_retries'            => max( 0, min( 10, (int) ( $values['max_retries'] ?? $defaults['max_retries'] ) ) ),
			'timeout'                => max( 5, min( 120, (int) ( $values['timeout'] ?? $defaults['timeout'] ) ) ),
			'extra_urls'             => array_values(
				array_filter(
					array_map(
						'esc_url_raw',
						(array) ( $values['extra_urls'] ?? array() )
					)
				)
			),
			'exclude_patterns'       => array_values(
				array_filter(
					array_map(
						'sanitize_text_field',
						(array) ( $values['exclude_patterns'] ?? array() )
					)
				)
			),
			'deploy_method'          => in_array( $values['deploy_method'] ?? '', array( 'none', 'command', 'git', 'netlify' ), true )
				? $values['deploy_method']
				: 'none',
			'deploy_command'         => sanitize_text_field( $values['deploy_command'] ?? '' ),
			'deploy_git_remote'      => esc_url_raw( $values['deploy_git_remote'] ?? '' ),
			'deploy_git_branch'      => sanitize_text_field( $values['deploy_git_branch'] ?? 'main' ),
			'deploy_git_token'       => sanitize_text_field( $values['deploy_git_token'] ?? '' ),
			'deploy_netlify_token'   => sanitize_text_field( $values['deploy_netlify_token'] ?? '' ),
			'deploy_netlify_site_id' => sanitize_text_field( $values['deploy_netlify_site_id'] ?? '' ),
			'notify_enabled'         => (bool) ( $values['notify_enabled'] ?? false ),
			'notify_email'           => sanitize_email( $values['notify_email'] ?? '' ),
			'webhook_url'            => esc_url_raw( $values['webhook_url'] ?? '' ),
			'webhook_secret'         => sanitize_text_field( $values['webhook_secret'] ?? '' ),
			'webhook_events'         => array_values(
				array_intersect(
					(array) ( $values['webhook_events'] ?? array() ),
					array( 'completed', 'failed' ),
				)
			),
			'pagination_depth'       => max( 0, (int) ( $values['pagination_depth'] ?? 0 ) ),
			'incremental_export'     => (bool) ( $values['incremental_export'] ?? false ),
			'pagefind_enabled'       => (bool) ( $values['pagefind_enabled'] ?? false ),
			'auto_export_on_publish' => (bool) ( $values['auto_export_on_publish'] ?? false ),
			'image_optimization'     => (bool) ( $values['image_optimization'] ?? false ),
			'image_quality'          => max( 1, min( 100, (int) ( $values['image_quality'] ?? 80 ) ) ),
			'redirects_content'      => sanitize_textarea_field( $values['redirects_content'] ?? '' ),
			'headers_content'        => sanitize_textarea_field( $values['headers_content'] ?? '' ),
			'minify_css'             => (bool) ( $values['minify_css'] ?? false ),
			'minify_js'              => (bool) ( $values['minify_js'] ?? false ),
		);
	}
}
