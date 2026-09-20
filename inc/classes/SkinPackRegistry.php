<?php

declare(strict_types=1);

namespace PolarisModule\ThemeSkinSwitcher;

use PolarisModule\ThemeSkinSwitcher\Traits\SingletonTrait;

defined('ABSPATH') || defined('WP_CLI') || exit;

/**
 * Registered skin pack (active child theme or parent theme).
 */
class SkinPackRegistry
{
	use SingletonTrait;

	/**
	 * @var array{id: string, path: string, url: string}|null
	 */
	private ?array $pack = null;

	/**
	 * Register the first theme directory that contains design-skins/.
	 *
	 * Prefers the active stylesheet (child) over the template (parent) so packs work
	 * with either a child theme or parent-only installs.
	 */
	public function register_active_theme_pack(): void
	{
		if ($this->has_pack()) {
			return;
		}

		$candidates = [];

		$stylesheet = get_stylesheet();
		$template   = get_template();

		if ($stylesheet !== '') {
			$candidates[] = [
				'id'   => sanitize_key($stylesheet),
				'path' => get_stylesheet_directory(),
				'url'  => get_stylesheet_directory_uri(),
			];
		}

		if ($template !== '' && $template !== $stylesheet) {
			$candidates[] = [
				'id'   => sanitize_key($template),
				'path' => get_template_directory(),
				'url'  => get_template_directory_uri(),
			];
		}

		foreach ($candidates as $candidate) {
			$this->register($candidate);

			if ($this->has_pack()) {
				return;
			}
		}
	}

	/**
	 * @param array{id?: string, path: string, url: string} $pack Pack metadata.
	 */
	public function register(array $pack): void
	{
		$path = rtrim($pack['path'], '/\\') . '/';
		$url  = trailingslashit($pack['url']);

		if (! is_dir($path . 'design-skins')) {
			return;
		}

		$this->pack = [
			'id'   => isset($pack['id']) ? sanitize_key($pack['id']) : 'default',
			'path' => $path,
			'url'  => $url,
		];
	}

	public function has_pack(): bool
	{
		return null !== $this->pack;
	}

	/**
	 * @return array{id: string, path: string, url: string}|null
	 */
	public function get_pack(): ?array
	{
		return $this->pack;
	}

	public static function get_pack_path(): string
	{
		$pack = self::get_instance()->get_pack();

		return $pack['path'] ?? '';
	}

	public static function get_pack_url(): string
	{
		$pack = self::get_instance()->get_pack();

		return $pack['url'] ?? '';
	}
}
