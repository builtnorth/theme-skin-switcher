<?php

/**
 * ------------------------------------------------------------------
 * Style Provisioner
 * ------------------------------------------------------------------
 *
 * Registers the WP-CLI command used to manage skin variations.
 *
 * WP-CLI (write operations):
 *   lando wp theme-skin-switcher set-style <slug>
 *   lando wp theme-skin-switcher list-styles
 *   lando wp theme-skin-switcher get-style
 *
 * REST API (read-only): see {@see \ThemeSkinSwitcher\RestAPI\StyleRoute}.
 *   GET /wp-json/theme-skin-switcher/v1/style
 *
 * @package ThemeSkinSwitcher\Provisioning
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ThemeSkinSwitcher\Provisioning;

use ThemeSkinSwitcher\Style\StyleVariationManager;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class StyleProvisioner
{
	/**
	 * Boot: register CLI commands.
	 */
	public static function boot(): void
	{
		if (defined('WP_CLI') && WP_CLI) {
			\WP_CLI::add_command('theme-skin-switcher', __CLASS__);
		}
	}

	// -----------------------------------------------------------------------
	// WP-CLI Commands
	// -----------------------------------------------------------------------

	/**
	 * Set the active style variation for this site.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The style slug to activate (e.g. arch, flow, fresh, slab).
	 *
	 * ## EXAMPLES
	 *
	 *     lando wp theme-skin-switcher set-style fresh
	 *
	 * @subcommand set-style
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function set_style(array $args, array $assoc_args): void
	{
		unset($assoc_args);

		if (empty($args[0])) {
			\WP_CLI::error('Please provide a style slug. Run `lando wp theme-skin-switcher list-styles` to see available options.');
		}

		$slug = sanitize_key($args[0]);

		if (! StyleVariationManager::set_active_style($slug)) {
			\WP_CLI::error(
				sprintf(
					'"%s" is not a valid style. Available: %s',
					$slug,
					implode(', ', StyleVariationManager::get_available_styles())
				)
			);
		}

		\WP_CLI::success(sprintf('Style "%s" applied.', $slug));
	}

	/**
	 * Get the currently active style variation slug.
	 *
	 * ## EXAMPLES
	 *
	 *     lando wp theme-skin-switcher get-style
	 *
	 * @subcommand get-style
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function get_style(array $args, array $assoc_args): void
	{
		unset($args, $assoc_args);

		$slug = StyleVariationManager::get_instance()->get_active_slug();

		if (empty($slug)) {
			\WP_CLI::line('No style variation is currently set (using base theme.json defaults).');
		} else {
			\WP_CLI::line(sprintf('Active style: %s', $slug));
		}
	}

	/**
	 * List all available style variations.
	 *
	 * ## EXAMPLES
	 *
	 *     lando wp theme-skin-switcher list-styles
	 *
	 * @subcommand list-styles
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function list_styles(array $args, array $assoc_args): void
	{
		unset($args, $assoc_args);

		$styles  = StyleVariationManager::get_available_styles();
		$current = StyleVariationManager::get_instance()->get_active_slug();

		$items = array_map(
			static function (string $slug) use ($current): array {
				return [
					'slug'   => $slug,
					'active' => $slug === $current ? 'yes' : '',
				];
			},
			$styles
		);

		\WP_CLI\Utils\format_items('table', $items, ['slug', 'active']);
	}
}
