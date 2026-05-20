<?php

/**
 * ------------------------------------------------------------------
 * Style Variation Manager
 * ------------------------------------------------------------------
 *
 * Manages per-style asset loading for block themes using design skins.
 *
 * Each skin is a subdirectory design-skins/{slug}/ with a readable variation.json
 * (same shape as a theme.json style variation). Slugs are discovered from the
 * filesystem — no manual allow list.
 *
 * The active style is read from a theme mod ({@see self::THEME_MOD_KEY}),
 * with migration from legacy keys where applicable. Falls back to the current
 * WordPress global styles variation slug from the wp_global_styles CPT.
 *
 * The active skin's variation.json is merged into theme layer data via
 * {@see self::merge_active_skin_theme_json()} so presets and custom tokens
 * apply on front and in the editor.
 *
 * Filters: {@see 'theme_skin_switcher_client_switch_payload'},
 * {@see 'theme_skin_switcher_client_custom_theme_properties'}.
 * Deprecated aliases: {@see 'compass_waas_skin_client_switch_payload'},
 * {@see 'compass_waas_skin_client_custom_theme_properties'}.
 *
 * @package ThemeSkinSwitcher\Style
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ThemeSkinSwitcher\Style;

use Polaris\Base\Container;
use Polaris\EditorExperience\PatternConfig;
use Polaris\EditorExperience\SectionDividerConfig;
use ThemeSkinSwitcher\SkinPackRegistry;
use ThemeSkinSwitcher\Traits\SingletonTrait;

// Don't load directly.
defined('ABSPATH') || defined('WP_CLI') || exit;

class StyleVariationManager
{
	use SingletonTrait;

	/**
	 * Theme mod key for the active skin slug.
	 */
	public const THEME_MOD_KEY = 'theme_skin_switcher_active_style';

	/**
	 * @deprecated 2.0.0 Migrated automatically to {@see self::THEME_MOD_KEY}.
	 */
	public const LEGACY_THEME_MOD_KEY = 'compass_waas_active_style';

	/**
	 * Body class prefix (output: `{prefix}-{slug}` e.g. theme-skin-fresh).
	 */
	public const BODY_CLASS_PREFIX = 'theme-skin';

	/**
	 * Root directory for design skins (variation.json, style.scss, assets).
	 */
	public const SKINS_DIRECTORY = 'design-skins';

	/**
	 * Compiled skin CSS output (webpack: design-skins/{slug}/style.scss → style.css).
	 */
	public const SKIN_BUILD_DIRECTORY = 'build/design-skins';

	public const SKIN_SECTION_PATTERNS_DIR = 'section-patterns';

	public const SKIN_SECTION_DIVIDERS_DIR = 'section-dividers';

	public const SKIN_BLOCK_PATTERNS_DIR = 'block-patterns';

	public const SKIN_DEFAULT_DIRECTORY = '_default';

	/**
	 * @var array<string, list<string>> Canonical skin subdir => legacy folder names (migration).
	 */
	private const SKIN_SUBDIRECTORY_LEGACY = [
		self::SKIN_SECTION_PATTERNS_DIR => ['background-patterns'],
		self::SKIN_SECTION_DIVIDERS_DIR   => ['motion-dividers', 'dividers'],
		self::SKIN_BLOCK_PATTERNS_DIR     => ['patterns'],
	];

	/**
	 * Optional per-slug Google Fonts API `family` query values (weights, italics).
	 * When absent for a slug, {@see self::resolve_google_font_query()} uses variation.json.
	 */
	private const GOOGLE_FONT_QUERY_OVERRIDES = [
		'arch'     => 'Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600',
		'concrete' => 'Inter:wght@400;500;600;700',
	];

	/**
	 * Lowercase first font-family name (theme.json stack) -> Fonts API query fragment.
	 */
	private const KNOWN_GOOGLE_FONT_QUERIES = [
		'inter'               => 'Inter:wght@400;500;600;700',
		'cormorant garamond'  => 'Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600',
	];

	/**
	 * Cached slugs from design-skins/* (filesystem).
	 *
	 * @var array<int, string>|null
	 */
	private static ?array $discovered_skin_slugs = null;

	/**
	 * Whether theme.json merge hooks are registered (see register_theme_json_merge_hooks).
	 */
	private static bool $theme_json_hooks_registered = false;

	/**
	 * Cached active style slug.
	 */
	private ?string $active_slug = null;

	/**
	 * Register wp_theme_json_data_* filters as early as possible.
	 *
	 * WordPress caches the resolved theme layer on the first get_theme_data() call.
	 * Admin often resolves theme JSON before priority 5 — register at after_setup_theme:0.
	 */
	public function register_theme_json_merge_hooks(): void
	{
		if (self::$theme_json_hooks_registered) {
			return;
		}

		self::$theme_json_hooks_registered = true;

		add_filter('wp_theme_json_data_theme', [$this, 'merge_active_skin_theme_json'], 20);
		add_filter('wp_theme_json_data_user', [$this, 'merge_active_skin_user_theme_json'], 999);
	}

	/**
	 * Boot the manager — register all hooks (idempotent with register_theme_json_merge_hooks).
	 */
	public function boot(): void
	{
		$this->register_theme_json_merge_hooks();

		add_action('after_setup_theme', [$this, 'register_editor_styles_support'], 11);
		add_action('init', [$this, 'register_active_skin_editor_style'], 10);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_style_assets'], 20);
		add_action('init', [$this, 'register_style_patterns'], 25);

		add_filter('body_class', [$this, 'add_style_body_class']);
		add_filter('admin_body_class', [$this, 'add_style_admin_body_class']);
		add_filter('block_editor_settings_all', [$this, 'filter_block_editor_settings'], 20, 2);
		add_filter('editor_stylesheets', [$this, 'append_active_skin_editor_stylesheet']);
		add_action('enqueue_block_assets', [$this, 'enqueue_skin_for_editor_canvas'], 20);
		add_action('enqueue_block_editor_assets', [$this, 'enqueue_skin_for_site_editor'], 20);

		add_filter('theme_skin_switcher_editor_experience_data', [$this, 'inject_editor_experience_overrides']);
		add_filter('polaris_editor_experience_data', [$this, 'inject_editor_experience_overrides']);

		// Site Editor: user entity comes from REST (raw CPT), not WP_Theme_JSON_Resolver::get_user_data().
		add_filter('rest_prepare_wp_global_styles', [$this, 'merge_active_skin_into_rest_global_styles'], 10, 3);

		add_filter('theme_skin_switcher_section_pattern_base_directories', [$this, 'filter_section_pattern_base_directories'], 10, 2);
		add_filter('polaris_section_pattern_base_directories', [$this, 'filter_section_pattern_base_directories'], 10, 2);
	}

	/**
	 * CSS body class for a skin slug.
	 */
	public static function get_body_class_for_slug(string $slug): string
	{
		$slug = sanitize_key($slug);

		if ($slug === '') {
			return '';
		}

		return self::BODY_CLASS_PREFIX . '-' . $slug;
	}

	/**
	 * Read active skin from theme mod, migrating legacy keys when needed.
	 */
	private static function read_active_slug_from_theme_mod(): string
	{
		$slug = (string) get_theme_mod(self::THEME_MOD_KEY, '');

		if ($slug !== '') {
			return $slug;
		}

		$legacy = (string) get_theme_mod(self::LEGACY_THEME_MOD_KEY, '');

		if ($legacy === '') {
			return '';
		}

		set_theme_mod(self::THEME_MOD_KEY, $legacy);
		remove_theme_mod(self::LEGACY_THEME_MOD_KEY);

		return $legacy;
	}

	/**
	 * Apply a filter with a deprecated hook alias when listeners exist.
	 *
	 * @param mixed ...$args Arguments passed to apply_filters after the value.
	 * @return mixed
	 */
	private static function apply_filter_with_legacy_alias(string $hook, string $legacy_hook, mixed $value, ...$args): mixed
	{
		$value = apply_filters($hook, $value, ...$args);

		if ($legacy_hook !== $hook && has_filter($legacy_hook)) {
			$value = apply_filters($legacy_hook, $value, ...$args);
		}

		return $value;
	}

	/**
	 * Whether enqueue_block_assets should load canvas (iframe) styles in the block editor.
	 */
	private static function should_enqueue_in_block_assets(): bool
	{
		return is_admin() && ! wp_should_load_block_editor_scripts_and_styles();
	}

	/**
	 * Allow section pattern SVGs to load from the registered skin pack directory.
	 *
	 * @param string[] $base_directories Theme directories (stylesheet, template).
	 * @param string   $pattern_dir      Relative pattern directory from editor experience.
	 * @return string[]
	 */
	public function filter_section_pattern_base_directories(array $base_directories, string $pattern_dir): array
	{
		$pack_path = SkinPackRegistry::get_pack_path();

		if ($pack_path === '') {
			return $base_directories;
		}

		array_unshift($base_directories, untrailingslashit($pack_path));

		return $base_directories;
	}

	/**
	 * Merge the active skin's variation.json into resolved theme.json data.
	 *
	 * Skins live under design-skins/{slug}/ — outside WP's default /styles discovery —
	 * so without this hook, settings such as custom.border-radius never affect CSS variables.
	 *
	 * Runs at priority 20 so later filters still apply.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json Theme JSON object.
	 * @return \WP_Theme_JSON_Data
	 */
	public function merge_active_skin_theme_json($theme_json)
	{
		return $this->apply_active_skin_to_theme_json_data($theme_json);
	}

	/**
	 * Re-apply the active skin on top of the user global-styles origin.
	 *
	 * Without this, values saved in the wp_global_styles post (e.g. prior theme tokens)
	 * override the theme layer and keep rounded corners after switching to Slab.
	 *
	 * @param \WP_Theme_JSON_Data $user_json User origin theme.json object.
	 * @return \WP_Theme_JSON_Data
	 */
	public function merge_active_skin_user_theme_json($user_json)
	{
		return $this->apply_active_skin_to_theme_json_data($user_json);
	}

	/**
	 * @param \WP_Theme_JSON_Data|object $data Theme or user origin instance.
	 * @return \WP_Theme_JSON_Data|object
	 */
	private function apply_active_skin_to_theme_json_data($data)
	{
		if (! is_object($data) || ! method_exists($data, 'update_with')) {
			return $data;
		}

		$slug = $this->get_active_slug();

		if ($slug === '' || ! self::is_registered_skin($slug)) {
			return $data;
		}

		$merge = self::get_skin_variation_merge_array($slug);

		if ($merge === null) {
			return $data;
		}

		return $data->update_with($merge);
	}

	/**
	 * Patch global-styles REST responses so they match theme.json merges used on the front and in get_block_editor_settings().
	 *
	 * prepare_item_for_response() reads post_content directly, so {@see 'wp_theme_json_data_user'} never runs.
	 * The Site Editor preloads this endpoint and merges it with theme data in JS; without this, canvas styles drift.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @param \WP_Post         $post     Global styles post.
	 * @param \WP_REST_Request $request  Request.
	 * @return \WP_REST_Response
	 */
	public function merge_active_skin_into_rest_global_styles(\WP_REST_Response $response, \WP_Post $post, \WP_REST_Request $request)
	{
		unset($request);

		if ('wp_global_styles' !== $post->post_type) {
			return $response;
		}

		$stored = json_decode((string) $post->post_content, true);

		if (! is_array($stored) || empty($stored['isGlobalStylesUserThemeJSON'])) {
			return $response;
		}

		$slug = $this->get_active_slug();

		if ($slug === '' || ! self::is_registered_skin($slug)) {
			return $response;
		}

		$skin = self::get_skin_variation_merge_array($slug);

		if (null === $skin || ! class_exists('WP_Theme_JSON_Data') || ! class_exists('WP_Theme_JSON')) {
			return $response;
		}

		$data = $response->get_data();

		if (! is_array($data)) {
			return $response;
		}

		$settings = isset($data['settings']) ? json_decode(wp_json_encode($data['settings']), true) : [];
		$styles   = isset($data['styles']) ? json_decode(wp_json_encode($data['styles']), true) : [];

		if (! is_array($settings)) {
			$settings = [];
		}

		if (! is_array($styles)) {
			$styles = [];
		}

		$payload = new \WP_Theme_JSON_Data(
			[
				'version'  => \WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => $settings,
				'styles'   => $styles,
			],
			'custom'
		);

		$payload->update_with($skin);
		$merged = $payload->get_data();

		if (isset($merged['settings'])) {
			$data['settings'] = $merged['settings'];
		}

		if (array_key_exists('styles', $merged)) {
			$data['styles'] = $merged['styles'];
		}

		$response->set_data($data);

		return $response;
	}

	/**
	 * theme.json-shaped array for update_with(), without style-variation-only keys.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function get_skin_variation_merge_array(string $slug): ?array
	{
		$path = self::get_variation_json_path($slug);

		if (! is_readable($path)) {
			return null;
		}

		$raw = file_get_contents($path);

		if (! is_string($raw) || $raw === '') {
			return null;
		}

		$data = json_decode($raw, true);

		if (! is_array($data)) {
			return null;
		}

		unset($data['$schema'], $data['title'], $data['slug']);

		return $data;
	}

	/**
	 * Flatten theme.json settings.custom into theme CSS custom property names (WordPress convention).
	 *
	 * @param array        $node settings.custom subtree or nested object therein.
	 * @param list<string> $path Path segments under settings.custom.
	 * @return array<string, string> e.g. array( '--wp--custom--border-radius--small' => '0px' ).
	 */
	private static function flatten_theme_json_custom_to_css_vars(array $node, array $path = []): array
	{
		$out = [];

		foreach ($node as $key => $value) {
			if (! is_string($key) && ! is_int($key)) {
				continue;
			}

			$segment = is_int($key) ? (string) $key : $key;

			if (is_array($value)) {
				$out = array_merge(
					$out,
					self::flatten_theme_json_custom_to_css_vars($value, array_merge($path, [$segment]))
				);

				continue;
			}

			if (! is_string($value) && ! is_numeric($value)) {
				continue;
			}

			$sanitized = is_numeric($value)
				? (string) $value
				: sanitize_text_field((string) $value);

			if ($sanitized === '') {
				continue;
			}

			$var_path        = array_merge($path, [$segment]);
			$out['--wp--custom--' . implode('--', $var_path)] = $sanitized;
		}

		return $out;
	}

	/**
	 * Base theme.json as WP_Theme_JSON_Data (no active-skin filters) for per-slug client previews.
	 */
	private static ?\WP_Theme_JSON_Data $base_theme_json_data_for_client = null;

	/**
	 * Theme.json from disk — avoids double-merging the active skin from {@see merge_active_skin_theme_json()}.
	 */
	private static function get_base_theme_json_data_for_client(): ?\WP_Theme_JSON_Data
	{
		if (! class_exists('WP_Theme_JSON_Data')) {
			return null;
		}

		if (self::$base_theme_json_data_for_client instanceof \WP_Theme_JSON_Data) {
			return self::$base_theme_json_data_for_client;
		}

		$path = get_template_directory() . '/theme.json';

		if (! is_readable($path)) {
			return null;
		}

		$raw = file_get_contents($path);

		if (! is_string($raw) || $raw === '') {
			return null;
		}

		$data = json_decode($raw, true);

		if (! is_array($data)) {
			return null;
		}

		self::$base_theme_json_data_for_client = new \WP_Theme_JSON_Data($data, 'theme');

		return self::$base_theme_json_data_for_client;
	}

	/**
	 * Base theme.json merged with a skin variation (for client preview and token flattening).
	 */
	public static function get_merged_skin_theme_json_data(string $slug): ?\WP_Theme_JSON_Data
	{
		$merge = self::get_skin_variation_merge_array($slug);

		if (! is_array($merge)) {
			return null;
		}

		$base = self::get_base_theme_json_data_for_client();

		if (! $base instanceof \WP_Theme_JSON_Data) {
			return null;
		}

		return $base->update_with($merge);
	}

	/**
	 * Client-side theme token preview: merged settings.custom (base theme.json + skin variation).
	 *
	 * Skin variation.json often only overrides a subset of tokens (e.g. Flow only sets
	 * border-radius.button). Sending the delta alone leaves stale :root values from the
	 * previously active skin until save + reload.
	 *
	 * @return array<string, string>
	 */
	private static function get_client_custom_theme_properties(string $slug): array
	{
		$merged = self::get_merged_skin_theme_json_data($slug);

		if (! $merged instanceof \WP_Theme_JSON_Data) {
			$merge  = self::get_skin_variation_merge_array($slug);
			$custom = is_array($merge) ? ($merge['settings']['custom'] ?? null) : null;

			$properties = is_array($custom)
				? self::flatten_theme_json_custom_to_css_vars($custom)
				: [];

			return self::apply_filter_with_legacy_alias(
				'theme_skin_switcher_client_custom_theme_properties',
				'compass_waas_skin_client_custom_theme_properties',
				$properties,
				$slug,
				null
			);
		}

		$custom = $merged->get_data()['settings']['custom'] ?? null;

		if (! is_array($custom)) {
			return self::apply_filter_with_legacy_alias(
				'theme_skin_switcher_client_custom_theme_properties',
				'compass_waas_skin_client_custom_theme_properties',
				[],
				$slug,
				$merged
			);
		}

		$properties = self::flatten_theme_json_custom_to_css_vars($custom);

		return self::apply_filter_with_legacy_alias(
			'theme_skin_switcher_client_custom_theme_properties',
			'compass_waas_skin_client_custom_theme_properties',
			$properties,
			$slug,
			$merged
		);
	}

	/**
	 * CSS compiled from variation.json only (styles, presets, variables defined on the skin).
	 *
	 * Grows automatically as skins add settings/styles blocks to variation.json without
	 * changing the client switcher.
	 *
	 * @return string Non-empty CSS or empty string.
	 */
	private static function get_client_variation_theme_json_css(string $slug): string
	{
		if (! class_exists('WP_Theme_JSON') || ! class_exists('WP_Theme_JSON_Data')) {
			return '';
		}

		$merge = self::get_skin_variation_merge_array($slug);

		if (! is_array($merge)) {
			return '';
		}

		$has_settings = ! empty($merge['settings']) && is_array($merge['settings']);
		$has_styles   = ! empty($merge['styles']) && is_array($merge['styles']);

		if (! $has_settings && ! $has_styles) {
			return '';
		}

		$payload = $merge;

		if (! isset($payload['version']) && class_exists('WP_Theme_JSON')) {
			$payload['version'] = \WP_Theme_JSON::LATEST_SCHEMA;
		}

		$skin_data  = new \WP_Theme_JSON_Data($payload, 'theme');
		$theme_json = new \WP_Theme_JSON($skin_data->get_data());
		$css        = $theme_json->get_stylesheet(
			['variables', 'presets', 'styles'],
			['theme']
		);

		if (! is_string($css)) {
			return '';
		}

		$css = trim($css);

		return $css !== '' ? $css : '';
	}

	/**
	 * Absolute filesystem path to design-skins/.
	 */
	public static function get_skins_base_path(): string
	{
		$pack_path = SkinPackRegistry::get_pack_path();

		if ($pack_path === '') {
			return '';
		}

		return $pack_path . self::SKINS_DIRECTORY . '/';
	}

	/**
	 * Theme-relative path to compiled skin CSS e.g. build/design-skins/fresh/style.css.
	 */
	public static function get_skin_compiled_style_relative_path(string $slug): string
	{
		return self::SKIN_BUILD_DIRECTORY . '/' . $slug . '/style.css';
	}

	/**
	 * Cache-bust token for compiled skin CSS (filemtime). Empty when the file is missing.
	 */
	public static function get_skin_style_css_version_for_slug(string $slug): string
	{
		if ($slug === '' || ! self::is_registered_skin($slug)) {
			return '';
		}

		$path = SkinPackRegistry::get_pack_path() . self::get_skin_compiled_style_relative_path($slug);

		if ($path === '' || ! is_readable($path)) {
			return '';
		}

		$mtime = filemtime($path);

		return $mtime !== false ? (string) $mtime : '';
	}

	/**
	 * Theme-relative path (no leading slash) e.g. design-skins/fresh/variation.json.
	 */
	public static function get_skin_relative_path(string $slug, string $filename = ''): string
	{
		$path = self::SKINS_DIRECTORY . '/' . $slug;

		if ($filename !== '') {
			$path .= '/' . ltrim($filename, '/');
		}

		return $path;
	}

	/**
	 * Absolute path to a skin directory.
	 */
	public static function get_skin_directory_path(string $slug): string
	{
		return self::get_skins_base_path() . $slug . '/';
	}

	/**
	 * Public URL for a skin directory (trailing slash).
	 */
	public static function get_skin_directory_uri(string $slug): string
	{
		return SkinPackRegistry::get_pack_url() . self::SKINS_DIRECTORY . '/' . $slug . '/';
	}

	/**
	 * Theme-relative subdirectory when present (checks canonical + legacy folder names).
	 *
	 * @param string $slug      Skin slug.
	 * @param string $canonical One of SKIN_SECTION_PATTERNS_DIR, SKIN_SECTION_DIVIDERS_DIR, SKIN_BLOCK_PATTERNS_DIR.
	 */
	public static function resolve_skin_subdirectory_relative(string $slug, string $canonical): ?string
	{
		$names = array_merge(
			[$canonical],
			self::SKIN_SUBDIRECTORY_LEGACY[$canonical] ?? []
		);

		foreach ($names as $dir) {
			$path = self::get_skin_directory_path($slug) . $dir;

			if (! is_dir($path)) {
				continue;
			}

			if (self::SKIN_BLOCK_PATTERNS_DIR === $canonical) {
				if (! empty(glob($path . '/*.php'))) {
					return self::get_skin_relative_path($slug, $dir);
				}

				continue;
			}

			if (! empty(glob($path . '/*.svg'))) {
				return self::get_skin_relative_path($slug, $dir);
			}
		}

		return null;
	}

	/**
	 * Build available_patterns labels from SVG filenames in a theme-relative directory.
	 *
	 * @return array<string, string> Slug => translated label.
	 */
	public static function discover_labeled_assets_from_relative_directory(string $relative_directory): array
	{
		$pack_path = SkinPackRegistry::get_pack_path();

		if ($pack_path === '') {
			return [];
		}

		$directory = $pack_path . ltrim($relative_directory, '/') . '/';

		if (! is_dir($directory)) {
			return [];
		}

		$labels = [];

		foreach (glob($directory . '*.svg') ?: [] as $file) {
			if (! is_readable($file)) {
				continue;
			}

			$slug = sanitize_key(pathinfo($file, PATHINFO_FILENAME));

			if ($slug === '') {
				continue;
			}

			$labels[ $slug ] = ucwords(str_replace(['-', '_'], ' ', $slug));
		}

		ksort($labels);

		return $labels;
	}

	/**
	 * Absolute asset URLs for editor pattern previews (avoids iframe path / skin sync issues).
	 *
	 * @param array<string, mixed> $patterns Pattern config (pattern_directory, available_patterns).
	 * @return array<string, mixed>
	 */
	/**
	 * Resolve a section-pattern asset URL (active skin dir, then design-skins/_default).
	 */
	public static function resolve_pattern_asset_url_for_slug(string $slug, string $primary_directory = ''): string
	{
		$slug = sanitize_key($slug);

		if ($slug === '') {
			return '';
		}

		$pack_url  = SkinPackRegistry::get_pack_url();
		$pack_path = SkinPackRegistry::get_pack_path();

		if ($pack_url === '' || $pack_path === '') {
			return '';
		}

		$directories = array_unique(
			array_filter(
				[
					trim($primary_directory, '/'),
					self::get_skin_relative_path(self::SKIN_DEFAULT_DIRECTORY, self::SKIN_SECTION_PATTERNS_DIR),
				]
			)
		);

		$extensions = ['svg', 'png', 'jpg', 'jpeg'];

		foreach ($directories as $directory) {
			foreach ($extensions as $ext) {
				$relative = $directory . '/' . $slug . '.' . $ext;
				$path     = $pack_path . $relative;

				if (is_readable($path)) {
					return $pack_url . $relative;
				}
			}
		}

		return '';
	}

	public static function append_pattern_asset_urls(array $patterns): array
	{
		if (empty($patterns['available_patterns']) || ! is_array($patterns['available_patterns'])) {
			return $patterns;
		}

		$primary_directory = isset($patterns['pattern_directory'])
			? trim((string) $patterns['pattern_directory'], '/')
			: '';

		$default_directory = self::get_skin_relative_path(self::SKIN_DEFAULT_DIRECTORY, self::SKIN_SECTION_PATTERNS_DIR);

		$slugs = array_unique(
			array_merge(
				array_keys($patterns['available_patterns']),
				array_keys(self::discover_labeled_assets_from_relative_directory($default_directory))
			)
		);

		$urls = [];

		foreach ($slugs as $slug) {
			$url = self::resolve_pattern_asset_url_for_slug((string) $slug, $primary_directory);

			if ($url !== '') {
				$urls[ sanitize_key((string) $slug) ] = $url;
			}
		}

		if ($urls !== []) {
			$patterns['pattern_urls'] = $urls;
		}

		return $patterns;
	}

	/**
	 * Subdirectories of design-skins/ whose variation.json defines a full design skin.
	 *
	 * @return string[] Sorted slugs.
	 */
	public static function discover_skin_slugs(): array
	{
		if (self::$discovered_skin_slugs !== null) {
			return self::$discovered_skin_slugs;
		}

		$base = self::get_skins_base_path();

		if ($base === '' || ! is_dir($base)) {
			self::$discovered_skin_slugs = [];

			return self::$discovered_skin_slugs;
		}

		$slugs = [];

		foreach (scandir($base) ?: [] as $item) {
			if ('.' === $item || '..' === $item || str_starts_with($item, '_')) {
				continue;
			}

			$path = $base . $item;

			if (! is_dir($path) || ! self::is_valid_skin_directory_name($item)) {
				continue;
			}

			$variation = $path . '/variation.json';

			if (is_readable($variation)) {
				$slugs[] = $item;
			}
		}

		sort($slugs);
		self::$discovered_skin_slugs = $slugs;

		return self::$discovered_skin_slugs;
	}

	/**
	 * Whether a slug matches a skin directory under design-skins/.
	 */
	public static function is_registered_skin(string $slug): bool
	{
		if ($slug === '' || ! self::is_valid_skin_directory_name($slug)) {
			return false;
		}

		return in_array($slug, self::discover_skin_slugs(), true);
	}

	/**
	 * True when the skin variation declares at least one fontFace with a theme-relative file: source.
	 * Those fonts are served from the theme; skip Google Fonts enqueue / editor fontCssUrl.
	 */
	private static function variation_defines_self_hosted_font_faces(string $slug): bool
	{
		$merge = self::get_skin_variation_merge_array($slug);

		if (! is_array($merge)) {
			return false;
		}

		$families = $merge['settings']['typography']['fontFamilies'] ?? null;

		if (! is_array($families)) {
			return false;
		}

		foreach ($families as $fam) {
			if (! is_array($fam) || empty($fam['fontFace']) || ! is_array($fam['fontFace'])) {
				continue;
			}

			foreach ($fam['fontFace'] as $face) {
				if (! is_array($face) || empty($face['src'])) {
					continue;
				}

				$src_list = $face['src'];

				if (! is_array($src_list)) {
					$src_list = [$src_list];
				}

				foreach ($src_list as $item) {
					if (is_string($item) && str_starts_with($item, 'file:')) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Google Fonts API `family` query value for enqueue / editor payloads, or null.
	 */
	public static function get_google_font_query_for_slug(string $slug): ?string
	{
		if (isset(self::GOOGLE_FONT_QUERY_OVERRIDES[$slug])) {
			return self::GOOGLE_FONT_QUERY_OVERRIDES[$slug];
		}

		if (self::variation_defines_self_hosted_font_faces($slug)) {
			return null;
		}

		$path = self::get_variation_json_path($slug);

		if (! is_readable($path)) {
			return null;
		}

		$raw = file_get_contents($path);

		if (! is_string($raw) || $raw === '') {
			return null;
		}

		$data = json_decode($raw, true);

		if (! is_array($data)) {
			return null;
		}

		$families = $data['settings']['typography']['fontFamilies'] ?? [];
		$stack    = '';

		if (is_array($families)) {
			foreach ($families as $fam) {
				if (! is_array($fam)) {
					continue;
				}

				if (($fam['slug'] ?? '') === 'primary' && ! empty($fam['fontFamily'])) {
					$stack = (string) $fam['fontFamily'];
					break;
				}
			}

			if ($stack === '' && isset($families[0]['fontFamily'])) {
				$stack = (string) $families[0]['fontFamily'];
			}
		}

		if ($stack === '') {
			return null;
		}

		$first = trim(explode(',', $stack)[0]);
		$first = trim($first, " \t\n\r\0\x0B\"'");
		$key   = strtolower($first);

		return self::KNOWN_GOOGLE_FONT_QUERIES[$key] ?? null;
	}

	/**
	 * Map slug => Google Fonts query for all discovered skins (editor boot / REST).
	 *
	 * @return array<string, string>
	 */
	public static function get_style_font_queries(): array
	{
		$out = [];

		foreach (self::discover_skin_slugs() as $slug) {
			$query = self::get_google_font_query_for_slug($slug);

			if (null !== $query) {
				$out[$slug] = $query;
			}
		}

		return $out;
	}

	private static function is_valid_skin_directory_name(string $name): bool
	{
		return 1 === preg_match('/^[a-z0-9][a-z0-9-]*$/', $name);
	}

	/**
	 * Get the active style slug.
	 *
	 * Priority:
	 *   1. Cached value (within same request).
	 *   2. Theme mod set during provisioning (fastest persistent path).
	 *   3. Current WordPress global styles variation from the CPT.
	 *   4. Empty string (no style active — base theme.json defaults apply).
	 */
	public function get_active_slug(): string
	{
		if (null !== $this->active_slug) {
			return $this->active_slug;
		}

		// 1. Check theme mod (includes legacy key migration).
		$slug = self::read_active_slug_from_theme_mod();

		// 2. Fall back to reading the global styles CPT variation.
		if (empty($slug)) {
			$slug = $this->get_slug_from_global_styles();
		}

		// 3. Validate slug matches a directory under design-skins/.
		if (! self::is_registered_skin($slug)) {
			$slug = '';
		}

		$this->active_slug = $slug;

		return $this->active_slug;
	}

	/**
	 * Enable editor-styles so {@see add_editor_style()} and {@see append_active_skin_editor_stylesheet()} apply in the canvas.
	 */
	public function register_editor_styles_support(): void
	{
		add_theme_support('editor-styles');
	}

	/**
	 * Register the active skin stylesheet for block/classic editor (relative theme path).
	 */
	public function register_active_skin_editor_style(): void
	{
		$slug = $this->get_active_slug();

		if ($slug === '' || ! self::is_registered_skin($slug)) {
			return;
		}

		// Skin CSS is enqueued via append_active_skin_editor_stylesheet (absolute pack URL).
	}

	/**
	 * Absolute URL for the active skin's style.css, or empty when none.
	 */
	public static function get_active_skin_style_css_url(): string
	{
		return self::get_active_skin_style_css_url_for_slug(
			self::get_instance()->get_active_slug()
		);
	}

	/**
	 * @param string $slug Skin slug.
	 */
	public static function get_active_skin_style_css_url_for_slug(string $slug): string
	{
		if ($slug === '' || ! self::is_registered_skin($slug)) {
			return '';
		}

		$relative = self::get_skin_compiled_style_relative_path($slug);
		$path     = SkinPackRegistry::get_pack_path() . $relative;

		if ($path === '' || ! is_readable($path)) {
			return '';
		}

		return SkinPackRegistry::get_pack_url() . $relative;
	}

	/**
	 * @param string[] $stylesheets Editor stylesheet URLs.
	 * @return string[]
	 */
	public function append_active_skin_editor_stylesheet(array $stylesheets): array
	{
		$slug = $this->get_active_slug();
		$url  = self::get_active_skin_style_css_url();

		if ($url === '') {
			return $stylesheets;
		}

		$version = self::get_skin_style_css_version_for_slug($slug);

		if ($version !== '') {
			$url = add_query_arg('ver', $version, $url);
		}

		if (! in_array($url, $stylesheets, true)) {
			$stylesheets[] = $url;
		}

		return $stylesheets;
	}

	/**
	 * Enqueue per-style CSS on the frontend.
	 */
	public function enqueue_style_assets(): void
	{
		$slug = $this->get_active_slug();

		if (empty($slug)) {
			return;
		}

		$css_url = self::get_active_skin_style_css_url();

		if ($css_url !== '') {
			$version = self::get_skin_style_css_version_for_slug($slug);

			wp_enqueue_style(
				"theme-skin-switcher-style-{$slug}",
				$css_url,
				[],
				$version !== '' ? $version : (defined('THEME_SKIN_SWITCHER_VERSION') ? THEME_SKIN_SWITCHER_VERSION : null)
			);
		}

		$this->enqueue_google_font($slug);
	}

	/**
	 * Enqueue active skin CSS/fonts into the editor canvas iframe only.
	 */
	public function enqueue_skin_for_editor_canvas(): void
	{
		if (! self::should_enqueue_in_block_assets()) {
			return;
		}

		$this->enqueue_style_assets();
		$this->enqueue_editor_canvas_token_styles();
	}

	/**
	 * Site Editor renders blocks on the admin shell (not in .editor-styles-wrapper iframe),
	 * so canvas-only enqueue_block_assets does not load skin CSS there.
	 */
	public function enqueue_skin_for_site_editor(): void
	{
		global $pagenow;

		if ($pagenow !== 'site-editor.php') {
			return;
		}

		$this->enqueue_style_assets();
	}

	/**
	 * Token + variation.json overrides for the editor canvas (enqueue_block_assets iframe pass).
	 */
	private function enqueue_editor_canvas_token_styles(): void
	{
		$slug = $this->get_active_slug();

		if ($slug === '') {
			return;
		}

		$inline = self::build_editor_canvas_inline_css($slug);

		if ($inline === '') {
			return;
		}

		$handle = 'theme-skin-switcher-canvas-tokens';

		wp_register_style($handle, false, [], defined('THEME_SKIN_SWITCHER_VERSION') ? THEME_SKIN_SWITCHER_VERSION : null);
		wp_enqueue_style($handle);
		wp_add_inline_style($handle, $inline);
	}

	/**
	 * @return string CSS for :root / .editor-styles-wrapper in the canvas iframe.
	 */
	private static function build_editor_canvas_inline_css(string $slug): string
	{
		$body_class = self::get_body_class_for_slug($slug);

		if ($body_class === '') {
			return '';
		}

		$scope = ':root, body.' . $body_class . ', .editor-styles-wrapper';
		$parts = [];

		$properties = self::get_client_custom_theme_properties($slug);

		if ($properties !== []) {
			$decls = [];

			foreach ($properties as $key => $val) {
				if ($val === null || $val === '') {
					continue;
				}

				$decls[] = $key . ': ' . (string) $val;
			}

			if ($decls !== []) {
				$parts[] = $scope . '{' . implode(';', $decls) . '}';
			}
		}

		$variation_css = self::get_client_variation_theme_json_css($slug);

		if ($variation_css !== '') {
			$parts[] = preg_replace('/:root\s*\{/', $scope . '{', $variation_css) ?? $variation_css;
		}

		return implode("\n", $parts);
	}

	/**
	 * Enqueue the Google Font for the given style slug (frontend only).
	 */
	private function enqueue_google_font(string $slug): void
	{
		$font_query = self::get_google_font_query_for_slug($slug);

		if (null === $font_query || $font_query === '') {
			return;
		}

		$font_url = "https://fonts.googleapis.com/css2?family={$font_query}&display=swap";

		wp_enqueue_style(
			"theme-skin-switcher-font-{$slug}",
			$font_url,
			[],
			null // No version — Google Fonts manages cache-busting.
		);
	}

	/**
	 * Register style-specific block patterns from design-skins/{slug}/block-patterns/*.php
	 */
	public function register_style_patterns(): void
	{
		$slug = $this->get_active_slug();

		if (empty($slug)) {
			return;
		}

		$relative = self::resolve_skin_subdirectory_relative($slug, self::SKIN_BLOCK_PATTERNS_DIR);

		if ($relative === null) {
			return;
		}

		$patterns_dir = SkinPackRegistry::get_pack_path() . $relative . '/';

		if (! is_dir($patterns_dir)) {
			return;
		}

		$pattern_files = glob($patterns_dir . '*.php');

		if (empty($pattern_files)) {
			return;
		}

		foreach ($pattern_files as $pattern_file) {
			require_once $pattern_file;
		}
	}

	/**
	 * Add a CSS body class for the active style (e.g. .theme-skin-fresh).
	 * Allows style-specific CSS selectors to be scoped without affecting other styles.
	 *
	 * @param array $classes Existing body classes.
	 * @return array Modified body classes.
	 */
	public function add_style_body_class(array $classes): array
	{
		$slug = $this->get_active_slug();

		if (!empty($slug)) {
			$classes[] = self::get_body_class_for_slug($slug);
		}

		return $classes;
	}

	/**
	 * Add skin body class to Site Editor / block editor admin screens.
	 *
	 * @param string $classes Space-separated admin body classes.
	 * @return string
	 */
	public function add_style_admin_body_class(string $classes): string
	{
		$slug = $this->get_active_slug();

		if (empty($slug)) {
			return $classes;
		}

		return $classes . ' ' . self::get_body_class_for_slug($slug);
	}

	/**
	 * Expose active skin class for editor scripts (canvas rehydration).
	 *
	 * @param array<string, mixed>     $settings Block editor settings.
	 * @param \WP_Block_Editor_Context $context  Editor context.
	 * @return array<string, mixed>
	 */
	public function filter_block_editor_settings(array $settings, \WP_Block_Editor_Context $context): array
	{
		unset($context);

		$slug = $this->get_active_slug();

		if (!empty($slug)) {
			$settings['themeSkinSwitcherActiveSlug']  = $slug;
			$settings['themeSkinSwitcherActiveClass'] = self::get_body_class_for_slug($slug);
		}

		return $settings;
	}

	/**
	 * Inject style-specific overrides into editor experience data.
	 *
	 * Hooked into {@see 'theme_skin_switcher_editor_experience_data'} and, when
	 * present, {@see 'polaris_editor_experience_data'} for backward compatibility.
	 *
	 * @param array $editor_experience Assembled editor experience config.
	 * @return array Modified editor experience config.
	 */
	public function inject_editor_experience_overrides(array $editor_experience): array
	{
		return self::apply_editor_experience_for_slug($editor_experience, $this->get_active_slug());
	}

	/**
	 * Theme-registered editor experience (patterns, dividers) without active-skin overrides.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_theme_editor_experience_baseline(): array
	{
		if (! class_exists(Container::class)) {
			return [];
		}

		try {
			$repository = Container::instance()->get('config')->get_repository();
		} catch (\Throwable $e) {
			return [];
		}

		$editor_experience = [];

		$patterns = PatternConfig::get($repository);

		if ($patterns !== []) {
			$editor_experience['patterns'] = $patterns;
		}

		$section_divider = SectionDividerConfig::get($repository);

		if ($section_divider !== []) {
			$editor_experience['section_divider'] = $section_divider;
		}

		return $editor_experience;
	}

	/**
	 * Apply per-skin editor experience overrides for a given slug.
	 *
	 * @param array  $editor_experience Base editor experience config.
	 * @param string $slug              Active skin slug.
	 * @return array Modified editor experience config.
	 */
	public static function apply_editor_experience_for_slug(array $editor_experience, string $slug): array
	{
		if ($slug === '' || ! self::is_registered_skin($slug)) {
			return $editor_experience;
		}

		$patterns_relative = self::resolve_skin_subdirectory_relative($slug, self::SKIN_SECTION_PATTERNS_DIR);

		if ($patterns_relative !== null) {
			$editor_experience['patterns'] = $editor_experience['patterns'] ?? [];
			$editor_experience['patterns']['enabled']            = true;
			$editor_experience['patterns']['pattern_directory']  = $patterns_relative;

			$discovered = self::discover_labeled_assets_from_relative_directory($patterns_relative);

			if ($discovered !== []) {
				$editor_experience['patterns']['available_patterns'] = $discovered;
			}
		}

		$motion_dividers_relative = self::resolve_skin_subdirectory_relative($slug, self::SKIN_SECTION_DIVIDERS_DIR);

		if ($motion_dividers_relative !== null) {
			$editor_experience['section_divider'] = $editor_experience['section_divider'] ?? [];
			$editor_experience['section_divider']['enabled']           = true;
			$editor_experience['section_divider']['divider_directory'] = $motion_dividers_relative;
		}

		$editor_experience['active_style'] = [
			'slug'       => $slug,
			'assets_url' => self::get_skin_directory_uri($slug),
		];

		if (! empty($editor_experience['patterns']) && is_array($editor_experience['patterns'])) {
			$editor_experience['patterns'] = self::append_pattern_asset_urls($editor_experience['patterns']);
		}

		return $editor_experience;
	}

	/**
	 * Payload for applying a skin switch in the editor without a full page reload.
	 *
	 * @param string $slug          New skin slug.
	 * @param string $previous_slug Previous skin slug (may be empty).
	 * @return array<string, mixed>
	 */
	public static function get_client_switch_payload(string $slug, string $previous_slug = ''): array
	{
		$style_css_url = self::get_active_skin_style_css_url_for_slug($slug);
		$style_version = self::get_skin_style_css_version_for_slug($slug);

		if ($style_css_url !== '' && $style_version !== '') {
			$style_css_url = add_query_arg('ver', $style_version, $style_css_url);
		}

		$font_css_url = '';
		$font_query   = self::get_google_font_query_for_slug($slug);

		if (null !== $font_query && $font_query !== '') {
			$font_css_url = 'https://fonts.googleapis.com/css2?family=' . $font_query . '&display=swap';
		}

		$editor_experience = self::apply_editor_experience_for_slug(
			self::get_theme_editor_experience_baseline(),
			$slug
		);

		$payload = [
			'slug'                    => $slug,
			'previousSlug'            => $previous_slug,
			'bodyClass'               => self::get_body_class_for_slug($slug),
			'previousBodyClass'       => $previous_slug ? self::get_body_class_for_slug($previous_slug) : '',
			'styleCssUrl'             => $style_css_url,
			'fontCssUrl'              => $font_css_url,
			'editorExperience'        => $editor_experience,
			'customThemeProperties'   => self::get_client_custom_theme_properties($slug),
			'variationThemeJsonCss'   => self::get_client_variation_theme_json_css($slug),
		];

		return self::apply_filter_with_legacy_alias(
			'theme_skin_switcher_client_switch_payload',
			'compass_waas_skin_client_switch_payload',
			$payload,
			$slug,
			$previous_slug
		);
	}

	/**
	 * Precomputed switch payloads keyed by slug (for instant editor apply).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_all_client_switch_payloads(): array
	{
		$payloads = [];

		foreach (self::discover_skin_slugs() as $slug) {
			$payloads[$slug] = self::get_client_switch_payload($slug);
		}

		return $payloads;
	}

	/**
	 * Preview image for the skin switcher (design-skins/{slug}/images/preview.*).
	 */
	public static function get_skin_preview_image_url(string $slug): string
	{
		if (! self::is_registered_skin($slug)) {
			return '';
		}

		$candidates = ['preview.webp', 'preview.jpg', 'preview.jpeg', 'preview.png', 'preview.svg'];

		foreach ($candidates as $filename) {
			$path = self::get_skin_directory_path($slug) . 'images/' . $filename;

			if (is_readable($path)) {
				return esc_url(self::get_skin_directory_uri($slug) . 'images/' . $filename);
			}
		}

		return '';
	}

	/**
	 * Get a placeholder image URL for the active style.
	 *
	 * Useful during provisioning or in pattern PHP files.
	 *
	 * @param string $filename Image filename (e.g. 'hero-placeholder.jpg').
	 * @return string Full URL or empty string if file doesn't exist.
	 */
	public static function get_placeholder_image(string $filename): string
	{
		$instance = self::get_instance();
		$slug     = $instance->get_active_slug();

		if (empty($slug)) {
			return '';
		}

		$image_path = self::get_skin_directory_path($slug) . 'images/' . $filename;
		$image_url  = self::get_skin_directory_uri($slug) . 'images/' . $filename;

		return file_exists($image_path) ? esc_url($image_url) : '';
	}

	/**
	 * Get all available style slugs.
	 *
	 * @return string[]
	 */
	public static function get_available_styles(): array
	{
		return self::discover_skin_slugs();
	}

	/**
	 * Path to a skin's theme.json variation used for provisioning seeds.
	 */
	public static function get_variation_json_path(string $slug): string
	{
		return self::get_skin_directory_path($slug) . 'variation.json';
	}

	/**
	 * Skin metadata for the Site Editor switcher UI.
	 *
	 * @return array<int, array{slug: string, title: string, previewImageUrl: string, baseColor: string, primaryColor: string, contrastColor: string}>
	 */
	public static function get_editor_style_definitions(): array
	{
		$definitions = [];

		foreach (self::discover_skin_slugs() as $slug) {
			$path = self::get_variation_json_path($slug);

			if (!is_readable($path)) {
				continue;
			}

			$data = json_decode((string) file_get_contents($path), true);

			if (!is_array($data)) {
				continue;
			}

			$palette = $data['settings']['color']['palette'] ?? [];
			$colors  = [];

			foreach ($palette as $entry) {
				if (isset($entry['slug'], $entry['color'])) {
					$colors[$entry['slug']] = $entry['color'];
				}
			}

			$definitions[] = [
				'slug'             => $slug,
				'title'            => (string) ($data['title'] ?? ucfirst($slug)),
				'previewImageUrl'  => self::get_skin_preview_image_url($slug),
				'baseColor'        => $colors['base'] ?? '#ffffff',
				'primaryColor'     => $colors['primary'] ?? '#333333',
				'contrastColor'    => $colors['contrast'] ?? '#000000',
			];
		}

		return $definitions;
	}

	/**
	 * Set the active style variation via theme mod.
	 * This is the entry point for provisioning (WP-CLI / REST API).
	 *
	 * @param string $slug A valid style slug.
	 * @return bool True on success, false if slug is invalid.
	 */
	public static function set_active_style(string $slug): bool
	{
		if (! self::is_registered_skin($slug)) {
			return false;
		}

		set_theme_mod(self::THEME_MOD_KEY, $slug);
		remove_theme_mod(self::LEGACY_THEME_MOD_KEY);

		// Clear the cache so the new value is used immediately.
		$instance               = self::get_instance();
		$instance->active_slug  = null;

		return true;
	}

	/**
	 * Read the current style variation slug from the WordPress wp_global_styles CPT.
	 *
	 * WordPress stores the user's chosen style variation in a published post of
	 * post type 'wp_global_styles', associated with the current theme via the
	 * 'wp_theme' taxonomy. The variation slug lives in post_content JSON under 'slug'.
	 *
	 * @return string Slug or empty string if none found.
	 */
	private function get_slug_from_global_styles(): string
	{
		if (!function_exists('get_stylesheet')) {
			return '';
		}

		$posts = get_posts([
			'post_type'      => 'wp_global_styles',
			'post_status'    => 'publish',
			'numberposts'    => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => [
				[
					'taxonomy' => 'wp_theme',
					'field'    => 'slug',
					'terms'    => get_stylesheet(),
				],
			],
			'no_found_rows'  => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		]);

		if (empty($posts)) {
			return '';
		}

		$data = json_decode($posts[0]->post_content ?? '', true);

		return is_array($data) ? (string) ($data['slug'] ?? '') : '';
	}
}
