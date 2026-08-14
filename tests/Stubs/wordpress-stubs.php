<?php
/**
 * WordPress function stubs for unit testing.
 *
 * Uses global arrays for state so tests can control behaviour.
 */

// ── Global state arrays ────────────────────────────────────────────────────

global $_wp_options, $_wp_remote_responses, $_wp_mail_log, $_wp_transients,
	$_wp_cron_events, $_wp_actions, $_wp_filters, $_wp_home_url, $_wp_bloginfo,
	$_wp_upload_dir, $_wp_query_posts_by_type, $_wp_taxonomies, $_wp_terms_by_taxonomy,
	$_wp_archive_post_types, $_wp_users, $_wp_rest_routes, $_wp_current_user_can,
	$_wp_action_hooks;

$_wp_options             = [];
$_wp_remote_responses    = [];
$_wp_mail_log            = [];
$_wp_transients          = [];
$_wp_cron_events         = [];
$_wp_actions             = [];
$_wp_filters             = [];
$_wp_home_url            = 'https://example.com';
$_wp_bloginfo            = [ 'name' => 'Test Site' ];
$_wp_query_posts_by_type = [];
$_wp_taxonomies          = [];
$_wp_terms_by_taxonomy   = [];
$_wp_archive_post_types  = [];
$_wp_users               = [];
$_wp_rest_routes         = [];
$_wp_current_user_can    = [];
$_wp_action_hooks        = [];
$_wp_upload_dir       = [
	'basedir' => '/tmp/wp-uploads',
	'baseurl' => 'https://example.com/wp-content/uploads',
	'path'    => '/tmp/wp-uploads',
	'url'     => 'https://example.com/wp-content/uploads',
	'error'   => false,
];

// ── Constants ──────────────────────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/var/www/html/' );
}
if ( ! defined( 'SEWP_VERSION' ) ) {
	define( 'SEWP_VERSION', '1.0.0-test' );
}
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}
if ( ! defined( 'FS_CHMOD_FILE' ) ) {
	define( 'FS_CHMOD_FILE', 0644 );
}

// ── Options API ────────────────────────────────────────────────────────────

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default = false ): mixed {
		global $_wp_options;
		return $_wp_options[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, mixed $value, string|bool $autoload = 'yes' ): bool {
		global $_wp_options;
		$_wp_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		global $_wp_options;
		unset( $_wp_options[ $option ] );
		return true;
	}
}

// ── URL helpers ────────────────────────────────────────────────────────────

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		global $_wp_home_url;
		return rtrim( $_wp_home_url, '/' ) . '/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( string $value ): string {
		return rtrim( $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url, ?array $protocols = null ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url, ?array $protocols = null, string $_context = 'display' ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

// ── Sanitization ───────────────────────────────────────────────────────────

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( string $email ): string {
		return filter_var( $email, FILTER_SANITIZE_EMAIL ) ?: '';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( array|string $args, array $defaults = [] ): array {
		if ( is_string( $args ) ) {
			parse_str( $args, $args );
		}
		return array_merge( $defaults, $args );
	}
}

// ── HTTP API ───────────────────────────────────────────────────────────────

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( string $url, array $args = [] ): array|WP_Error {
		global $_wp_remote_responses;
		if ( isset( $_wp_remote_responses[ $url ] ) ) {
			return $_wp_remote_responses[ $url ];
		}
		// Default: 200 with empty body.
		return [
			'response' => [ 'code' => 200 ],
			'headers'  => new WP_HTTP_Headers_Stub( [] ),
			'body'     => '',
		];
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( string $url, array $args = [] ): array|WP_Error {
		global $_wp_remote_responses;
		if ( isset( $_wp_remote_responses[ $url ] ) ) {
			return $_wp_remote_responses[ $url ];
		}
		return [
			'response' => [ 'code' => 200 ],
			'headers'  => new WP_HTTP_Headers_Stub( [] ),
			'body'     => '',
		];
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( string $url, array $args = [] ): array|WP_Error {
		global $_wp_remote_responses;
		if ( isset( $_wp_remote_responses[ $url ] ) ) {
			return $_wp_remote_responses[ $url ];
		}
		return [
			'response' => [ 'code' => 200 ],
			'headers'  => new WP_HTTP_Headers_Stub( [] ),
			'body'     => '',
		];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( array $response ): int|string {
		return $response['response']['code'] ?? 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( array $response ): string {
		return $response['body'] ?? '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
	function wp_remote_retrieve_header( array $response, string $header ): string {
		$headers = $response['headers'] ?? [];
		if ( $headers instanceof WP_HTTP_Headers_Stub ) {
			return $headers->get( $header );
		}
		return $headers[ $header ] ?? '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_headers' ) ) {
	function wp_remote_retrieve_headers( array $response ): WP_HTTP_Headers_Stub {
		$headers = $response['headers'] ?? [];
		if ( $headers instanceof WP_HTTP_Headers_Stub ) {
			return $headers;
		}
		return new WP_HTTP_Headers_Stub( (array) $headers );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

/**
 * Minimal headers stub for wp_remote_retrieve_headers().
 */
if ( ! class_exists( 'WP_HTTP_Headers_Stub' ) ) {
	class WP_HTTP_Headers_Stub {
		private array $headers;

		public function __construct( array $headers ) {
			$this->headers = array_change_key_case( $headers, CASE_LOWER );
		}

		public function get( string $key ): string {
			return $this->headers[ strtolower( $key ) ] ?? '';
		}

		public function getAll(): array {
			return $this->headers;
		}
	}
}

// ── Mail ───────────────────────────────────────────────────────────────────

if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( string|array $to, string $subject, string $message, string|array $headers = '', string|array $attachments = [] ): bool {
		global $_wp_mail_log;
		$_wp_mail_log[] = [
			'to'      => $to,
			'subject' => $subject,
			'message' => $message,
			'headers' => $headers,
		];
		return true;
	}
}

// ── Misc ───────────────────────────────────────────────────────────────────

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir(): array {
		global $_wp_upload_dir;
		return $_wp_upload_dir;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show = '' ): string {
		global $_wp_bloginfo;
		return $_wp_bloginfo[ $show ] ?? '';
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $type ): string {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4(): string {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
		);
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $data, int $options = 0, int $depth = 512 ): string|false {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( string $target ): bool {
		if ( is_dir( $target ) ) {
			return true;
		}
		return mkdir( $target, 0755, true );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( string $file ): void {
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
	}
}

if ( ! function_exists( 'wp_list_pluck' ) ) {
	function wp_list_pluck( array $input_list, string|int $field, ?string $index_key = null ): array {
		$result = [];
		foreach ( $input_list as $item ) {
			$value = is_object( $item ) ? $item->$field : $item[ $field ];
			if ( null !== $index_key ) {
				$key            = is_object( $item ) ? $item->$index_key : $item[ $index_key ];
				$result[ $key ] = $value;
			} else {
				$result[] = $value;
			}
		}
		return $result;
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( int $min = 0, int $max = 0 ): int {
		return mt_rand( $min, $max ?: mt_getrandmax() );
	}
}

// ── Hooks ──────────────────────────────────────────────────────────────────

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook_name, mixed ...$args ): void {
		global $_wp_actions;
		$_wp_actions[] = [ 'hook' => $hook_name, 'args' => $args ];
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook_name, mixed $value, mixed ...$args ): mixed {
		global $_wp_filters;
		foreach ( $_wp_filters[ $hook_name ] ?? [] as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		global $_wp_action_hooks;
		$_wp_action_hooks[] = [ 'hook' => $hook_name, 'callback' => $callback, 'priority' => $priority ];
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		global $_wp_filters;
		$_wp_filters[ $hook_name ][] = $callback;
		return true;
	}
}

// ── Transients ─────────────────────────────────────────────────────────────

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $transient ): mixed {
		global $_wp_transients;
		return $_wp_transients[ $transient ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $transient, mixed $value, int $expiration = 0 ): bool {
		global $_wp_transients;
		$_wp_transients[ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		global $_wp_transients;
		unset( $_wp_transients[ $transient ] );
		return true;
	}
}

// ── Cron ───────────────────────────────────────────────────────────────────

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( int $timestamp, string $hook, array $args = [] ): bool {
		global $_wp_cron_events;
		$_wp_cron_events[] = [ 'timestamp' => $timestamp, 'hook' => $hook, 'args' => $args ];
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( string $hook, array $args = [] ): int {
		global $_wp_cron_events;
		$before          = $_wp_cron_events;
		$_wp_cron_events = array_values( array_filter(
			$_wp_cron_events,
			fn( $event ) => ! ( $event['hook'] === $hook && ( empty( $args ) || $event['args'] === $args ) ),
		) );
		return count( $before ) - count( $_wp_cron_events );
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( string $hook, array $args = [] ): int|false {
		global $_wp_cron_events;
		foreach ( $_wp_cron_events as $event ) {
			if ( $event['hook'] === $hook && ( empty( $args ) || $event['args'] === $args ) ) {
				return $event['timestamp'];
			}
		}
		return false;
	}
}

if ( ! function_exists( 'spawn_cron' ) ) {
	function spawn_cron(): void {
		// No-op in tests.
	}
}

// ── i18n ───────────────────────────────────────────────────────────────────

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( int $from, int $to = 0 ): string {
		$diff = abs( $to - $from );
		if ( $diff < 60 ) {
			return $diff . ' secs';
		}
		return round( $diff / 60 ) . ' mins';
	}
}

// ── Image ──────────────────────────────────────────────────────────────────

if ( ! function_exists( 'wp_get_image_editor' ) ) {
	function wp_get_image_editor( string $path ): WP_Error {
		return new WP_Error( 'no_editor', 'No image editor available in tests.' );
	}
}

// ── Filesystem ─────────────────────────────────────────────────────────────

if ( ! function_exists( 'WP_Filesystem' ) ) {
	function WP_Filesystem(): bool {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			$wp_filesystem = new WP_Filesystem_Stub();
		}
		return true;
	}
}

/**
 * Minimal WP_Filesystem stub so FileWriter doesn't require wp-admin files.
 */
if ( ! class_exists( 'WP_Filesystem_Stub' ) ) {
	class WP_Filesystem_Stub {
		public function put_contents( string $file, string $contents, int $mode = 0644 ): bool {
			$dir = dirname( $file );
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0755, true );
			}
			return false !== file_put_contents( $file, $contents );
		}

		public function get_contents( string $file ): string|false {
			return file_get_contents( $file );
		}

		public function exists( string $file ): bool {
			return file_exists( $file );
		}

		public function rmdir( string $path, bool $recursive = false ): bool {
			if ( ! is_dir( $path ) ) {
				return false;
			}
			if ( $recursive ) {
				$items = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS ),
					\RecursiveIteratorIterator::CHILD_FIRST,
				);
				foreach ( $items as $item ) {
					$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
				}
			}
			return rmdir( $path );
		}
	}
}

// ── Query / Posts ──────────────────────────────────────────────────────────

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		/** @var int[]|WP_Post[] */
		public array $posts = [];

		public function __construct( array $args = [] ) {
			global $_wp_query_posts_by_type;
			$post_type   = (string) ( $args['post_type'] ?? 'post' );
			$this->posts = $_wp_query_posts_by_type[ $post_type ] ?? [];
		}
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( int|WP_Post $post ): string|false {
		$id = is_object( $post ) ? $post->ID : $post;
		return home_url( '/?p=' . $id );
	}
}

if ( ! function_exists( 'get_taxonomies' ) ) {
	function get_taxonomies( array $args = [], string $output = 'names' ): array {
		global $_wp_taxonomies;
		return $_wp_taxonomies;
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( array $args = [] ): array|WP_Error {
		global $_wp_terms_by_taxonomy;
		$taxonomy = (string) ( $args['taxonomy'] ?? '' );
		return $_wp_terms_by_taxonomy[ $taxonomy ] ?? [];
	}
}

if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( int|object $term, string $taxonomy = '' ): string|WP_Error {
		$id = is_object( $term ) ? $term->term_id : $term;
		return home_url( '/term/' . $id . '/' );
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( array $args = [], string $output = 'names' ): array {
		global $_wp_archive_post_types;
		return $_wp_archive_post_types;
	}
}

if ( ! function_exists( 'get_post_type_archive_link' ) ) {
	function get_post_type_archive_link( string $post_type ): string|false {
		return home_url( '/' . $post_type . '/' );
	}
}

if ( ! function_exists( 'get_users' ) ) {
	function get_users( array $args = [] ): array {
		global $_wp_users;
		return $_wp_users;
	}
}

if ( ! function_exists( 'get_author_posts_url' ) ) {
	function get_author_posts_url( int $author_id ): string {
		return home_url( '/author/' . $author_id . '/' );
	}
}

if ( ! function_exists( 'get_month_link' ) ) {
	function get_month_link( int $year, int $month ): string {
		return home_url( sprintf( '/%04d/%02d/', $year, $month ) );
	}
}

// ── Plugin bootstrap ─────────────────────────────────────────────────────────

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		global $_wp_is_admin;
		return (bool) $_wp_is_admin;
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( string $domain, bool $deprecated = false, string|false $plugin_rel_path = false ): bool {
		return true;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $file ): string {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( string $file ): string {
		return 'https://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( string $file, callable $callback ): void {}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( string $file, callable $callback ): void {}
}

// ── Admin ──────────────────────────────────────────────────────────────────

if ( ! class_exists( 'WPDieException' ) ) {
	/**
	 * Thrown by the wp_die() stub instead of actually terminating the process,
	 * so tests can assert on the "died" path with expectException().
	 */
	class WPDieException extends \Exception {}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * @param array<string, mixed> $args Extra wp_die() arguments (unused in the stub).
	 */
	function wp_die( string $message = '', string $title = '', array $args = [] ): void {
		throw new WPDieException( $message );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( int|string $action = -1, string $query_arg = '_wpnonce' ): bool {
		return true;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return home_url( '/wp-admin/' . ltrim( $path, '/' ) );
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '' ): string {
		return home_url( '/wp-json/' . ltrim( $path, '/' ) );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( int|string $action = -1 ): string {
		return 'test-nonce';
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( string $actionurl, int|string $action = -1, string $name = '_wpnonce' ): string {
		return $actionurl . ( str_contains( $actionurl, '?' ) ? '&' : '?' ) . $name . '=test-nonce';
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	/**
	 * @param callable|string $callback Page render callback.
	 */
	function add_menu_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable|string $callback = '', string $icon_url = '', int|float|null $position = null ): string {
		return 'toplevel_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * @param string[] $deps Script handle dependencies.
	 */
	function wp_enqueue_script( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, bool $in_footer = false ): void {}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * @param string[] $deps Style handle dependencies.
	 */
	function wp_enqueue_style( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false ): void {}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * @param array<string, mixed> $l10n Data made available to the script.
	 */
	function wp_localize_script( string $handle, string $object_name, array $l10n ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_tempnam' ) ) {
	function wp_tempnam( string $filename = '', string $dir = '' ): string {
		return ( '' !== $dir ? $dir : sys_get_temp_dir() ) . '/' . uniqid( 'sewp_tmp_', true );
	}
}

// ── REST API ───────────────────────────────────────────────────────────────

if ( ! function_exists( 'register_rest_route' ) ) {
	/**
	 * @param array<string, mixed> $args Route args (methods/callback/permission_callback, or a list of such).
	 */
	function register_rest_route( string $namespace, string $route, array $args = [] ): bool {
		global $_wp_rest_routes;
		$_wp_rest_routes[] = [ 'namespace' => $namespace, 'route' => $route, 'args' => $args ];
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability, mixed ...$args ): bool {
		global $_wp_current_user_can;
		return $_wp_current_user_can[ $capability ] ?? true;
	}
}

// ── Database ───────────────────────────────────────────────────────────────

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( string|array $queries = '' ): array {
		global $_wp_dbdelta_calls;
		$_wp_dbdelta_calls[] = $queries;
		return [];
	}
}
