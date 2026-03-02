<?php
/**
 * Minimal WP_REST_Request stub for unit testing.
 */
class WP_REST_Request {

	private array $params      = [];
	private array $json_params = [];

	public function get_param( string $key ): mixed {
		return $this->params[ $key ] ?? null;
	}

	public function set_param( string $key, mixed $value ): void {
		$this->params[ $key ] = $value;
	}

	public function get_json_params(): array {
		return $this->json_params;
	}

	public function set_json_params( array $params ): void {
		$this->json_params = $params;
	}
}
