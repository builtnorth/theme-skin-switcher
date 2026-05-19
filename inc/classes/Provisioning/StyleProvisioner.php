<?php

/**
 * ------------------------------------------------------------------
 * Style Provisioner
 * ------------------------------------------------------------------
 *
 * Registers the WP-CLI command and read-only REST API used by the
 * Site Editor skin switcher to load boot data.
 *
 * WP-CLI (write operations):
 *   lando wp theme-skin-switcher set-style <slug>
 *   lando wp theme-skin-switcher list-styles
 *   lando wp theme-skin-switcher get-style
 *
 * REST API (read-only):
 *   GET /wp-json/theme-skin-switcher/v1/style
 *
 * @package ThemeSkinSwitcher\Provisioning
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ThemeSkinSwitcher\Provisioning;

use ThemeSkinSwitcher\SkinPackRegistry;
use ThemeSkinSwitcher\Style\StyleVariationManager;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class StyleProvisioner
{
	/**
	 * REST API namespace.
	 */
	const REST_NAMESPACE = 'theme-skin-switcher/v1';

	/**
	 * REST API route.
	 */
	const REST_ROUTE = '/style';

	/**
	 * Capability required to read skin switcher data via REST.
	 */
	const REST_READ_CAPABILITY = 'edit_theme_options';

	/**
	 * Boot: register CLI commands and REST routes.
	 */
	public static function boot(): void
	{
		add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

		if (defined('WP_CLI') && WP_CLI) {
			\WP_CLI::add_command('theme-skin-switcher', __CLASS__);
		}
	}

	// -----------------------------------------------------------------------
	// REST API (read-only)
	// -----------------------------------------------------------------------

	/**
	 * Register read-only REST route for editor boot data.
	 */
	public static function register_rest_routes(): void
	{
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [__CLASS__, 'rest_get_style'],
				'permission_callback' => [__CLASS__, 'rest_get_permission'],
				'show_in_index'       => false,
			]
		);
	}

	/**
	 * Permission callback for GET /style.
	 *
	 * Requires an authenticated user who can edit theme/global styles (Site Editor).
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return bool|\WP_Error
	 */
	public static function rest_get_permission(\WP_REST_Request $request): bool|\WP_Error
	{
		unset($request);

		if (! is_user_logged_in()) {
			return new \WP_Error(
				'rest_not_logged_in',
				__('You must be logged in to access design skin data.', 'theme-skin-switcher'),
				['status' => 401]
			);
		}

		if (! current_user_can(self::REST_READ_CAPABILITY)) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to view design skins.', 'theme-skin-switcher'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * REST GET: return active skin and editor boot payloads.
	 *
	 * @return \WP_REST_Response
	 */
	public static function rest_get_style(): \WP_REST_Response
	{
		$manager = StyleVariationManager::get_instance();

		return rest_ensure_response([
			'slug'             => $manager->get_active_slug(),
			'available_styles' => StyleVariationManager::get_available_styles(),
			'styles'           => StyleVariationManager::get_editor_style_definitions(),
			'themeUrl'         => SkinPackRegistry::get_pack_url() ?: trailingslashit(get_template_directory_uri()),
			'styleFonts'       => StyleVariationManager::get_style_font_queries(),
			'switchPayloads'   => StyleVariationManager::get_all_client_switch_payloads(),
		]);
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
