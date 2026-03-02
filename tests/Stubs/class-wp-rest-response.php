<?php
/**
 * Minimal WP_REST_Response stub for unit testing.
 */
class WP_REST_Response {

	public mixed $data;
	public int $status;

	public function __construct( mixed $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function get_data(): mixed {
		return $this->data;
	}

	public function get_status(): int {
		return $this->status;
	}
}
