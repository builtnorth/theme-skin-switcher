<?php

declare(strict_types=1);

namespace ThemeSkinSwitcher;

use ThemeSkinSwitcher\Provisioning\StyleProvisioner;
use ThemeSkinSwitcher\RestAPI\StyleRoute;
use ThemeSkinSwitcher\Style\StyleEditorIntegration;
use ThemeSkinSwitcher\Style\StyleVariationManager;
use ThemeSkinSwitcher\Traits\SingletonTrait;

defined('ABSPATH') || defined('WP_CLI') || exit;

/**
 * Theme Skin Switcher application bootstrap.
 */
class App
{
	use SingletonTrait;

	private const VERSION = '1.0.0';

	public function boot(): void
	{
		$this->define_constants();

		add_action('after_setup_theme', [$this, 'register_theme_json_merge_early'], 0);
		add_action('after_setup_theme', [$this, 'register_skin_pack'], 1);
		add_action('after_setup_theme', [$this, 'init'], 5);
		add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
	}

	private function define_constants(): void
	{
		if (! defined('THEME_SKIN_SWITCHER_VERSION')) {
			define('THEME_SKIN_SWITCHER_VERSION', self::VERSION);
		}

		if (! defined('THEME_SKIN_SWITCHER_PATH')) {
			define('THEME_SKIN_SWITCHER_PATH', self::package_path());
		}

		if (! defined('THEME_SKIN_SWITCHER_URL')) {
			define('THEME_SKIN_SWITCHER_URL', self::package_url());
		}
	}

	/**
	 * Absolute filesystem path to the package root (trailing slash).
	 */
	public static function package_path(): string
	{
		static $path = null;

		if ($path === null) {
			$ref  = new \ReflectionClass(self::class);
			$path = trailingslashit(dirname($ref->getFileName(), 3));
		}

		return $path;
	}

	/**
	 * Public URL for package assets (build/, etc.).
	 */
	public static function package_url(): string
	{
		$path = wp_normalize_path(self::package_path());

		$candidates = [];

		if (function_exists('get_template_directory')) {
			$candidates[] = [
				wp_normalize_path(get_template_directory()),
				trailingslashit(get_template_directory_uri()),
			];
		}

		if (function_exists('get_stylesheet_directory')) {
			$candidates[] = [
				wp_normalize_path(get_stylesheet_directory()),
				trailingslashit(get_stylesheet_directory_uri()),
			];
		}

		foreach ($candidates as [$base, $base_uri]) {
			if ($base === '' || $base_uri === '') {
				continue;
			}

			$base = trailingslashit($base);

			if (str_starts_with($path, $base)) {
				return $base_uri . ltrim(substr($path, strlen($base)), '/');
			}
		}

		$content_dir = wp_normalize_path(WP_CONTENT_DIR);

		if (str_starts_with($path, $content_dir)) {
			return content_url(substr($path, strlen($content_dir)));
		}

		// Dev monorepo: wp-content/packages/theme-skin-switcher → composer_packages.
		return trailingslashit(content_url('packages/theme-skin-switcher'));
	}

	public function register_theme_json_merge_early(): void
	{
		StyleVariationManager::get_instance()->register_theme_json_merge_hooks();
	}

	/**
	 * Resolve design-skins from child (stylesheet) or parent (template).
	 */
	public function register_skin_pack(): void
	{
		$registry = SkinPackRegistry::get_instance();
		$registry->register_active_theme_pack();

		/**
		 * Fires after the active theme pack is registered. Plugins may call
		 * {@see SkinPackRegistry::register()} to override the auto-detected pack.
		 */
		do_action('theme_skin_switcher_register', $registry);
	}

	public function init(): void
	{
		StyleVariationManager::get_instance()->boot();
		StyleProvisioner::boot();
		StyleEditorIntegration::boot();

		add_action('rest_api_init', [StyleRoute::class, 'register']);
	}

	public function enqueue_editor_assets(): void
	{
		$asset_path = THEME_SKIN_SWITCHER_PATH . 'build/assets/editor/editor.asset.php';

		if (! is_readable($asset_path)) {
			return;
		}

		$asset = include $asset_path;

		wp_enqueue_script(
			'theme-skin-switcher-editor',
			THEME_SKIN_SWITCHER_URL . 'build/assets/editor/editor.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? THEME_SKIN_SWITCHER_VERSION,
			true
		);

		$style_path = THEME_SKIN_SWITCHER_PATH . 'build/assets/editor/editor.css';

		if (is_readable($style_path)) {
			wp_enqueue_style(
				'theme-skin-switcher-editor',
				THEME_SKIN_SWITCHER_URL . 'build/assets/editor/editor.css',
				[],
				$asset['version'] ?? THEME_SKIN_SWITCHER_VERSION
			);
		}
	}
}
