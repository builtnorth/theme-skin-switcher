<?php

declare(strict_types=1);

/**
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

namespace KitarisModule\ThemeSkinSwitcher\Tests\Style;

use KitarisModule\ThemeSkinSwitcher\Style\StyleVariationManager;
use KitarisModule\ThemeSkinSwitcher\Tests\Support\SkinPackFixture;
use WP_Block_Editor_Context;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WP_Theme_JSON_Resolver;

/**
 * StyleVariationManager: active skin resolution (theme mod, legacy migration, global
 * styles fallback) and what the active skin feeds into theme.json, body classes, and
 * the editor.
 */
class ActiveSkinTest extends TestCase
{
	use SkinPackFixture;

	public function setUp(): void
	{
		parent::setUp();
		WP_Mock::setUp();
		self::reset_static_state();
		$this->set_up_wordpress();
		WP_Mock::userFunction('get_stylesheet')->andReturn('child');
		$this->build_pack();
		$this->register_pack();
	}

	public function tearDown(): void
	{
		$this->remove_pack();
		self::reset_static_state();
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_active_slug_from_theme_mod(): void
	{
		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ] = 'fresh';

		$this->assertSame('fresh', StyleVariationManager::get_instance()->get_active_slug());
	}

	public function test_unregistered_theme_mod_slug_resolves_to_empty(): void
	{
		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ] = '_default';

		$this->assertSame('', StyleVariationManager::get_instance()->get_active_slug());
	}

	public function test_legacy_theme_mod_is_migrated(): void
	{
		$this->themeMods[ StyleVariationManager::LEGACY_THEME_MOD_KEY ] = 'Fresh';

		$this->assertSame('fresh', StyleVariationManager::get_instance()->get_active_slug());
		$this->assertSame([StyleVariationManager::THEME_MOD_KEY => 'fresh'], $this->themeMods);
	}

	public function test_current_theme_mod_wins_over_legacy(): void
	{
		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ]        = 'arch';
		$this->themeMods[ StyleVariationManager::LEGACY_THEME_MOD_KEY ] = 'fresh';

		$this->assertSame('arch', StyleVariationManager::get_instance()->get_active_slug());
		$this->assertSame('fresh', $this->themeMods[ StyleVariationManager::LEGACY_THEME_MOD_KEY ]);
	}

	public function test_falls_back_to_global_styles_variation(): void
	{
		WP_Theme_JSON_Resolver::$user_cpt = ['post_content' => '{"slug":"arch","version":3}'];

		$this->assertSame('arch', StyleVariationManager::get_instance()->get_active_slug());
	}

	public function test_global_styles_with_invalid_json_resolves_to_empty(): void
	{
		WP_Theme_JSON_Resolver::$user_cpt = ['post_content' => 'not json'];

		$this->assertSame('', StyleVariationManager::get_instance()->get_active_slug());
	}

	public function test_active_slug_is_cached(): void
	{
		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ] = 'fresh';
		$manager = StyleVariationManager::get_instance();
		$manager->get_active_slug();

		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ] = 'arch';

		$this->assertSame('fresh', $manager->get_active_slug());
	}

	public function test_set_active_style_rejects_unregistered_slug(): void
	{
		$this->assertFalse(StyleVariationManager::set_active_style('missing'));
		$this->assertSame([], $this->themeMods);
	}

	public function test_set_active_style_persists_and_clears_cache(): void
	{
		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ]        = 'fresh';
		$this->themeMods[ StyleVariationManager::LEGACY_THEME_MOD_KEY ] = 'fresh';
		$manager = StyleVariationManager::get_instance();
		$manager->get_active_slug();

		$this->assertTrue(StyleVariationManager::set_active_style('arch'));
		$this->assertSame([StyleVariationManager::THEME_MOD_KEY => 'arch'], $this->themeMods);
		$this->assertSame('arch', $manager->get_active_slug());
	}

	public function test_set_active_style_purges_page_cache_only_on_change(): void
	{
		$fired = 0;
		WP_Mock::onAction('kitaris_performance_purge_all')->with(null)->perform(
			static function () use (&$fired): void {
				++$fired;
			}
		);

		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ] = 'fresh';

		$this->assertTrue(StyleVariationManager::set_active_style('fresh'));
		$this->assertSame(0, $fired, 'saving the active skin again must not purge');

		$this->assertTrue(StyleVariationManager::set_active_style('arch'));
		$this->assertSame(1, $fired);
	}

	public function test_placeholder_image_ignores_path_segments(): void
	{
		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ] = 'fresh';
		WP_Mock::userFunction('sanitize_file_name')->andReturnUsing(static fn ($name): string => (string) $name);
		WP_Mock::userFunction('esc_url')->andReturnUsing(static fn ($url): string => (string) $url);

		$this->assertSame('', StyleVariationManager::get_placeholder_image('../arch/variation.json'));
		$this->assertSame('', StyleVariationManager::get_placeholder_image('../../fresh/variation.json'));
		$this->assertSame(
			$this->packUrl . 'design-skins/fresh/images/preview.png',
			StyleVariationManager::get_placeholder_image('../../images/preview.png')
		);
	}

	public function test_body_classes_follow_active_skin(): void
	{
		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ] = 'fresh';
		$manager = StyleVariationManager::get_instance();

		$this->assertSame(['home', 'theme-skin-fresh'], $manager->add_style_body_class(['home']));
		$this->assertSame('folded theme-skin-fresh', $manager->add_style_admin_body_class('folded'));
		$this->assertSame(
			['x' => 1, 'themeSkinSwitcherActiveSlug' => 'fresh', 'themeSkinSwitcherActiveClass' => 'theme-skin-fresh'],
			$manager->filter_block_editor_settings(['x' => 1], new WP_Block_Editor_Context())
		);
	}

	public function test_body_classes_untouched_without_active_skin(): void
	{
		$manager = StyleVariationManager::get_instance();

		$this->assertSame(['home'], $manager->add_style_body_class(['home']));
		$this->assertSame('folded', $manager->add_style_admin_body_class('folded'));
		$this->assertSame(['x' => 1], $manager->filter_block_editor_settings(['x' => 1], new WP_Block_Editor_Context()));
	}

	public function test_merge_strips_variation_only_keys(): void
	{
		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ] = 'fresh';
		$data = $this->theme_json_data();

		$result = StyleVariationManager::get_instance()->merge_active_skin_theme_json($data);

		$this->assertSame($data, $result);
		$this->assertCount(1, $data->merged);
		$merged = $data->merged[0];
		$this->assertArrayNotHasKey('$schema', $merged);
		$this->assertArrayNotHasKey('title', $merged);
		$this->assertArrayNotHasKey('slug', $merged);
		$this->assertSame(3, $merged['version']);
		$this->assertSame(8, $merged['settings']['custom']['borderRadius']['button']);
		$this->assertArrayHasKey('styles', $merged);
	}

	public function test_merge_skipped_without_active_skin_or_valid_variation(): void
	{
		$data = $this->theme_json_data();
		StyleVariationManager::get_instance()->merge_active_skin_theme_json($data);
		$this->assertSame([], $data->merged, 'no active skin');

		self::reset_static_state();
		$this->register_pack();
		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ] = 'broken';
		StyleVariationManager::get_instance()->merge_active_skin_theme_json($data);
		$this->assertSame([], $data->merged, 'variation.json is not valid JSON');
	}

	public function test_merge_returns_non_theme_json_values_untouched(): void
	{
		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ] = 'fresh';
		$manager = StyleVariationManager::get_instance();

		$this->assertSame('raw', $manager->merge_active_skin_theme_json('raw'));
		$plain = (object) ['a' => 1];
		$this->assertSame($plain, $manager->merge_active_skin_theme_json($plain));
	}

	public function test_flattens_custom_tokens_to_css_variables(): void
	{
		$custom = json_decode((string) file_get_contents($this->packPath . 'design-skins/fresh/variation.json'), true)['settings']['custom'];

		$this->assertSame(
			[
				'--wp--custom--border-radius--tile'   => 'var(--wp--preset--border-radius--medium)',
				'--wp--custom--border-radius--button' => '8',
				'--wp--custom--line-height'           => '1.5',
				'--wp--custom--zero'                  => '0',
				'--wp--custom--nested--deep--value'   => 'bold',
			],
			self::call_private('flatten_theme_json_custom_to_css_vars', $custom)
		);
	}

	public function test_flatten_empty_tree(): void
	{
		$this->assertSame([], self::call_private('flatten_theme_json_custom_to_css_vars', []));
	}

	public function test_custom_properties_apply_legacy_filter_only_when_hooked(): void
	{
		$expected = self::call_private(
			'flatten_theme_json_custom_to_css_vars',
			['borderRadius' => ['tile' => 'var(--wp--preset--border-radius--medium)', 'button' => 8], 'lineHeight' => 1.5, 'zero' => 0, 'nested' => ['deep' => ['value' => 'bold']]]
		);

		WP_Mock::onFilter('compass_waas_skin_client_custom_theme_properties')
			->with($expected, 'fresh', null)
			->reply(['--legacy' => '1']);

		$this->assertSame($expected, self::call_private('get_client_custom_theme_properties', 'fresh'));

		$this->registeredFilters['compass_waas_skin_client_custom_theme_properties'] = true;

		$this->assertSame(['--legacy' => '1'], self::call_private('get_client_custom_theme_properties', 'fresh'));
	}

	public function test_canvas_inline_css_scopes_custom_tokens(): void
	{
		$css = self::call_private('build_editor_canvas_inline_css', 'fresh');

		$this->assertSame(
			':root, body.theme-skin-fresh, .editor-styles-wrapper{'
			. '--wp--custom--border-radius--tile: var(--wp--preset--border-radius--medium);'
			. '--wp--custom--border-radius--button: 8;'
			. '--wp--custom--line-height: 1.5;'
			. '--wp--custom--zero: 0;'
			. '--wp--custom--nested--deep--value: bold}',
			$css
		);
	}

	public function test_canvas_inline_css_rechecks_filtered_properties(): void
	{
		$expected = self::call_private(
			'flatten_theme_json_custom_to_css_vars',
			['borderRadius' => ['tile' => 'var(--wp--preset--border-radius--medium)', 'button' => 8], 'lineHeight' => 1.5, 'zero' => 0, 'nested' => ['deep' => ['value' => 'bold']]]
		);

		WP_Mock::onFilter('theme_skin_switcher_client_custom_theme_properties')
			->with($expected, 'fresh', null)
			->reply([
				'--ok'                   => 'red</style><script>{x}',
				'--Upper'                => 'blue',
				'color'                  => 'green',
				'--x;}body{color:red;--y' => 'pink',
				'--n'                    => 4,
				'--arr'                  => ['nope'],
			]);

		$this->assertSame(
			':root, body.theme-skin-fresh, .editor-styles-wrapper{--ok: red/style>script>x;--n: 4}',
			self::call_private('build_editor_canvas_inline_css', 'fresh')
		);
	}

	public function test_canvas_inline_css_empty_for_skin_without_tokens(): void
	{
		$this->assertSame('', self::call_private('build_editor_canvas_inline_css', 'arch'));
		$this->assertSame('', self::call_private('build_editor_canvas_inline_css', ''));
	}

	/**
	 * Stand-in for WP_Theme_JSON_Data: records update_with() calls.
	 */
	private function theme_json_data(): object
	{
		return new class () {
			/** @var list<array<string, mixed>> */
			public array $merged = [];

			/**
			 * @param array<string, mixed> $data theme.json fragment.
			 */
			public function update_with(array $data): self
			{
				$this->merged[] = $data;

				return $this;
			}
		};
	}
}
