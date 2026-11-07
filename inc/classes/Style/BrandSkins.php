<?php

declare(strict_types=1);

namespace KitarisModule\ThemeSkinSwitcher\Style;

defined('ABSPATH') || defined('WP_CLI') || exit;

/**
 * Which skins a brand offers, from brand.json in the active (child) theme:
 *
 *     { "skins": { "collections": ["rugged"], "include": [], "exclude": [], "default": "blueprint" } }
 *
 * A skin offers itself to a brand through the collections in its meta.json.
 * Only what the switcher lists is narrowed: every skin in the library stays a
 * registered skin, so a site keeps a skin its brand no longer offers. Without
 * a brand file every skin is offered and there is no default.
 */
final class BrandSkins
{
	public const FILE = 'brand.json';

	/**
	 * This request's parsed config; false when there is none.
	 *
	 * @var array{collections: string[], include: string[], exclude: string[], default: string}|false|null
	 */
	private static array|false|null $config = null;

	/**
	 * The brand's skins settings, or null without a (valid) brand file.
	 *
	 * @return array{collections: string[], include: string[], exclude: string[], default: string}|null
	 */
	public static function config(): ?array
	{
		if (self::$config === null) {
			self::$config = self::read() ?? false;
		}

		return self::$config === false ? null : self::$config;
	}

	/**
	 * The skins to offer, in library order: those in the brand's collections,
	 * plus include, minus exclude, plus the site's current skin.
	 *
	 * @param string[] $library Every registered skin slug.
	 * @param string   $active  The site's current skin ('' for none).
	 * @return string[]
	 */
	public static function offered(array $library, string $active = ''): array
	{
		$config = self::config();

		if ($config === null) {
			return $library;
		}

		$offered = array_filter(
			$library,
			static function (string $slug) use ($config, $active): bool {
				if ($slug === $active) {
					return true;
				}

				if (in_array($slug, $config['exclude'], true)) {
					return false;
				}

				if (in_array($slug, $config['include'], true)) {
					return true;
				}

				$collections = StyleVariationManager::get_skin_meta($slug)['collections'];

				return array_intersect($collections, $config['collections']) !== [];
			}
		);

		return array_values($offered);
	}

	/**
	 * The brand's default skin when it is a registered skin, otherwise ''.
	 */
	public static function default_slug(): string
	{
		$default = self::config()['default'] ?? '';

		return $default !== '' && StyleVariationManager::is_registered_skin($default) ? $default : '';
	}

	/**
	 * Forget this request's config (tests, or after switching theme).
	 */
	public static function reset(): void
	{
		self::$config = null;
	}

	/**
	 * Parse and check brand.json; null when missing, unreadable or malformed.
	 *
	 * @return array{collections: string[], include: string[], exclude: string[], default: string}|null
	 */
	private static function read(): ?array
	{
		$file = trailingslashit(get_stylesheet_directory()) . self::FILE;

		if (! is_readable($file)) {
			return null;
		}

		$data  = wp_json_file_decode($file, ['associative' => true]);
		$skins = is_array($data) && is_array($data['skins'] ?? null) ? $data['skins'] : null;

		if ($skins === null) {
			return null;
		}

		$default = is_string($skins['default'] ?? null) ? sanitize_key($skins['default']) : '';

		return [
			'collections' => self::keys($skins['collections'] ?? []),
			'include'     => self::keys($skins['include'] ?? []),
			'exclude'     => self::keys($skins['exclude'] ?? []),
			'default'     => $default,
		];
	}

	/**
	 * A list of lowercase keys from untrusted JSON; anything else dropped.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	private static function keys(mixed $value): array
	{
		if (! is_array($value)) {
			return [];
		}

		$keys = array_map(static fn($item) => is_string($item) ? sanitize_key($item) : '', $value);

		return array_values(array_unique(array_filter($keys)));
	}
}
