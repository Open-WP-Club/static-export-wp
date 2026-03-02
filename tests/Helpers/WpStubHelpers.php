<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Helpers;

/**
 * Trait for managing WP stub state in tests.
 */
trait WpStubHelpers {

	protected function reset_wp_state(): void {
		global $_wp_options, $_wp_remote_responses, $_wp_mail_log, $_wp_transients,
			$_wp_cron_events, $_wp_actions, $_wp_filters, $_wp_home_url, $_wp_bloginfo,
			$_wp_upload_dir;

		$_wp_options          = [];
		$_wp_remote_responses = [];
		$_wp_mail_log         = [];
		$_wp_transients       = [];
		$_wp_cron_events      = [];
		$_wp_actions          = [];
		$_wp_filters          = [];
		$_wp_home_url         = 'https://example.com';
		$_wp_bloginfo         = [ 'name' => 'Test Site' ];
		$_wp_upload_dir       = [
			'basedir' => '/tmp/wp-uploads',
			'baseurl' => 'https://example.com/wp-content/uploads',
			'path'    => '/tmp/wp-uploads',
			'url'     => 'https://example.com/wp-content/uploads',
			'error'   => false,
		];
	}

	protected function set_home_url( string $url ): void {
		global $_wp_home_url;
		$_wp_home_url = $url;
	}

	protected function set_option( string $key, mixed $value ): void {
		global $_wp_options;
		$_wp_options[ $key ] = $value;
	}

	protected function set_remote_response( string $url, array|WP_Error $response ): void {
		global $_wp_remote_responses;
		$_wp_remote_responses[ $url ] = $response;
	}

	protected function get_mail_log(): array {
		global $_wp_mail_log;
		return $_wp_mail_log;
	}

	protected function set_bloginfo( string $key, string $value ): void {
		global $_wp_bloginfo;
		$_wp_bloginfo[ $key ] = $value;
	}

	protected function get_actions(): array {
		global $_wp_actions;
		return $_wp_actions;
	}

	protected function get_transient( string $key ): mixed {
		global $_wp_transients;
		return $_wp_transients[ $key ] ?? false;
	}
}
