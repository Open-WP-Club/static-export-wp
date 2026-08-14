<?php
/**
 * Processes exported HTML pages: rewrites URLs and collects referenced assets.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Export;

/**
 * Parses page HTML into a DOMDocument and performs URL rewriting and asset
 * collection against that single parsed document.
 */
final class HtmlProcessor {

	/**
	 * Construct the processor.
	 *
	 * @param UrlRewriter    $url_rewriter    Rewrites individual URLs found in the document.
	 * @param AssetCollector $asset_collector Collects asset URLs referenced by the document.
	 */
	public function __construct(
		private readonly UrlRewriter $url_rewriter,
		private readonly AssetCollector $asset_collector,
	) {}

	/**
	 * Process an HTML page: rewrite URLs and collect assets.
	 *
	 * Parses DOMDocument once and shares it across all operations.
	 *
	 * @param string $html     Raw HTML content of the page.
	 * @param string $page_url URL the page was fetched from, used to resolve relative links.
	 * @param string $url_mode URL rewrite mode: relative or absolute.
	 * @param string $base_url Base URL used when rewriting in absolute mode.
	 * @return array{html: string, assets: string[], discovered_urls: string[]}
	 */
	public function process( string $html, string $page_url, string $url_mode, string $base_url ): array {
		$site_url = untrailingslashit( home_url() );

		// Single DOMDocument parse for all operations.
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		// Collect assets from the parsed DOM (before rewriting changes URLs).
		$assets = $this->asset_collector->collect_from_doc( $doc, $site_url, $html );

		// Discover internal links from the parsed DOM.
		$discovered = $this->extract_internal_links_from_doc( $doc, $site_url );

		// Rewrite URLs in the same DOM.
		$html = $this->rewrite_doc( $doc, $page_url, $url_mode, $base_url, $site_url );

		return array(
			'html'            => $html,
			'assets'          => $assets,
			'discovered_urls' => $discovered,
		);
	}

	/**
	 * Rewrite all URL-bearing attributes and inline CSS url() references in a parsed document.
	 *
	 * @param \DOMDocument $doc      Parsed HTML document to rewrite in place.
	 * @param string       $page_url URL the page was fetched from, used to resolve relative links.
	 * @param string       $url_mode URL rewrite mode: relative or absolute.
	 * @param string       $base_url Base URL used when rewriting in absolute mode.
	 * @param string       $site_url Site's own URL, unused but kept for a consistent rewrite signature.
	 * @return string The serialized, rewritten HTML.
	 */
	private function rewrite_doc( \DOMDocument $doc, string $page_url, string $url_mode, string $base_url, string $site_url ): string {
		$rewrite = fn( string $url ) => $this->url_rewriter->rewrite( $url, $page_url, $url_mode, $base_url );

		// <a href>
		$this->rewrite_attribute( $doc, 'a', 'href', $rewrite );

		// <link href>
		$this->rewrite_attribute( $doc, 'link', 'href', $rewrite );

		// <script src>
		$this->rewrite_attribute( $doc, 'script', 'src', $rewrite );

		// <img src>
		$this->rewrite_attribute( $doc, 'img', 'src', $rewrite );

		// <img srcset>
		$this->rewrite_srcset( $doc, 'img', $rewrite );

		// <source src, srcset>
		$this->rewrite_attribute( $doc, 'source', 'src', $rewrite );
		$this->rewrite_srcset( $doc, 'source', $rewrite );

		// <video src, poster>
		$this->rewrite_attribute( $doc, 'video', 'src', $rewrite );
		$this->rewrite_attribute( $doc, 'video', 'poster', $rewrite );

		// <form action>
		$this->rewrite_attribute( $doc, 'form', 'action', $rewrite );

		$output = $doc->saveHTML();

		// Remove the XML encoding declaration we added.
		$output = str_replace( '<?xml encoding="utf-8" ?>', '', $output );

		// Rewrite inline style url() references.
		$output = $this->rewrite_inline_css_urls( $output, $rewrite );

		return $output;
	}

	/**
	 * Rewrite a single attribute on every element matching a tag name.
	 *
	 * @param \DOMDocument $doc     Parsed HTML document to rewrite in place.
	 * @param string       $tag     Element tag name to match, e.g. 'a' or 'img'.
	 * @param string       $attr    Attribute name to rewrite, e.g. 'href' or 'src'.
	 * @param callable     $rewrite Callback that rewrites a single URL string.
	 */
	private function rewrite_attribute( \DOMDocument $doc, string $tag, string $attr, callable $rewrite ): void {
		foreach ( $doc->getElementsByTagName( $tag ) as $el ) {
			$value = $el->getAttribute( $attr );
			if ( '' !== $value ) {
				$el->setAttribute( $attr, $rewrite( $value ) );
			}
		}
	}

	/**
	 * Rewrite the URL portion of each candidate in the `srcset` attribute of matching elements.
	 *
	 * @param \DOMDocument $doc     Parsed HTML document to rewrite in place.
	 * @param string       $tag     Element tag name to match, e.g. 'img' or 'source'.
	 * @param callable     $rewrite Callback that rewrites a single URL string.
	 */
	private function rewrite_srcset( \DOMDocument $doc, string $tag, callable $rewrite ): void {
		foreach ( $doc->getElementsByTagName( $tag ) as $el ) {
			$srcset = $el->getAttribute( 'srcset' );
			if ( '' === $srcset ) {
				continue;
			}

			$entries = array_map(
				function ( $entry ) use ( $rewrite ) {
					$parts = preg_split( '/\s+/', trim( $entry ), 2 );
					if ( ! empty( $parts[0] ) ) {
							$parts[0] = $rewrite( $parts[0] );
					}
					return implode( ' ', $parts );
				},
				explode( ',', $srcset )
			);

			$el->setAttribute( 'srcset', implode( ', ', $entries ) );
		}
	}

	/**
	 * Rewrite `url(...)` references inside inline CSS found in the serialized HTML.
	 *
	 * @param string   $html    Serialized HTML that may contain inline style url() references.
	 * @param callable $rewrite Callback that rewrites a single URL string.
	 * @return string HTML with inline CSS URLs rewritten.
	 */
	private function rewrite_inline_css_urls( string $html, callable $rewrite ): string {
		return (string) preg_replace_callback(
			'/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i',
			function ( $matches ) use ( $rewrite ) {
				$url     = $matches[1];
				$new_url = $rewrite( $url );
				return "url('{$new_url}')";
			},
			$html,
		);
	}

	/**
	 * Extract internal links from a pre-parsed DOMDocument.
	 *
	 * @param \DOMDocument $doc      Parsed HTML document to scan for links.
	 * @param string       $site_url Site's own URL, used to identify internal links.
	 * @return string[]
	 */
	private function extract_internal_links_from_doc( \DOMDocument $doc, string $site_url ): array {
		$urls = array();

		foreach ( $doc->getElementsByTagName( 'a' ) as $el ) {
			$href = $el->getAttribute( 'href' );

			if ( '' === $href ) {
				continue;
			}

			// Make absolute.
			if ( str_starts_with( $href, '/' ) && ! str_starts_with( $href, '//' ) ) {
				$href = $site_url . $href;
			}

			// Only internal, non-asset links.
			if ( ! str_starts_with( $href, $site_url ) ) {
				continue;
			}

			// Skip WordPress backend and PHP URLs.
			$path = wp_parse_url( $href, PHP_URL_PATH ) ?? '';
			if (
				str_contains( $path, '/wp-admin' )
				|| str_contains( $path, '/wp-login.php' )
				|| str_contains( $path, '/wp-cron.php' )
				|| str_contains( $path, '/wp-json' )
				|| str_contains( $path, '/xmlrpc.php' )
				|| str_contains( $path, '/feed' )
				|| str_ends_with( strtolower( $path ), '.php' )
			) {
				continue;
			}

			// Strip fragment.
			$stripped = strtok( $href, '#' );
			$href     = $stripped ? $stripped : $href;

			$urls[] = $href;
		}

		return array_unique( $urls );
	}
}
