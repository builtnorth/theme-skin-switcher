<?php

declare(strict_types=1);

/**
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

namespace KitarisModule\ThemeSkinSwitcher\Tests\Style;

use KitarisModule\ThemeSkinSwitcher\Style\StyleVariationManager;
use KitarisModule\ThemeSkinSwitcher\Tests\Support\SkinPackFixture;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * StyleVariationManager: section pattern / divider directory resolution and the
 * editor experience overrides built from them.
 */
class PatternAssetsTest extends TestCase
{
	use SkinPackFixture;

	public function setUp(): void
	{
		parent::setUp();
		WP_Mock::setUp();
		self::reset_static_state();
		$this->set_up_wordpress();
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

	public function test_resolves_canonical_section_patterns_directory(): void
	{
		$this->assertSame(
			'design-skins/fresh/section-patterns',
			StyleVariationManager::resolve_skin_subdirectory_relative('fresh', StyleVariationManager::SKIN_SECTION_PATTERNS_DIR)
		);
	}

	public function test_empty_canonical_directory_falls_through_to_legacy_name(): void
	{
		// arch/section-patterns/ exists but holds no SVGs; background-patterns/ does.
		$this->assertSame(
			'design-skins/arch/background-patterns',
			StyleVariationManager::resolve_skin_subdirectory_relative('arch', StyleVariationManager::SKIN_SECTION_PATTERNS_DIR)
		);
	}

	public function test_resolves_legacy_divider_directory(): void
	{
		$this->assertSame(
			'design-skins/fresh/motion-dividers',
			StyleVariationManager::resolve_skin_subdirectory_relative('fresh', StyleVariationManager::SKIN_SECTION_DIVIDERS_DIR)
		);
		$this->assertNull(
			StyleVariationManager::resolve_skin_subdirectory_relative('arch', StyleVariationManager::SKIN_SECTION_DIVIDERS_DIR)
		);
	}

	public function test_block_patterns_directory_needs_php_files(): void
	{
		$this->assertSame(
			'design-skins/arch/block-patterns',
			StyleVariationManager::resolve_skin_subdirectory_relative('arch', StyleVariationManager::SKIN_BLOCK_PATTERNS_DIR)
		);
		// fresh/block-patterns/ exists but is empty.
		$this->assertNull(
			StyleVariationManager::resolve_skin_subdirectory_relative('fresh', StyleVariationManager::SKIN_BLOCK_PATTERNS_DIR)
		);
	}

	public function test_labels_svg_assets_sorted_by_slug(): void
	{
		$this->assertSame(
			['grid' => 'Grid', 'waves-big' => 'Waves Big'],
			StyleVariationManager::discover_labeled_assets_from_relative_directory('/design-skins/fresh/section-patterns')
		);
		// Only SVGs are labelled; dots.png is ignored.
		$this->assertSame(
			['grid' => 'Grid'],
			StyleVariationManager::discover_labeled_assets_from_relative_directory('design-skins/_default/section-patterns')
		);
		$this->assertSame([], StyleVariationManager::discover_labeled_assets_from_relative_directory('design-skins/nope'));
	}

	public function test_labels_are_sorted_after_slug_sanitising(): void
	{
		// glob() lists "Zebra.svg" before "apple_pie.svg" (uppercase sorts first); the
		// sanitised slug "zebra" must still sort after "apple_pie".
		$this->assertSame(
			['apple_pie' => 'Apple Pie', 'zebra' => 'Zebra'],
			StyleVariationManager::discover_labeled_assets_from_relative_directory('design-skins/fresh/extra')
		);
	}

	public function test_pattern_asset_prefers_primary_directory(): void
	{
		$this->assertSame(
			['url' => $this->packUrl . 'design-skins/fresh/section-patterns/grid.svg', 'ext' => 'svg'],
			StyleVariationManager::resolve_pattern_asset_for_slug('grid', '/design-skins/fresh/section-patterns/')
		);
	}

	public function test_pattern_asset_falls_back_to_default_directory(): void
	{
		$this->assertSame(
			['url' => $this->packUrl . 'design-skins/_default/section-patterns/dots.png', 'ext' => 'png'],
			StyleVariationManager::resolve_pattern_asset_for_slug('dots', 'design-skins/fresh/section-patterns')
		);
		// _default holds grid.svg and grid.png: SVG is tried first.
		$this->assertSame(
			['url' => $this->packUrl . 'design-skins/_default/section-patterns/grid.svg', 'ext' => 'svg'],
			StyleVariationManager::resolve_pattern_asset_for_slug('grid')
		);
		$this->assertSame(
			$this->packUrl . 'design-skins/_default/section-patterns/grid.svg',
			StyleVariationManager::resolve_pattern_asset_url_for_slug('grid')
		);
	}

	public function test_pattern_asset_slug_is_sanitised(): void
	{
		$this->assertSame(
			$this->packUrl . 'design-skins/_default/section-patterns/dots.png',
			StyleVariationManager::resolve_pattern_asset_url_for_slug('../DOTS')
		);
	}

	public function test_pattern_asset_empty_cases(): void
	{
		$empty = ['url' => '', 'ext' => ''];

		$this->assertSame($empty, StyleVariationManager::resolve_pattern_asset_for_slug('missing'));
		$this->assertSame($empty, StyleVariationManager::resolve_pattern_asset_for_slug('!!!'));
	}

	public function test_append_pattern_asset_urls_keeps_existing_entries(): void
	{
		$result = StyleVariationManager::append_pattern_asset_urls([
			'pattern_directory'  => 'design-skins/fresh/section-patterns',
			'available_patterns' => ['grid' => 'Grid', 'gone' => 'Gone'],
			'pattern_urls'       => ['custom' => 'https://cdn.test/custom.svg'],
			'pattern_extensions' => ['custom' => 'svg'],
		]);

		$this->assertSame(
			[
				'custom' => 'https://cdn.test/custom.svg',
				'grid'   => $this->packUrl . 'design-skins/fresh/section-patterns/grid.svg',
			],
			$result['pattern_urls']
		);
		$this->assertSame(['custom' => 'svg', 'grid' => 'svg'], $result['pattern_extensions']);
		$this->assertArrayNotHasKey('gone', $result['pattern_urls']);
	}

	public function test_editor_experience_unchanged_for_unregistered_slug(): void
	{
		$base = ['patterns' => ['enabled' => false]];

		$this->assertSame($base, StyleVariationManager::apply_editor_experience_for_slug($base, ''));
		$this->assertSame($base, StyleVariationManager::apply_editor_experience_for_slug($base, '_default'));
	}

	public function test_editor_experience_overrides_for_skin(): void
	{
		$result = StyleVariationManager::apply_editor_experience_for_slug(
			['patterns' => ['label' => 'Backgrounds', 'enabled' => false], 'other' => 1],
			'fresh'
		);

		$this->assertSame(1, $result['other']);
		$this->assertSame('Backgrounds', $result['patterns']['label']);
		$this->assertTrue($result['patterns']['enabled']);
		$this->assertSame('design-skins/fresh/section-patterns', $result['patterns']['pattern_directory']);
		$this->assertSame(['grid' => 'Grid', 'waves-big' => 'Waves Big'], $result['patterns']['available_patterns']);
		$this->assertSame(
			$this->packUrl . 'design-skins/fresh/section-patterns/waves-big.svg',
			$result['patterns']['pattern_urls']['waves-big']
		);
		$this->assertSame(
			['enabled' => true, 'divider_directory' => 'design-skins/fresh/motion-dividers'],
			$result['section_divider']
		);
		$this->assertSame(
			['slug' => 'fresh', 'assets_url' => $this->packUrl . 'design-skins/fresh/'],
			$result['active_style']
		);
	}

	public function test_editor_experience_without_skin_dividers_leaves_divider_config(): void
	{
		$result = StyleVariationManager::apply_editor_experience_for_slug(
			['section_divider' => ['enabled' => false]],
			'local'
		);

		$this->assertSame(['enabled' => false], $result['section_divider']);
		$this->assertArrayNotHasKey('patterns', $result);
		$this->assertSame('local', $result['active_style']['slug']);
	}

	public function test_section_pattern_base_directories_prepend_pack(): void
	{
		$result = StyleVariationManager::get_instance()->filter_section_pattern_base_directories(['/themes/child'], 'patterns');

		$this->assertSame([rtrim($this->packPath, '/'), '/themes/child'], $result);
	}
}
