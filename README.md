# Static Export WP

Export your WordPress site as static HTML files. Crawls every page like a browser and saves a complete static copy with all referenced assets.

## Features

- **Full site crawl** — discovers posts, pages, archives, taxonomies, author and date pages automatically
- **Smart asset collection** — only copies CSS, JS, images and fonts that are actually referenced
- **URL rewriting** — relative paths (works offline/from `file://`) or custom base URL for deployment
- **Background processing** — exports run via Action Scheduler or WP Cron without blocking
- **WP-CLI support** — run exports from the command line with progress bars
- **React admin UI** — modern dashboard with real-time progress tracking
- **Export history** — view past exports with status and statistics

## Requirements

- PHP 8.1+
- WordPress 6.4+

## Installation

```bash
# Clone the repo into your plugins directory
cd wp-content/plugins/
git clone https://github.com/your-username/static-export-wp.git
cd static-export-wp

# Install dependencies
composer install
npm install

# Build the admin UI
npm run build
```

Activate the plugin in **Plugins → Static Export**, then open **Static Export** from the admin menu.

## WP-CLI

```bash
# Full synchronous export with progress bar
wp static-export generate --synchronous

# Export with custom output and base URL
wp static-export generate --synchronous --output-dir=/tmp/export --url-mode=absolute --base-url=https://example.com

# Preview discoverable URLs
wp static-export list-urls

# Check export status
wp static-export status

# Cancel a running export
wp static-export cancel

# Delete exported files
wp static-export clean --yes
```

## REST API

All endpoints require `manage_options` capability. Namespace: `sewp/v1`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/settings` | Get current settings |
| POST | `/settings` | Update settings |
| POST | `/export/start` | Start background export |
| POST | `/export/cancel` | Cancel running export |
| GET | `/export/status` | Get export progress |
| GET | `/export/log` | Export history |
| POST | `/export/clean` | Delete output files |
| GET | `/export/discover-urls` | Preview URL list |

## Development

```bash
# Watch mode for JS development
npm run start

# Run PHP unit tests
./vendor/bin/phpunit

# Check coding standards
composer phpcs
```

## Releasing

Push a version tag to trigger the GitHub Actions workflow that builds a release ZIP:

```bash
git tag v1.0.0
git push origin v1.0.0
```

The workflow installs production dependencies, builds assets, stamps the version, and creates a GitHub Release with the plugin ZIP attached.

## License

GPL-2.0-or-later
