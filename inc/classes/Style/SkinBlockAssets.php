<?php

/**
 * Per-block CSS/JS for design skins.
 *
 * A skin can ship design-skins/{slug}/block-assets/{namespace}/{block}.{scss,js};
 * the theme build writes build/design-skins/{slug}/block-assets/ with a
 * manifest. Only the active skin's directory is handed to Kitaris BlockAssets,
 * after the parent and child theme, so the skin's rules win for that block.
 *
 * @package KitarisModule\ThemeSkinSwitcher
 */

declare(strict_types=1);

namespace KitarisModule\ThemeSkinSwitcher\Style;

use Kitaris\Blocks\BlockAssets;
use KitarisModule\ThemeSkinSwitcher\SkinPackRegistry;

defined('ABSPATH') || defined('WP_CLI') || exit;

final class SkinBlockAssets
{
	/**
	 * Load after the parent (20) and child (30) theme directories.
	 */
	public const PRIORITY = 40;

	/**
	 * Hook into Kitaris BlockAssets.
	 */
	public static function boot(): void
	{
		add_action('kitaris_register_block_assets', [self::class, 'register_active_skin']);
	}

	/**
	 * Add the active skin's block-assets directory.
	 *
	 * @param BlockAssets $block_assets Kitaris BlockAssets instance.
	 */
	public static function register_active_skin($block_assets): void
	{
		if (! $block_assets instanceof BlockAssets) {
			return;
		}

		$slug = StyleVariationManager::get_instance()->get_active_slug();

		if ($slug === '' || ! StyleVariationManager::is_registered_skin($slug)) {
			return;
		}

		$block_assets->add_directory(
			self::directory_for_slug($slug),
			self::url_for_slug($slug),
			self::PRIORITY,
			self::source_for_slug($slug)
		);
	}

	/**
	 * A skin's per-block stylesheets for live switching in the editor.
	 *
	 * IDs match the handles Kitaris registers, so the editor can swap the
	 * previous skin's files (printed as <link> or inlined <style>) for these.
	 *
	 * @param string $slug Skin slug.
	 * @return list<array{id: string, href: string}>
	 */
	public static function get_client_styles(string $slug): array
	{
		if (! class_exists(BlockAssets::class) || ! StyleVariationManager::is_registered_skin($slug)) {
			return [];
		}

		$directory = self::directory_for_slug($slug);
		$url       = self::url_for_slug($slug);
		$source    = self::source_for_slug($slug);
		$styles    = [];

		foreach ((new BlockAssets())->read_manifest($directory) as $block_name => $entry) {
			if (! isset($entry['style'])) {
				continue;
			}

			$href = $url . '/' . $entry['style'];

			$styles[] = [
				'id'   => BlockAssets::handle($source, $block_name),
				'href' => $entry['version'] !== '' ? add_query_arg('ver', $entry['version'], $href) : $href,
			];
		}

		return $styles;
	}

	/**
	 * Handle segment for a skin, e.g. skin-arch.
	 */
	public static function source_for_slug(string $slug): string
	{
		return 'skin-' . $slug;
	}

	private static function directory_for_slug(string $slug): string
	{
		return trailingslashit(SkinPackRegistry::get_pack_path()) . self::relative_directory($slug);
	}

	private static function url_for_slug(string $slug): string
	{
		return trailingslashit(SkinPackRegistry::get_pack_url()) . self::relative_directory($slug);
	}

	private static function relative_directory(string $slug): string
	{
		return StyleVariationManager::SKIN_BUILD_DIRECTORY . '/' . $slug . '/block-assets';
	}
}
