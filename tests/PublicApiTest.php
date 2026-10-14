<?php

declare(strict_types=1);

/**
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

namespace KitarisModule\ThemeSkinSwitcher\Tests;

use KitarisModule\ThemeSkinSwitcher\Tests\Support\SkinPackFixture;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * inc/public-api.php helpers (loaded through composer `files` autoload).
 */
class PublicApiTest extends TestCase
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

	public function test_helper_is_defined(): void
	{
		$this->assertTrue(function_exists('theme_skin_switcher_append_pattern_asset_urls'));
	}

	public function test_patterns_without_available_patterns_are_returned_unchanged(): void
	{
		$this->register_pack();

		$patterns = ['enabled' => true, 'pattern_directory' => 'design-skins/fresh/section-patterns'];

		$this->assertSame($patterns, theme_skin_switcher_append_pattern_asset_urls($patterns));
	}

	public function test_appends_urls_and_extensions_for_the_active_pack(): void
	{
		$this->register_pack();

		$result = theme_skin_switcher_append_pattern_asset_urls([
			'pattern_directory'  => '/design-skins/fresh/section-patterns/',
			'available_patterns' => ['waves-big' => 'Waves Big', 'dots' => 'Dots'],
		]);

		// grid is not listed but is discovered from design-skins/_default (SVGs only) and
		// resolved from the primary directory first; dots falls back to _default's PNG.
		$this->assertSame(
			[
				'waves-big' => $this->packUrl . 'design-skins/fresh/section-patterns/waves-big.svg',
				'dots'      => $this->packUrl . 'design-skins/_default/section-patterns/dots.png',
				'grid'      => $this->packUrl . 'design-skins/fresh/section-patterns/grid.svg',
			],
			$result['pattern_urls']
		);
		$this->assertSame(['waves-big' => 'svg', 'dots' => 'png', 'grid' => 'svg'], $result['pattern_extensions']);
	}

	public function test_no_pack_adds_no_urls(): void
	{
		$patterns = ['available_patterns' => ['grid' => 'Grid']];

		$this->assertSame($patterns, theme_skin_switcher_append_pattern_asset_urls($patterns));
	}
}
