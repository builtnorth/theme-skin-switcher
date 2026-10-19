<?php

declare(strict_types=1);

/**
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

namespace KitarisModule\ThemeSkinSwitcher\Tests\Style;

use Kitaris\Blocks\BlockAssets;
use KitarisModule\ThemeSkinSwitcher\Style\SkinBlockAssets;
use KitarisModule\ThemeSkinSwitcher\Style\StyleVariationManager;
use KitarisModule\ThemeSkinSwitcher\Tests\Support\SkinPackFixture;
use ReflectionProperty;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * SkinBlockAssets: only the active skin's per-block files reach Kitaris
 * BlockAssets, after the themes, and the editor gets matching handles.
 */
class SkinBlockAssetsTest extends TestCase
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

		$build = $this->packPath . 'build/design-skins/fresh/block-assets';
		mkdir($build . '/core', 0777, true);
		file_put_contents($build . '/core/button.css', '.x{}');
		file_put_contents(
			$build . '/' . BlockAssets::MANIFEST_FILE,
			'<?php return ' . var_export([
				'core/button' => ['style' => 'core/button.css', 'version' => 'abc'],
				'core/details' => ['script' => 'core/details.js', 'version' => 'def'],
			], true) . ';'
		);
	}

	public function tearDown(): void
	{
		$this->remove_pack();
		self::reset_static_state();
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_active_skin_directory_is_added_after_the_themes(): void
	{
		$this->themeMods[ StyleVariationManager::THEME_MOD_KEY ] = 'fresh';

		$block_assets = new BlockAssets();
		SkinBlockAssets::register_active_skin($block_assets);

		$directories = (new ReflectionProperty(BlockAssets::class, 'directories'))->getValue($block_assets);

		$this->assertSame(
			[
				$this->packPath . 'build/design-skins/fresh/block-assets' => [
					'url'      => $this->packUrl . 'build/design-skins/fresh/block-assets',
					'priority' => SkinBlockAssets::PRIORITY,
					'source'   => 'skin-fresh',
				],
			],
			$directories
		);
		$this->assertGreaterThan(BlockAssets::PRIORITY_CHILD_THEME, SkinBlockAssets::PRIORITY);
	}

	public function test_no_directory_without_an_active_skin(): void
	{
		$block_assets = new BlockAssets();
		SkinBlockAssets::register_active_skin($block_assets);

		$this->assertSame([], (new ReflectionProperty(BlockAssets::class, 'directories'))->getValue($block_assets));
	}

	public function test_client_styles_list_only_stylesheets_with_kitaris_handles(): void
	{
		$this->assertSame(
			[
				[
					'id'   => 'kitaris-block-assets-skin-fresh-core-button',
					'href' => $this->packUrl . 'build/design-skins/fresh/block-assets/core/button.css?ver=abc',
				],
			],
			SkinBlockAssets::get_client_styles('fresh')
		);
		$this->assertSame([], SkinBlockAssets::get_client_styles('arch'));
		$this->assertSame([], SkinBlockAssets::get_client_styles('_default'));
	}
}
