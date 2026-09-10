<?php

/**
 * REST: active skin + Site Editor boot data (read-only).
 *
 * Uses meridian/rest's AdminOperationalPermission so an authenticated user with
 * `edit_theme_options` (Site Editor access) can read the current skin/style
 * payload — same capability the Site Editor itself already requires.
 *
 * @package ThemeSkinSwitcher\RestAPI
 */

declare(strict_types=1);

namespace ThemeSkinSwitcher\RestAPI;

use Meridian\Rest\Route\AbstractRestRoute;
use Meridian\Rest\Permission\PermissionFactory;
use ThemeSkinSwitcher\SkinPackRegistry;
use ThemeSkinSwitcher\Style\StyleVariationManager;
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
			permissionResolver: PermissionFactory::admin_operational_permission(
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
