=== Static Export WP ===
Contributors: staticexportwp
Tags: static site, html export, static html, site generator, export
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export your WordPress site as static HTML files. Crawls every page and saves a complete static copy with all referenced assets.

== Description ==

Static Export WP creates a complete static HTML copy of your WordPress site. It works like a browser — visiting every page, saving the rendered HTML, and collecting all referenced CSS, JavaScript, images, and fonts.

**Key Features:**

* Full site crawl — discovers all posts, pages, archives, taxonomies, and author pages
* Asset collection — only copies CSS/JS/images/fonts that are actually referenced
* URL rewriting — choose between relative paths (works offline) or a custom base URL
* Background processing — exports run via Action Scheduler or WP Cron
* WP-CLI support — run exports from the command line with progress bars
* React admin UI — modern dashboard with real-time progress tracking
* Export history — view past exports with status and statistics

**Use Cases:**

* Create a fast, secure static version of your site
* Generate an offline backup of your content
* Deploy to static hosting (Netlify, Cloudflare Pages, S3, etc.)
* Archive a WordPress site before migration

== Installation ==

1. Upload the `static-export-wp` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Static Export in the admin menu
4. Configure your output directory and URL settings
5. Click Start Export or use `wp static-export generate --synchronous`

== Frequently Asked Questions ==

= Does this work with any theme? =

Yes. The plugin crawls the rendered HTML output, so it captures whatever your theme and plugins produce.

= What about dynamic content like forms and comments? =

Static HTML cannot process forms or comments. You would need a third-party service (e.g., Formspree, Disqus) for those features.

= How large of a site can this handle? =

The plugin uses a database-backed queue and batch processing, so it can handle sites with thousands of pages. Adjust the batch size and rate limit in settings to match your server capacity.

= Can I use this with WP-CLI? =

Yes! Use `wp static-export generate --synchronous` for a full export with a progress bar, or `wp static-export list-urls` to preview discoverable URLs.

== Changelog ==

= 1.0.0 =
* Initial release
* Full site crawl with URL discovery
* HTML processing with DOMDocument
* Relative and absolute URL rewriting
* Background export via Action Scheduler / WP Cron
* React admin dashboard with progress tracking
* WP-CLI commands
* Export history logging
