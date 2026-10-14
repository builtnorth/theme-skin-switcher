<?php

declare(strict_types=1);

/**
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

namespace KitarisModule\ThemeSkinSwitcher\Tests\Support;

use FilesystemIterator;
use KitarisModule\ThemeSkinSwitcher\SkinPackRegistry;
use KitarisModule\ThemeSkinSwitcher\Style\StyleVariationManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use ReflectionProperty;
use WP_Mock;

/**
 * Builds a real skin pack in a temp directory, backs the theme mod API with an
 * in-memory array, mocks the WordPress string helpers the package calls, and resets
 * the package's static caches (singletons, discovered slugs) between tests.
 *
 * Fixture layout (pack root = $this->packPath):
 *
 *   design-skins/
 *     fresh/    variation.json (primary Inter, palette, custom tokens), section-patterns/*.svg,
 *               motion-dividers/zig.svg (legacy divider folder), images/preview.png,
 *               block-patterns/ (no .php files), extra/{Zebra,apple_pie}.svg
 *     arch/     variation.json (no title/palette), section-patterns/ (empty),
 *               background-patterns/old.svg (legacy pattern folder), block-patterns/hero.php
 *     local/    variation.json with a self-hosted file: fontFace
 *     serif/    variation.json, no "primary" family (first-family fallback)
 *     broken/   variation.json that is not valid JSON
 *     nojson/   no variation.json (not a skin)
 *     Bad_Name/ variation.json, invalid directory name (not a skin)
 *     _default/ variation.json + section-patterns/{grid.svg,grid.png,dots.png} (shared fallback, not a skin)
 *   build/design-skins/fresh/style.css
 */
trait SkinPackFixture
{
	protected string $packPath = '';

	protected string $packUrl = 'https://example.test/wp-content/themes/child/';

	/** @var array<string, mixed> */
	protected array $themeMods = [];

	/** @var list<object> */
	protected array $globalStylesPosts = [];

	/** @var array<string, bool> Hook name => has_filter() result. */
	protected array $registeredFilters = [];

	protected function set_up_wordpress(): void
	{
		$this->themeMods         = [];
		$this->globalStylesPosts = [];
		$this->registeredFilters = [];

		WP_Mock::userFunction('sanitize_key')->andReturnUsing(
			static fn ($key): string => preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)) ?? ''
		);
		WP_Mock::userFunction('trailingslashit')->andReturnUsing(
			static fn ($value): string => rtrim((string) $value, '/\\') . '/'
		);
		WP_Mock::userFunction('untrailingslashit')->andReturnUsing(
			static fn ($value): string => rtrim((string) $value, '/\\')
		);
		WP_Mock::userFunction('sanitize_text_field')->andReturnUsing(
			static fn ($value): string => trim(strip_tags((string) $value))
		);
		// Simplified core helper: camelCase / snake_case / spaces → kebab-case.
		WP_Mock::userFunction('_wp_to_kebab_case')->andReturnUsing(
			static fn ($value): string => strtolower(
				(string) preg_replace(['/([a-z0-9])([A-Z])/', '/[\s_]+/'], ['$1-$2', '-'], (string) $value)
			)
		);
		WP_Mock::userFunction('add_query_arg')->andReturnUsing(
			static fn (string $key, string $value, string $url): string => $url . (str_contains($url, '?') ? '&' : '?') . $key . '=' . $value
		);
		WP_Mock::userFunction('has_filter')->andReturnUsing(
			fn (string $hook): bool => $this->registeredFilters[ $hook ] ?? false
		);
		WP_Mock::userFunction('get_theme_mod')->andReturnUsing(
			fn (string $name, $default = false) => array_key_exists($name, $this->themeMods) ? $this->themeMods[ $name ] : $default
		);
		WP_Mock::userFunction('set_theme_mod')->andReturnUsing(
			function (string $name, $value): bool {
				$this->themeMods[ $name ] = $value;

				return true;
			}
		);
		WP_Mock::userFunction('remove_theme_mod')->andReturnUsing(
			function (string $name): void {
				unset($this->themeMods[ $name ]);
			}
		);
		WP_Mock::userFunction('get_posts')->andReturnUsing(fn (): array => $this->globalStylesPosts);
	}

	protected function build_pack(): void
	{
		$this->packPath = sys_get_temp_dir() . '/tss-pack-' . bin2hex(random_bytes(6)) . '/';

		$skins = $this->packPath . 'design-skins/';

		$this->write_json($skins . 'fresh/variation.json', [
			'$schema'  => 'https://schemas.wp.org/trunk/theme.json',
			'version'  => 3,
			'title'    => 'Fresh',
			'slug'     => 'fresh',
			'settings' => [
				'color'      => [
					'palette' => [
						['slug' => 'base', 'color' => '#fafafa'],
						['slug' => 'primary', 'color' => '#0055ff'],
						['name' => 'no slug', 'color' => '#123456'],
					],
				],
				'typography' => [
					'fontFamilies' => [
						['slug' => 'heading', 'fontFamily' => 'Georgia, serif'],
						['slug' => 'primary', 'fontFamily' => '"Inter", sans-serif'],
					],
				],
				'custom'     => [
					'borderRadius' => [
						'tile'   => 'var(--wp--preset--border-radius--medium)',
						'button' => 8,
					],
					'lineHeight'   => 1.5,
					'zero'         => 0,
					'empty'        => '',
					'flag'         => true,
					'nested'       => ['deep' => ['value' => ' <b>bold</b> ']],
				],
			],
			'styles'   => ['color' => ['background' => 'var(--wp--preset--color--base)']],
		]);
		$this->write_file($skins . 'fresh/section-patterns/grid.svg', '<svg/>');
		$this->write_file($skins . 'fresh/section-patterns/waves-big.svg', '<svg/>');
		$this->write_file($skins . 'fresh/motion-dividers/zig.svg', '<svg/>');
		$this->write_file($skins . 'fresh/images/preview.png', 'png');
		mkdir($skins . 'fresh/block-patterns', 0777, true);

		$this->write_json($skins . 'arch/variation.json', [
			'version'  => 3,
			'settings' => [
				'typography' => [
					'fontFamilies' => [['slug' => 'primary', 'fontFamily' => 'Unknown Sans']],
				],
			],
		]);
		mkdir($skins . 'arch/section-patterns', 0777, true);
		$this->write_file($skins . 'arch/background-patterns/old.svg', '<svg/>');
		$this->write_file($skins . 'arch/block-patterns/hero.php', '<?php // fixture');

		$this->write_json($skins . 'local/variation.json', [
			'settings' => [
				'typography' => [
					'fontFamilies' => [
						[
							'slug'       => 'primary',
							'fontFamily' => 'Inter, sans-serif',
							'fontFace'   => [
								['fontFamily' => 'Inter', 'src' => ['file:./assets/fonts/inter.woff2']],
							],
						],
					],
				],
			],
		]);

		$this->write_json($skins . 'serif/variation.json', [
			'title'    => 'Serif',
			'settings' => [
				'typography' => [
					'fontFamilies' => [
						['slug' => 'body', 'fontFamily' => "'Cormorant Garamond', serif"],
						['slug' => 'mono', 'fontFamily' => 'Inter'],
					],
				],
			],
		]);

		$this->write_file($skins . 'broken/variation.json', '{not json');
		mkdir($skins . 'nojson', 0777, true);
		$this->write_json($skins . 'Bad_Name/variation.json', ['title' => 'Bad']);
		$this->write_json($skins . '_default/variation.json', ['title' => 'Default']);
		$this->write_file($skins . '_default/section-patterns/grid.svg', '<svg/>');
		$this->write_file($skins . '_default/section-patterns/dots.png', 'png');
		$this->write_file($skins . '_default/section-patterns/grid.png', 'png');
		$this->write_file($skins . 'fresh/extra/Zebra.svg', '<svg/>');
		$this->write_file($skins . 'fresh/extra/apple_pie.svg', '<svg/>');
		$this->write_file($skins . 'readme.txt', 'not a skin');

		$this->write_file($this->packPath . 'build/design-skins/fresh/style.css', 'body{}');
	}

	protected function register_pack(): void
	{
		SkinPackRegistry::get_instance()->register([
			'id'   => 'child',
			'path' => $this->packPath,
			'url'  => $this->packUrl,
		]);
	}

	protected function remove_pack(): void
	{
		if ($this->packPath === '' || ! is_dir($this->packPath)) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->packPath, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($items as $item) {
			$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
		}

		rmdir($this->packPath);
		$this->packPath = '';
	}

	protected static function reset_static_state(): void
	{
		(new ReflectionProperty(SkinPackRegistry::class, 'instance'))->setValue(null, null);
		(new ReflectionProperty(StyleVariationManager::class, 'instance'))->setValue(null, null);
		(new ReflectionProperty(StyleVariationManager::class, 'discovered_skin_slugs'))->setValue(null, null);
		(new ReflectionProperty(StyleVariationManager::class, 'theme_json_hooks_registered'))->setValue(null, false);
		(new ReflectionProperty(StyleVariationManager::class, 'base_theme_json_for_client'))->setValue(null, null);
	}

	/**
	 * Invoke a private static StyleVariationManager helper.
	 *
	 * @param mixed ...$args Method arguments.
	 * @return mixed
	 */
	protected static function call_private(string $method, ...$args)
	{
		return (new ReflectionMethod(StyleVariationManager::class, $method))->invoke(null, ...$args);
	}

	/**
	 * @param array<string, mixed> $data JSON payload.
	 */
	private function write_json(string $path, array $data): void
	{
		$this->write_file($path, (string) json_encode($data, JSON_UNESCAPED_SLASHES));
	}

	private function write_file(string $path, string $contents): void
	{
		if (! is_dir(dirname($path))) {
			mkdir(dirname($path), 0777, true);
		}

		file_put_contents($path, $contents);
	}
}
