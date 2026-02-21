<?php

declare(strict_types=1);

namespace StaticExportWP\Core;

final class Activator {

	public static function activate(): void {
		Schema::create_tables();

		$settings = new Settings();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			update_option( Settings::OPTION_KEY, $settings->defaults() );
		}
	}
}
