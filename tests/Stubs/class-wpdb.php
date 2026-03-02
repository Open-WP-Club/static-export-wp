<?php
/**
 * Minimal wpdb stub for unit testing.
 */
class wpdb {

	public string $prefix = 'wp_';
	public string $posts  = 'wp_posts';

	/** @var array Queued return values for get_var(). */
	public array $_get_var_returns = [];

	/** @var array Queued return values for get_results(). */
	public array $_get_results_returns = [];

	/** @var array Queued return values for get_col(). */
	public array $_get_col_returns = [];

	/** @var array Queued return values for query(). */
	public array $_query_returns = [];

	/** @var array Log of all calls made. */
	public array $_call_log = [];

	public function prepare( string $query, mixed ...$args ): string {
		$this->_call_log[] = [ 'method' => 'prepare', 'query' => $query, 'args' => $args ];
		// Simple placeholder replacement for testing.
		foreach ( $args as $arg ) {
			$pos = strpos( $query, '%s' );
			if ( false === $pos ) {
				$pos = strpos( $query, '%d' );
			}
			if ( false !== $pos ) {
				$replacement = is_int( $arg ) || is_float( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'";
				$query       = substr_replace( $query, $replacement, $pos, 2 );
			}
		}
		return $query;
	}

	public function query( string $query ): int|bool {
		$this->_call_log[] = [ 'method' => 'query', 'query' => $query ];
		if ( ! empty( $this->_query_returns ) ) {
			return array_shift( $this->_query_returns );
		}
		return 1;
	}

	public function get_var( ?string $query = null, int $x = 0, int $y = 0 ): ?string {
		$this->_call_log[] = [ 'method' => 'get_var', 'query' => $query ];
		if ( ! empty( $this->_get_var_returns ) ) {
			return array_shift( $this->_get_var_returns );
		}
		return null;
	}

	public function get_results( ?string $query = null, string $output = 'OBJECT' ): ?array {
		$this->_call_log[] = [ 'method' => 'get_results', 'query' => $query ];
		if ( ! empty( $this->_get_results_returns ) ) {
			return array_shift( $this->_get_results_returns );
		}
		return [];
	}

	public function get_col( ?string $query = null, int $x = 0 ): array {
		$this->_call_log[] = [ 'method' => 'get_col', 'query' => $query ];
		if ( ! empty( $this->_get_col_returns ) ) {
			return array_shift( $this->_get_col_returns );
		}
		return [];
	}

	public function insert( string $table, array $data, array|string|null $format = null ): int|false {
		$this->_call_log[] = [ 'method' => 'insert', 'table' => $table, 'data' => $data ];
		return 1;
	}

	public function update( string $table, array $data, array $where, array|string|null $format = null, array|string|null $where_format = null ): int|false {
		$this->_call_log[] = [ 'method' => 'update', 'table' => $table, 'data' => $data, 'where' => $where ];
		return 1;
	}

	public function delete( string $table, array $where, array|string|null $where_format = null ): int|false {
		$this->_call_log[] = [ 'method' => 'delete', 'table' => $table, 'where' => $where ];
		return 1;
	}

	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}
}
