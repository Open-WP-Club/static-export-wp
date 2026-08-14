<?php
/**
 * Discovers the public URLs of the site to be crawled for export.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Crawler;

use StaticExportWP\Core\Settings;

/**
 * Builds the list of URLs to crawl by querying posts, taxonomies, archives,
 * authors and extra configured URLs, then deduplicating and filtering them.
 */
final class UrlDiscovery {

	/**
	 * Create the URL discovery service.
	 *
	 * @param Settings $settings Plugin settings used to control discovery behaviour.
	 */
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
			$selected = $this->settings->get( 'selected_urls', array() );
			if ( ! empty( $selected ) ) {
				/** This filter is documented below. */
				return apply_filters( 'sewp_discovered_urls', $selected );
			}
		}

		$urls = array();

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

	/**
	 * Get the permalinks of all published posts for the configured post types.
	 *
	 * @return string[] Array of permalink URLs.
	 */
	private function get_post_urls(): array {
		$urls       = array();
		$post_types = $this->settings->get( 'post_types', array( 'post', 'page' ) );

		foreach ( $post_types as $post_type ) {
			$query = new \WP_Query(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			foreach ( $query->posts as $post_id ) {
				$permalink = get_permalink( $post_id );
				if ( $permalink ) {
					$urls[] = $permalink;
				}
			}
		}

		return $urls;
	}

	/**
	 * Get the term archive links for all public taxonomies that have terms.
	 *
	 * @return string[] Array of taxonomy term URLs.
	 */
	private function get_taxonomy_urls(): array {
		$urls       = array();
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'fields'     => 'ids',
				)
			);

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

	/**
	 * Get the archive URLs for public post types that have an archive page.
	 *
	 * @return string[] Array of post type archive URLs.
	 */
	private function get_post_type_archive_urls(): array {
		$urls       = array();
		$post_types = get_post_types(
			array(
				'has_archive' => true,
				'public'      => true,
			),
			'names'
		);

		foreach ( $post_types as $post_type ) {
			$archive_link = get_post_type_archive_link( $post_type );
			if ( $archive_link ) {
				$urls[] = $archive_link;
			}
		}

		return $urls;
	}

	/**
	 * Get the author archive URLs for all users with at least one published post.
	 *
	 * @return string[] Array of author archive URLs.
	 */
	private function get_author_urls(): array {
		$urls    = array();
		$authors = get_users(
			array(
				'has_published_posts' => true,
				'fields'              => 'ID',
			)
		);

		foreach ( $authors as $author_id ) {
			$urls[] = get_author_posts_url( (int) $author_id );
		}

		return $urls;
	}

	/**
	 * Get the monthly date archive URLs for all published posts.
	 *
	 * @return string[] Array of date archive URLs.
	 */
	private function get_date_archive_urls(): array {
		global $wpdb;

		$urls   = array();
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

	/**
	 * Get the manually configured extra URLs, restricted to the current site.
	 *
	 * @return string[] Array of extra URLs belonging to the current site.
	 */
	private function get_extra_urls(): array {
		$extra = $this->settings->get( 'extra_urls', array() );
		$site  = home_url();

		return array_filter(
			$extra,
			function ( $url ) use ( $site ) {
				// Only allow URLs from the same site.
				return str_starts_with( $url, $site );
			}
		);
	}

	/**
	 * Remove duplicate URLs, treating differently-cased and trailing-slash
	 * variants of the same URL as equivalent.
	 *
	 * @param string[] $urls URLs to deduplicate.
	 * @return string[] Deduplicated URLs, in original order and casing.
	 */
	private function deduplicate( array $urls ): array {
		$seen   = array();
		$result = array();

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
	 * Filter out URLs matching any of the configured exclude patterns.
	 *
	 * @param string[] $urls URLs to filter.
	 * @return string[] URLs that do not match any exclude pattern.
	 */
	private function apply_exclude_patterns( array $urls ): array {
		$patterns = $this->settings->get( 'exclude_patterns', array() );

		if ( empty( $patterns ) ) {
			return $urls;
		}

		return array_values(
			array_filter(
				$urls,
				function ( $url ) use ( $patterns ) {
					foreach ( $patterns as $pattern ) {
						if ( fnmatch( $pattern, $url ) || str_contains( $url, $pattern ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}
}
