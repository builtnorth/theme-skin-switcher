<?php

/**
 * REST: active skin + Site Editor boot data (read-only).
 *
 * Uses siratik/rest-access's AdminPermission so an authenticated user with
 * `edit_theme_options` (Site Editor access) can read the current skin/style
 * payload — same capability the Site Editor itself already requires.
 *
 * @package KitarisModule\ThemeSkinSwitcher\RestAPI
 */

declare(strict_types=1);

namespace KitarisModule\ThemeSkinSwitcher\RestAPI;

use Siratik\Rest\Route\AbstractRestRoute;
use Siratik\Rest\Permission\PermissionFactory;
use KitarisModule\ThemeSkinSwitcher\SkinPackRegistry;
use KitarisModule\ThemeSkinSwitcher\Style\StyleVariationManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || defined('WP_CLI') || exit;

final class StyleRoute extends AbstractRestRoute
{
	public const REST_NAMESPACE = 'theme-skin-switcher/v1';
	public const REST_ROUTE = '/style';

	public function __construct()
	{
		parent::__construct(
			permissionResolver: PermissionFactory::admin_permission(
				capability: 'edit_theme_options',
			),
			namespace: self::REST_NAMESPACE,
		);
	}

	public static function register(): void
	{
		(new self())->register_routes();
	}

	public function register_routes(): void
	{
		$this->register_route(
			self::REST_ROUTE,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'handle_get_style'],
					'permission_callback' => [$this, 'check_permission'],
				],
			]
		);

		$this->register_route(
			self::REST_ROUTE . '/base',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'handle_get_base'],
					'permission_callback' => [$this, 'check_permission'],
					'args'                => [
						'slug' => [
							'type'     => 'string',
							'pattern'  => '^[a-z0-9-]+$',
							'required' => true,
						],
					],
				],
			]
		);
	}

	/**
	 * The theme's styles with a skin merged in, shaped like core's
	 * /wp/v2/global-styles/themes/{stylesheet} response.
	 */
	public function handle_get_base(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		$base = StyleVariationManager::get_theme_base_for_slug((string) $request->get_param('slug'));

		if (null === $base) {
			return new WP_Error('theme_skin_switcher_unknown_skin', __('That skin is not available.', 'theme-skin-switcher'), ['status' => 404]);
		}

		return new WP_REST_Response($base, 200);
	}

	public function handle_get_style(): WP_REST_Response
	{
		$manager = StyleVariationManager::get_instance();

		return new WP_REST_Response(
			[
				'slug'             => $manager->get_active_slug(),
				'available_styles' => StyleVariationManager::get_available_styles(),
				'styles'           => StyleVariationManager::get_editor_style_definitions(),
				'themeUrl'         => SkinPackRegistry::get_pack_url() ?: trailingslashit(get_template_directory_uri()),
				'styleFonts'       => StyleVariationManager::get_style_font_queries(),
				'switchPayloads'   => StyleVariationManager::get_all_client_switch_payloads(),
			],
			200
		);
	}
}
