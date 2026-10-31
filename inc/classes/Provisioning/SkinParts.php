<?php

/**
 * Applies a skin's header and footer.
 *
 * @package KitarisModule\ThemeSkinSwitcher
 */

declare(strict_types=1);

namespace KitarisModule\ThemeSkinSwitcher\Provisioning;

use KitarisModule\ThemeSkinSwitcher\SkinPackRegistry;
use KitarisModule\ThemeSkinSwitcher\Style\StyleVariationManager;

defined('ABSPATH') || defined('WP_CLI') || exit;

/**
 * A skin's meta.json can pair it with a shared header and footer from the
 * pack's pattern-archive folder. Skins are chosen by a site setting, so this
 * follows the setting and every route that changes it is covered.
 *
 * The pair is saved as the site's template parts, like a Site Editor save. A
 * part changed since it was applied is the owner's and is never replaced.
 */
final class SkinParts
{
	/** Hash of the content applied to a part. */
	private const APPLIED_META = '_theme_skin_switcher_applied';

	private const FOLDER = 'pattern-archive';

	public static function register(): void
	{
		$option = 'theme_mods_' . get_stylesheet();

		add_action("update_option_{$option}", [self::class, 'on_mods_updated'], 10, 2);
		add_action("add_option_{$option}", [self::class, 'on_mods_added'], 10, 2);
	}

	public static function on_mods_updated(mixed $old, mixed $new): void
	{
		$key  = StyleVariationManager::THEME_MOD_KEY;
		$slug = is_array($new) ? (string) ($new[$key] ?? '') : '';

		if ($slug !== '' && $slug !== (is_array($old) ? (string) ($old[$key] ?? '') : '')) {
			self::apply($slug);
		}
	}

	public static function on_mods_added(string $option, mixed $value): void
	{
		self::on_mods_updated([], $value);
	}

	/**
	 * Save a skin's header and footer as this site's template parts.
	 */
	public static function apply(string $slug): void
	{
		$meta    = StyleVariationManager::get_skin_meta($slug);
		$changed = false;

		foreach (['header', 'footer'] as $area) {
			$content = $meta[$area] !== '' ? self::content($area, $meta[$area]) : '';

			if ($content !== '' && self::save($area, $content)) {
				$changed = true;
			}
		}

		if ($changed) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- kitaris-performance's hook.
			do_action('kitaris_performance_purge_all');
		}
	}

	/**
	 * A pattern's markup, with menus named by slug pointed at this site's menus.
	 */
	private static function content(string $area, string $name): string
	{
		$file = SkinPackRegistry::get_pack_path() . self::FOLDER . "/{$area}/{$name}.php";

		if (! is_readable($file)) {
			return '';
		}

		ob_start();

		try {
			include $file;
		} finally {
			$html = trim((string) ob_get_clean());
		}

		return (string) preg_replace_callback(
			'/(,?)"kitarisMenu":"([a-z0-9-]+)"(,?)/',
			static function (array $match): string {
				$menu = self::menu($match[2]);

				if ($menu > 0) {
					return $match[1] . '"ref":' . $menu . $match[3];
				}

				return $match[1] !== '' && $match[3] !== '' ? ',' : '';
			},
			$html
		);
	}

	/**
	 * The published menu with a slug, 0 when there is none.
	 */
	private static function menu(string $slug): int
	{
		$ids = get_posts(
			[
				'post_type'      => 'wp_navigation',
				'post_status'    => 'publish',
				'name'           => $slug,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		return (int) ($ids[0] ?? 0);
	}

	/**
	 * @return bool Whether the part changed.
	 */
	private static function save(string $area, string $content): bool
	{
		$existing = get_block_template(get_stylesheet() . '//' . $area, 'wp_template_part');
		$id       = $existing && $existing->wp_id ? (int) $existing->wp_id : 0;

		if ($id > 0 && get_post_meta($id, self::APPLIED_META, true) !== md5((string) get_post_field('post_content', $id))) {
			return false;
		}

		$id = wp_insert_post(
			[
				'ID'           => $id,
				'post_type'    => 'wp_template_part',
				'post_status'  => 'publish',
				'post_name'    => $area,
				'post_title'   => ucfirst($area),
				'post_content' => wp_slash($content),
			],
			true
		);

		if (is_wp_error($id)) {
			return false;
		}

		wp_set_object_terms($id, get_stylesheet(), 'wp_theme');
		wp_set_object_terms($id, $area, 'wp_template_part_area');
		update_post_meta($id, self::APPLIED_META, md5((string) get_post_field('post_content', $id)));

		return true;
	}
}
