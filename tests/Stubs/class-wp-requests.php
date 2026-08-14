<?php
/**
 * Minimal stub for the WpOrg\Requests library bundled with WordPress core
 * (wp-includes/Requests). It is not installed via Composer, so this stub
 * lets BatchFetcher (which calls Requests::request_multiple() directly)
 * be exercised in isolation under PHPUnit.
 */

declare(strict_types=1);

namespace WpOrg\Requests;

/**
 * Stub for WpOrg\Requests\Exception.
 */
class Exception extends \Exception {

	/**
	 * @param string $message Error message.
	 * @param string $type    Requests error type (e.g. 'curlerror').
	 * @param mixed  $data    Additional error data.
	 */
	public function __construct(
		string $message = '',
		private readonly string $type = '',
		private readonly mixed $data = null,
	) {
		parent::__construct( $message );
	}
}

/**
 * Stub for WpOrg\Requests\Requests.
 *
 * Tests populate self::$_responses (keyed by URL) with either an array
 * shaped like ['status_code' => int, 'headers' => array, 'body' => string]
 * or a Exception instance before calling code that triggers request_multiple().
 */
class Requests {

	public const GET = 'GET';

	/**
	 * Queued responses keyed by request URL, set by tests.
	 *
	 * @var array<string, array{status_code:int, headers?:array<string,string>, body?:string}|Exception>
	 */
	public static array $_responses = [];

	/**
	 * Simulate parallel HTTP requests using queued test responses.
	 *
	 * @param array<string, array{url:string,type:string,headers:array,options:array}> $requests Keyed request definitions.
	 * @return array<string, Response|Exception> Results keyed the same as $requests.
	 */
	public static function request_multiple( array $requests ): array {
		$results = [];

		foreach ( $requests as $key => $request ) {
			$url    = $request['url'];
			$queued = self::$_responses[ $url ] ?? null;

			if ( $queued instanceof Exception ) {
				$results[ $key ] = $queued;
				continue;
			}

			$data = $queued ?? [
				'status_code' => 200,
				'headers'     => [],
				'body'        => '',
			];

			$results[ $key ] = new Response(
				(int) ( $data['status_code'] ?? 200 ),
				(array) ( $data['headers'] ?? [] ),
				(string) ( $data['body'] ?? '' ),
			);
		}

		return $results;
	}
}

/**
 * Stub for WpOrg\Requests\Response.
 */
class Response {

	public Headers $headers;

	/**
	 * @param array<string, string> $headers Response headers.
	 */
	public function __construct(
		public int $status_code,
		array $headers,
		public string $body,
	) {
		$this->headers = new Headers( $headers );
	}
}

/**
 * Stub for WpOrg\Requests\Response\Headers — case-insensitive, array-accessible.
 *
 * @implements \ArrayAccess<string, string>
 */
class Headers implements \ArrayAccess {

	/** @var array<string, string> Lower-cased header map. */
	private array $data = [];

	/**
	 * @param array<string, string> $headers Initial headers.
	 */
	public function __construct( array $headers = [] ) {
		foreach ( $headers as $name => $value ) {
			$this->data[ strtolower( (string) $name ) ] = $value;
		}
	}

	public function offsetExists( mixed $offset ): bool {
		return isset( $this->data[ strtolower( (string) $offset ) ] );
	}

	public function offsetGet( mixed $offset ): mixed {
		return $this->data[ strtolower( (string) $offset ) ] ?? null;
	}

	public function offsetSet( mixed $offset, mixed $value ): void {
		$this->data[ strtolower( (string) $offset ) ] = $value;
	}

	public function offsetUnset( mixed $offset ): void {
		unset( $this->data[ strtolower( (string) $offset ) ] );
	}

	/**
	 * @return array<string, string> All headers.
	 */
	public function getAll(): array {
		return $this->data;
	}
}
