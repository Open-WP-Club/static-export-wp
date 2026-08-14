<?php
/**
 * Runs plugin activation tasks.
 *
 * @package StaticExportWP
 */

declare(strict_types=1);

namespace StaticExportWP\Core;

/**
 * Handles one-time setup performed when the plugin is activated.
 */
final class Activator {

	/**
	 * Create database tables and seed default settings on activation.
	 */
	public static function activate(): void {
		Schema::create_tables();

		$settings = new Settings();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			update_option( Settings::OPTION_KEY, $settings->defaults() );
		}
	}
}
