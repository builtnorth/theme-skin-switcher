<?php

declare(strict_types=1);

/**
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

namespace KitarisModule\ThemeSkinSwitcher\Tests;

use KitarisModule\ThemeSkinSwitcher\SkinPackRegistry;
use KitarisModule\ThemeSkinSwitcher\Tests\Support\SkinPackFixture;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * SkinPackRegistry: which theme directory supplies design-skins/.
 */
class SkinPackRegistryTest extends TestCase
{
	use SkinPackFixture;

	private string $parentPath = '';

	public function setUp(): void
	{
		parent::setUp();
		WP_Mock::setUp();
		self::reset_static_state();
		$this->set_up_wordpress();
		$this->build_pack();

		// A second theme directory without design-skins/.
		$this->parentPath = sys_get_temp_dir() . '/tss-parent-' . bin2hex(random_bytes(6));
		mkdir($this->parentPath);
	}

	public function tearDown(): void
	{
		$this->remove_pack();
		if ($this->parentPath !== '' && is_dir($this->parentPath)) {
			if (is_dir($this->parentPath . '/design-skins')) {
				rmdir($this->parentPath . '/design-skins');
			}
			rmdir($this->parentPath);
		}
		self::reset_static_state();
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_no_pack_until_registered(): void
	{
		$registry = SkinPackRegistry::get_instance();

		$this->assertFalse($registry->has_pack());
		$this->assertNull($registry->get_pack());
		$this->assertSame('', SkinPackRegistry::get_pack_path());
		$this->assertSame('', SkinPackRegistry::get_pack_url());
	}

	public function test_register_ignores_directory_without_design_skins(): void
	{
		SkinPackRegistry::get_instance()->register([
			'id'   => 'parent',
			'path' => $this->parentPath,
			'url'  => 'https://example.test/parent',
		]);

		$this->assertFalse(SkinPackRegistry::get_instance()->has_pack());
	}

	public function test_register_normalises_path_url_and_id(): void
	{
		SkinPackRegistry::get_instance()->register([
			'id'   => 'My Child!',
			'path' => rtrim($this->packPath, '/') . '//',
			'url'  => 'https://example.test/child',
		]);

		$this->assertSame(
			[
				'id'   => 'mychild',
				'path' => $this->packPath,
				'url'  => 'https://example.test/child/',
			],
			SkinPackRegistry::get_instance()->get_pack()
		);
		$this->assertSame($this->packPath, SkinPackRegistry::get_pack_path());
		$this->assertSame('https://example.test/child/', SkinPackRegistry::get_pack_url());
	}

	public function test_register_without_id_uses_default(): void
	{
		SkinPackRegistry::get_instance()->register([
			'path' => rtrim($this->packPath, '/'),
			'url'  => $this->packUrl,
		]);

		$this->assertSame('default', SkinPackRegistry::get_instance()->get_pack()['id']);
	}

	public function test_active_theme_pack_prefers_child_theme(): void
	{
		$this->mock_themes('child', $this->packPath, 'parent', $this->parentPath);

		SkinPackRegistry::get_instance()->register_active_theme_pack();

		$pack = SkinPackRegistry::get_instance()->get_pack();
		$this->assertSame('child', $pack['id']);
		$this->assertSame($this->packPath, $pack['path']);
	}

	public function test_child_theme_wins_when_both_themes_have_skins(): void
	{
		mkdir($this->parentPath . '/design-skins');
		$this->mock_themes('child', $this->packPath, 'parent', $this->parentPath);

		SkinPackRegistry::get_instance()->register_active_theme_pack();

		$this->assertSame('child', SkinPackRegistry::get_instance()->get_pack()['id']);
	}

	public function test_active_theme_pack_falls_back_to_parent_theme(): void
	{
		$this->mock_themes('child', $this->parentPath, 'parent', $this->packPath);

		SkinPackRegistry::get_instance()->register_active_theme_pack();

		$pack = SkinPackRegistry::get_instance()->get_pack();
		$this->assertSame('parent', $pack['id']);
		$this->assertSame($this->packPath, $pack['path']);
		$this->assertSame('https://example.test/themes/parent/', $pack['url']);
	}

	public function test_active_theme_pack_registers_nothing_when_no_theme_has_skins(): void
	{
		$this->mock_themes('child', $this->parentPath, 'parent', $this->parentPath);

		SkinPackRegistry::get_instance()->register_active_theme_pack();

		$this->assertFalse(SkinPackRegistry::get_instance()->has_pack());
	}

	public function test_parent_only_install_checks_template_once(): void
	{
		WP_Mock::userFunction('get_stylesheet')->andReturn('solo');
		WP_Mock::userFunction('get_template')->andReturn('solo');
		WP_Mock::userFunction('get_stylesheet_directory')->once()->andReturn($this->packPath);
		WP_Mock::userFunction('get_stylesheet_directory_uri')->once()->andReturn('https://example.test/themes/solo');
		WP_Mock::userFunction('get_template_directory')->never();
		WP_Mock::userFunction('get_template_directory_uri')->never();

		SkinPackRegistry::get_instance()->register_active_theme_pack();

		$this->assertSame('solo', SkinPackRegistry::get_instance()->get_pack()['id']);
	}

	public function test_active_theme_pack_keeps_an_already_registered_pack(): void
	{
		$this->register_pack();

		WP_Mock::userFunction('get_stylesheet')->never();
		WP_Mock::userFunction('get_template')->never();

		SkinPackRegistry::get_instance()->register_active_theme_pack();

		$this->assertSame('child', SkinPackRegistry::get_instance()->get_pack()['id']);
	}

	private function mock_themes(string $stylesheet, string $stylesheetDir, string $template, string $templateDir): void
	{
		WP_Mock::userFunction('get_stylesheet')->andReturn($stylesheet);
		WP_Mock::userFunction('get_template')->andReturn($template);
		WP_Mock::userFunction('get_stylesheet_directory')->andReturn($stylesheetDir);
		WP_Mock::userFunction('get_stylesheet_directory_uri')->andReturn('https://example.test/themes/' . $stylesheet);
		WP_Mock::userFunction('get_template_directory')->andReturn($templateDir);
		WP_Mock::userFunction('get_template_directory_uri')->andReturn('https://example.test/themes/' . $template);
	}
}
