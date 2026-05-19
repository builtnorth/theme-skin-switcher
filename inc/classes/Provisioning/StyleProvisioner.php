<?php

/**
 * ------------------------------------------------------------------
 * Style Provisioner
 * ------------------------------------------------------------------
 *
 * Registers the WP-CLI command and REST API endpoint used by the
 * WaaS onboarding flow to apply a style variation to a site.
 *
 * WP-CLI:
 *   lando wp theme-skin-switcher set-style <slug>
 *   lando wp theme-skin-switcher list-styles
 *   lando wp theme-skin-switcher get-style
 *
 * REST API:
 *   POST /wp-json/theme-skin-switcher/v1/style   { "slug": "concrete" }
 *   GET  /wp-json/theme-skin-switcher/v1/style
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
	 * Boot: register CLI commands and REST routes.
	 */
	public static function boot(): void
	{
		// Register REST API endpoint.
		add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

		// Register WP-CLI commands.
		if (defined('WP_CLI') && WP_CLI) {
			\WP_CLI::add_command('theme-skin-switcher', __CLASS__);
		}
	}

	// -----------------------------------------------------------------------
	// REST API
	// -----------------------------------------------------------------------

	/**
	 * Register REST API routes.
	 */
	public static function register_rest_routes(): void
	{
		register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [__CLASS__, 'rest_get_style'],
				'permission_callback' => [__CLASS__, 'rest_permission'],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [__CLASS__, 'rest_set_style'],
				'permission_callback' => [__CLASS__, 'rest_permission'],
				'args'                => [
					'slug' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => [__CLASS__, 'validate_style_slug'],
						'description'       => __('Style variation slug.', 'theme-skin-switcher'),
					],
				],
			],
		]);
	}

	/**
	 * Permission callback: require manage_options (site admin).
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return bool|\WP_Error
	 */
	public static function rest_permission(\WP_REST_Request $request): bool|\WP_Error
	{
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to manage styles.', 'theme-skin-switcher'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * REST GET: return the currently active style slug.
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

	/**
	 * REST POST: set the active style slug.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_set_style(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
	{
		$slug     = (string) $request->get_param('slug');
		$previous = StyleVariationManager::get_instance()->get_active_slug();
		$success  = StyleVariationManager::set_active_style($slug);

		if (!$success) {
			return new \WP_Error(
				'invalid_style',
				sprintf(
					/* translators: %s: attempted style slug */
					__('"%s" is not a valid style. Available styles: %s', 'theme-skin-switcher'),
					$slug,
					implode(', ', StyleVariationManager::get_available_styles())
				),
				['status' => 422]
			);
		}

		$payload = StyleVariationManager::get_client_switch_payload($slug, $previous);

		return rest_ensure_response([
			'success'        => true,
			'slug'           => $slug,
			'themeUrl'       => SkinPackRegistry::get_pack_url() ?: trailingslashit(get_template_directory_uri()),
			'styleFonts'     => StyleVariationManager::get_style_font_queries(),
			'styles'         => StyleVariationManager::get_editor_style_definitions(),
			'switchPayloads' => StyleVariationManager::get_all_client_switch_payloads(),
			...$payload,
		]);
	}

	/**
	 * Validate a style slug param for the REST API.
	 *
	 * @param string $slug The slug to validate.
	 * @return bool
	 */
	public static function validate_style_slug(string $slug): bool
	{
		return in_array($slug, StyleVariationManager::get_available_styles(), true);
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
	 * : The style slug to activate (e.g. arch, concrete, flow, slab).
	 *
	 * ## EXAMPLES
	 *
	 *     lando wp theme-skin-switcher set-style concrete
	 *     lando wp theme-skin-switcher set-style flow
	 *
	 * @subcommand set-style
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function set_style(array $args, array $assoc_args): void
	{
		if (empty($args[0])) {
			\WP_CLI::error('Please provide a style slug. Run `lando wp theme-skin-switcher list-styles` to see available options.');
		}

		$slug = sanitize_key($args[0]);

		if (!StyleVariationManager::set_active_style($slug)) {
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
		$styles  = StyleVariationManager::get_available_styles();
		$current = StyleVariationManager::get_instance()->get_active_slug();

		$items = array_map(function (string $slug) use ($current): array {
			return [
				'slug'   => $slug,
				'active' => $slug === $current ? 'yes' : '',
			];
		}, $styles);

		\WP_CLI\Utils\format_items('table', $items, ['slug', 'active']);
	}
}
