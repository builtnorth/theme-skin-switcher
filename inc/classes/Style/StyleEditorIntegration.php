<?php

/**
 * Site Editor data for the theme skin switcher (script loads via editor bundle).
 *
 * @package KitarisModule\ThemeSkinSwitcher\Style
 * @since 1.0.0
 */

declare(strict_types=1);

namespace KitarisModule\ThemeSkinSwitcher\Style;

use KitarisModule\ThemeSkinSwitcher\RestAPI\StyleRoute;
use KitarisModule\ThemeSkinSwitcher\SkinPackRegistry;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class StyleEditorIntegration
{
	const LOCALIZE_OBJECT = 'theme_skin_switcher';

	/**
	 * Ensure boot data is available before the editor script runs.
	 */
	public static function boot(): void
	{
		add_action('init', [__CLASS__, 'register_site_editor_setting'], 20);
		add_action('enqueue_block_editor_assets', [__CLASS__, 'inline_boot_data'], 5);
		add_action('enqueue_block_editor_assets', [__CLASS__, 'inline_editor_script_data'], 100);
	}

	/**
	 * Expose the active skin via Site Settings so the editor Save button tracks changes.
	 * Value is stored in the theme mod only (not a duplicate option row).
	 */
	public static function register_site_editor_setting(): void
	{
		$key = StyleVariationManager::THEME_MOD_KEY;

		register_setting(
			'theme_skin_switcher',
			$key,
			[
				'type'              => 'string',
				'default'           => '',
				'label'             => __('theme skin', 'theme-skin-switcher'),
				'description'       => __(
					'Design skin for section patterns, dividers, and skin CSS.',
					'theme-skin-switcher'
				),
				'sanitize_callback' => 'sanitize_key',
				'show_in_rest'      => [
					'name'   => $key,
					'schema' => [
						'type'  => 'string',
						'title' => __('theme skin', 'theme-skin-switcher'),
					],
				],
			]
		);

		add_filter(
			'pre_option_' . $key,
			static function (): string {
				return StyleVariationManager::get_instance()->get_active_slug();
			}
		);

		add_filter(
			'pre_update_option_' . $key,
			static function ($value): bool {
				if (! is_string($value)) {
					return false;
				}

				StyleVariationManager::set_active_style($value);

				return false;
			}
		);
	}

	/**
	 * Inline boot data on wp-edit-site so it exists before theme-skin-switcher-editor executes.
	 */
	public static function inline_boot_data(): void
	{
		global $pagenow;

		if ($pagenow !== 'site-editor.php') {
			return;
		}

		if (!wp_script_is('wp-edit-site', 'registered')) {
			return;
		}

		wp_add_inline_script(
			'wp-edit-site',
			'window.' . self::LOCALIZE_OBJECT . ' = ' . wp_json_encode(self::get_localize_data()) . ';',
			'before'
		);
	}

	/**
	 * Inline full JSON boot data (avoids wp_localize_script flattening nested payloads).
	 */
	public static function inline_editor_script_data(): void
	{
		if (!wp_script_is('theme-skin-switcher-editor', 'enqueued')) {
			return;
		}

		wp_add_inline_script(
			'theme-skin-switcher-editor',
			'window.' . self::LOCALIZE_OBJECT . ' = ' . wp_json_encode(self::get_localize_data()) . ';',
			'before'
		);
	}

	/**
	 * Data passed to the skin switcher script.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_localize_data(): array
	{
		$manager = StyleVariationManager::get_instance();

		return [
			'restNamespace'  => StyleRoute::REST_NAMESPACE,
			'restRoute'      => StyleRoute::REST_ROUTE,
			'nonce'          => wp_create_nonce('wp_rest'),
			'themeUrl'       => SkinPackRegistry::get_pack_url() ?: trailingslashit(get_template_directory_uri()),
			'styleFonts'     => StyleVariationManager::get_style_font_queries(),
			'settingKey'     => StyleVariationManager::THEME_MOD_KEY,
			'activeSlug'     => $manager->get_active_slug(),
			'styles'         => StyleVariationManager::get_editor_style_definitions(),
			'switchPayloads' => StyleVariationManager::get_all_client_switch_payloads(),
		];
	}
}
