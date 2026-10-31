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
 * StyleVariationManager: skin discovery, path helpers, and per-skin metadata.
 */
class SkinDiscoveryTest extends TestCase
{
	use SkinPackFixture;

	public function setUp(): void
	{
		parent::setUp();
		WP_Mock::setUp();
		self::reset_static_state();
		$this->set_up_wordpress();
		$this->build_pack();
	}

	public function tearDown(): void
	{
		$this->remove_pack();
		self::reset_static_state();
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_body_class_for_slug(): void
	{
		$this->assertSame('theme-skin-fresh', StyleVariationManager::get_body_class_for_slug('Fresh'));
		$this->assertSame('theme-skin-dark-mode', StyleVariationManager::get_body_class_for_slug('dark-mode'));
		$this->assertSame('', StyleVariationManager::get_body_class_for_slug(''));
		$this->assertSame('', StyleVariationManager::get_body_class_for_slug('!!!'));
	}

	public function test_relative_path_helpers(): void
	{
		$this->assertSame('design-skins/fresh', StyleVariationManager::get_skin_relative_path('fresh'));
		$this->assertSame('design-skins/fresh/variation.json', StyleVariationManager::get_skin_relative_path('fresh', '/variation.json'));
		$this->assertSame('build/design-skins/fresh/style.css', StyleVariationManager::get_skin_compiled_style_relative_path('fresh'));
	}

	public function test_paths_are_empty_without_a_pack(): void
	{
		$this->assertSame('', StyleVariationManager::get_skins_base_path());
		$this->assertSame([], StyleVariationManager::discover_skin_slugs());
		$this->assertFalse(StyleVariationManager::is_registered_skin('fresh'));
	}

	public function test_path_and_uri_helpers_use_the_registered_pack(): void
	{
		$this->register_pack();

		$this->assertSame($this->packPath . 'design-skins/', StyleVariationManager::get_skins_base_path());
		$this->assertSame($this->packPath . 'design-skins/fresh/', StyleVariationManager::get_skin_directory_path('fresh'));
		$this->assertSame($this->packPath . 'design-skins/fresh/variation.json', StyleVariationManager::get_variation_json_path('fresh'));
		$this->assertSame($this->packUrl . 'design-skins/fresh/', StyleVariationManager::get_skin_directory_uri('fresh'));
	}

	public function test_discovers_only_valid_skin_directories_with_variation_json(): void
	{
		$this->register_pack();

		// Excluded: _default (underscore), Bad_Name (invalid name), nojson (no variation.json), readme.txt (file).
		$this->assertSame(['arch', 'broken', 'fresh', 'local', 'serif'], StyleVariationManager::discover_skin_slugs());
		$this->assertSame(['arch', 'broken', 'fresh', 'local', 'serif'], StyleVariationManager::get_available_styles());
	}

	public function test_discovery_is_cached_for_the_request(): void
	{
		$this->register_pack();
		StyleVariationManager::discover_skin_slugs();

		mkdir($this->packPath . 'design-skins/later');
		file_put_contents($this->packPath . 'design-skins/later/variation.json', '{}');

		$this->assertNotContains('later', StyleVariationManager::discover_skin_slugs());
	}

	/**
	 * @dataProvider unregisteredSlugs
	 */
	public function test_is_registered_skin_rejects(string $slug): void
	{
		$this->register_pack();

		$this->assertFalse(StyleVariationManager::is_registered_skin($slug));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function unregisteredSlugs(): array
	{
		return [
			'empty'             => [''],
			'shared default'    => ['_default'],
			'invalid name'      => ['Bad_Name'],
			'no variation.json' => ['nojson'],
			'path traversal'    => ['../fresh'],
			'uppercase'         => ['Fresh'],
			'unknown'           => ['missing'],
		];
	}

	public function test_is_registered_skin_accepts_discovered_slug(): void
	{
		$this->register_pack();

		$this->assertTrue(StyleVariationManager::is_registered_skin('fresh'));
	}

	public function test_style_css_url_only_for_registered_skin_with_compiled_css(): void
	{
		$this->register_pack();

		$this->assertSame(
			$this->packUrl . 'build/design-skins/fresh/style.css',
			StyleVariationManager::get_active_skin_style_css_url_for_slug('fresh')
		);
		$this->assertSame('', StyleVariationManager::get_active_skin_style_css_url_for_slug('arch'));
		$this->assertSame('', StyleVariationManager::get_active_skin_style_css_url_for_slug('missing'));
		$this->assertSame('', StyleVariationManager::get_active_skin_style_css_url_for_slug(''));
	}

	public function test_style_css_version_is_compiled_file_mtime(): void
	{
		$this->register_pack();
		touch($this->packPath . 'build/design-skins/fresh/style.css', 1700000000);
		clearstatcache();

		$this->assertSame('1700000000', StyleVariationManager::get_skin_style_css_version_for_slug('fresh'));
		$this->assertSame('', StyleVariationManager::get_skin_style_css_version_for_slug('arch'));
		$this->assertSame('', StyleVariationManager::get_skin_style_css_version_for_slug(''));
	}

	public function test_preview_image_url(): void
	{
		$this->register_pack();

		$this->assertSame(
			$this->packUrl . 'design-skins/fresh/images/preview.png',
			StyleVariationManager::get_skin_preview_image_url('fresh')
		);
		$this->assertSame('', StyleVariationManager::get_skin_preview_image_url('arch'));
		$this->assertSame('', StyleVariationManager::get_skin_preview_image_url('_default'));
	}

	public function test_editor_style_definitions(): void
	{
		$this->register_pack();

		$definitions = StyleVariationManager::get_editor_style_definitions();
		$bySlug      = array_column($definitions, null, 'slug');

		// broken/variation.json is not valid JSON and is skipped.
		$this->assertSame(['arch', 'fresh', 'local', 'serif'], array_keys($bySlug));

		$this->assertSame(
			[
				'slug'            => 'fresh',
				'title'           => 'Fresh',
				'description'     => '',
				'tags'            => [],
				'previewImageUrl' => $this->packUrl . 'design-skins/fresh/images/preview.png',
				'baseColor'       => '#fafafa',
				'primaryColor'    => '#0055ff',
				'contrastColor'   => '#000000',
			],
			$bySlug['fresh']
		);

		// No title or palette: ucfirst(slug) and the default colours.
		$this->assertSame('Arch', $bySlug['arch']['title']);
		$this->assertSame('', $bySlug['arch']['previewImageUrl']);
		$this->assertSame('#ffffff', $bySlug['arch']['baseColor']);
		$this->assertSame('#333333', $bySlug['arch']['primaryColor']);
	}

	public function test_google_font_query_override_wins(): void
	{
		$this->register_pack();

		// arch's variation.json names "Unknown Sans", but the per-slug override applies.
		$this->assertSame(
			'Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600',
			StyleVariationManager::get_google_font_query_for_slug('arch')
		);
	}

	public function test_google_font_query_uses_primary_family(): void
	{
		$this->register_pack();

		// "heading" (Georgia) comes first; the "primary" slug is preferred and its quotes stripped.
		$this->assertSame('Inter:wght@400;500;600;700', StyleVariationManager::get_google_font_query_for_slug('fresh'));
	}

	public function test_google_font_query_falls_back_to_first_family(): void
	{
		$this->register_pack();

		$this->assertSame(
			'Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600',
			StyleVariationManager::get_google_font_query_for_slug('serif')
		);
	}

	public function test_google_font_query_null_cases(): void
	{
		$this->register_pack();

		$this->assertNull(StyleVariationManager::get_google_font_query_for_slug('local'), 'self-hosted file: fontFace');
		$this->assertNull(StyleVariationManager::get_google_font_query_for_slug('broken'), 'invalid JSON');
		$this->assertNull(StyleVariationManager::get_google_font_query_for_slug('missing'), 'no variation.json');
		$this->assertNull(StyleVariationManager::get_google_font_query_for_slug('nojson'), 'directory without variation.json');
	}

	public function test_style_font_queries_map_only_skins_with_a_query(): void
	{
		$this->register_pack();

		$this->assertSame(
			[
				'arch'  => 'Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600',
				'fresh' => 'Inter:wght@400;500;600;700',
				'serif' => 'Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600',
			],
			StyleVariationManager::get_style_font_queries()
		);
	}
}
