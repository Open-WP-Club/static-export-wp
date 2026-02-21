<?php

declare(strict_types=1);

namespace StaticExportWP\Crawler;

use StaticExportWP\Core\Settings;

final class UrlDiscovery {

	public function __construct(
		private readonly Settings $settings,
	) {}

	/**
	 * Discover all public URLs on the site.
	 *
	 * @return string[] Array of absolute URLs.
	 */
	public function discover(): array {
		$export_mode = $this->settings->get( 'export_mode', 'full' );

		// Selective mode: only export specific URLs.
		if ( 'selective' === $export_mode ) {
			$selected = $this->settings->get( 'selected_urls', [] );
			if ( ! empty( $selected ) ) {
				/** This filter is documented below. */
				return apply_filters( 'sewp_discovered_urls', $selected );
			}
		}

		$urls = [];

		$urls[] = home_url( '/' );

		$urls = array_merge( $urls, $this->get_post_urls() );
		$urls = array_merge( $urls, $this->get_taxonomy_urls() );
		$urls = array_merge( $urls, $this->get_post_type_archive_urls() );
		$urls = array_merge( $urls, $this->get_author_urls() );
		$urls = array_merge( $urls, $this->get_date_archive_urls() );
		$urls = array_merge( $urls, $this->get_extra_urls() );

		$urls = $this->deduplicate( $urls );
		$urls = $this->apply_exclude_patterns( $urls );

		/**
		 * Filter the list of discovered URLs before crawling.
		 *
		 * @param string[] $urls Discovered URLs.
		 */
		return apply_filters( 'sewp_discovered_urls', $urls );
	}

	private function get_post_urls(): array {
		$urls       = [];
		$post_types = $this->settings->get( 'post_types', [ 'post', 'page' ] );

		foreach ( $post_types as $post_type ) {
			$query = new \WP_Query( [
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			] );

			foreach ( $query->posts as $post_id ) {
				$permalink = get_permalink( $post_id );
				if ( $permalink ) {
					$urls[] = $permalink;
				}
			}
		}

		return $urls;
	}

	private function get_taxonomy_urls(): array {
		$urls       = [];
		$taxonomies = get_taxonomies( [ 'public' => true ], 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( [
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'fields'     => 'ids',
			] );

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term_id ) {
				$link = get_term_link( (int) $term_id, $taxonomy );
				if ( ! is_wp_error( $link ) ) {
					$urls[] = $link;
				}
			}
		}

		return $urls;
	}

	private function get_post_type_archive_urls(): array {
		$urls       = [];
		$post_types = get_post_types( [ 'has_archive' => true, 'public' => true ], 'names' );

		foreach ( $post_types as $post_type ) {
			$archive_link = get_post_type_archive_link( $post_type );
			if ( $archive_link ) {
				$urls[] = $archive_link;
			}
		}

		return $urls;
	}

	private function get_author_urls(): array {
		$urls    = [];
		$authors = get_users( [
			'has_published_posts' => true,
			'fields'              => 'ID',
		] );

		foreach ( $authors as $author_id ) {
			$urls[] = get_author_posts_url( (int) $author_id );
		}

		return $urls;
	}

	private function get_date_archive_urls(): array {
		global $wpdb;

		$urls   = [];
		$months = $wpdb->get_results(
			"SELECT DISTINCT YEAR(post_date) AS year, MONTH(post_date) AS month
			FROM {$wpdb->posts}
			WHERE post_status = 'publish' AND post_type = 'post'
			ORDER BY year DESC, month DESC"
		);

		foreach ( $months as $month ) {
			$urls[] = get_month_link( (int) $month->year, (int) $month->month );
		}

		return $urls;
	}

	private function get_extra_urls(): array {
		$extra = $this->settings->get( 'extra_urls', [] );
		$site  = home_url();

		return array_filter( $extra, function ( $url ) use ( $site ) {
			// Only allow URLs from the same site.
			return str_starts_with( $url, $site );
		} );
	}

	/**
	 * @param string[] $urls
	 * @return string[]
	 */
	private function deduplicate( array $urls ): array {
		$seen   = [];
		$result = [];

		foreach ( $urls as $url ) {
			$normalized = untrailingslashit( strtolower( $url ) );
			if ( ! isset( $seen[ $normalized ] ) ) {
				$seen[ $normalized ] = true;
				$result[]            = $url;
			}
		}

		return $result;
	}

	/**
	 * @param string[] $urls
	 * @return string[]
	 */
	private function apply_exclude_patterns( array $urls ): array {
		$patterns = $this->settings->get( 'exclude_patterns', [] );

		if ( empty( $patterns ) ) {
			return $urls;
		}

		return array_values( array_filter( $urls, function ( $url ) use ( $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( fnmatch( $pattern, $url ) || str_contains( $url, $pattern ) ) {
					return false;
				}
			}
			return true;
		} ) );
	}
}
