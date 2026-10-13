<?php

/**
 * Theme Skin Switcher helper functions
 *
 * @package KitarisModule\ThemeSkinSwitcher
 */

declare(strict_types=1);

use KitarisModule\ThemeSkinSwitcher\Style\StyleVariationManager;

if (! function_exists('theme_skin_switcher_append_pattern_asset_urls')) {
	/**
	 * Resolve and append asset URLs for the active skin's section patterns.
	 *
	 * @param array<string, mixed> $patterns Editor patterns data.
	 * @return array<string, mixed>
	 */
	function theme_skin_switcher_append_pattern_asset_urls(array $patterns): array {
		if (! class_exists(StyleVariationManager::class)) {
			return $patterns;
		}

		return StyleVariationManager::append_pattern_asset_urls($patterns);
	}
}
