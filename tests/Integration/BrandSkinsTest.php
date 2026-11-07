<?php
/**
 * BrandSkins: which skins a brand (brand.json in the child theme) offers.
 *
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

declare(strict_types=1);

namespace KitarisModule\ThemeSkinSwitcher\Tests\Integration;

use FilesystemIterator;
use KitarisModule\ThemeSkinSwitcher\SkinPackRegistry;
use KitarisModule\ThemeSkinSwitcher\Style\BrandSkins;
use KitarisModule\ThemeSkinSwitcher\Style\StyleVariationManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;
use WP_UnitTestCase;

class BrandSkinsTest extends WP_UnitTestCase {

	/** Library order (discovery sorts by name). */
	private const LIBRARY = [ 'arch', 'blueprint', 'fresh', 'meadow', 'stone' ];

	private string $root = '';

	public function set_up(): void {
		parent::set_up();

		$this->root = untrailingslashit( get_temp_dir() ) . '/sst-brand-' . wp_generate_password( 8, false );

		$this->skin( 'arch', [ 'collections' => [ 'rugged' ] ] );
		$this->skin( 'blueprint', [ 'collections' => [ 'clean', 'rugged' ] ] );
		$this->skin( 'fresh', [ 'collections' => [ 'clean' ] ] );
		$this->skin( 'meadow', [ 'collections' => [ 42, ' Soft! ' ] ] );
		$this->skin( 'stone', [] );
		wp_mkdir_p( $this->root . '/theme' );

		add_filter( 'stylesheet_directory', [ $this, 'theme_dir' ] );
		delete_option( 'theme_mods_' . get_stylesheet() );
		$this->reset();
	}

	public function tear_down(): void {
		remove_filter( 'stylesheet_directory', [ $this, 'theme_dir' ] );
		$this->reset();

		$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $this->root );

		parent::tear_down();
	}

	public function theme_dir(): string {
		return $this->root . '/theme';
	}

	public function test_without_a_usable_brand_file_every_skin_is_offered(): void {
		$this->assertSame( self::LIBRARY, StyleVariationManager::get_available_styles(), 'no brand.json' );

		// Core's wp_json_file_decode() raises an E_USER_NOTICE on a syntax
		// error; record it so the outcome (not the notice) is what is checked.
		$warnings = 0;
		set_error_handler(
			static function () use ( &$warnings ): bool {
				++$warnings;
				return true;
			},
			E_USER_NOTICE | E_USER_WARNING
		);

		try {
			foreach ( [ '{not json', '[]', '{"skins":"rugged"}', '{"other":{}}' ] as $raw ) {
				$this->brand_raw( $raw );
				$this->assertSame( self::LIBRARY, StyleVariationManager::get_available_styles(), $raw );
				$this->assertSame( '', StyleVariationManager::get_instance()->get_active_slug(), $raw );
			}
		} finally {
			restore_error_handler();
		}

		$this->assertSame( 1, $warnings, 'only the malformed file warns' );
	}

	public function test_offered_is_collections_plus_include_minus_exclude(): void {
		$this->brand(
			[
				'collections' => [ 'rugged', 'soft' ],
				'include'     => [ 'stone', 'fresh' ],
				'exclude'     => [ 'arch', 'fresh' ],
			]
		);

		// arch: collection but excluded; fresh: included but excluded (exclude wins);
		// meadow: its sanitized " Soft! " collection; stone: included.
		$this->assertSame( [ 'blueprint', 'meadow', 'stone' ], StyleVariationManager::get_available_styles() );
	}

	public function test_active_skin_outside_the_brand_stays_offered_registered_and_applied(): void {
		$this->brand( [ 'collections' => [ 'clean' ], 'exclude' => [ 'arch' ], 'default' => 'fresh' ] );
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'arch' );

		$this->assertSame( 'arch', StyleVariationManager::get_instance()->get_active_slug() );
		$this->assertSame( [ 'arch', 'blueprint', 'fresh' ], StyleVariationManager::get_available_styles() );
		$this->assertSame( self::LIBRARY, StyleVariationManager::discover_skin_slugs() );
		$this->assertTrue( StyleVariationManager::is_registered_skin( 'arch' ) );
	}

	public function test_brand_default_applies_only_when_nothing_is_set_and_it_is_registered(): void {
		$this->brand( [ 'collections' => [ 'clean' ], 'default' => 'Blueprint' ] );
		$this->assertSame( 'blueprint', StyleVariationManager::get_instance()->get_active_slug(), 'nothing set' );

		$this->reset();
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'stone' );
		$this->assertSame( 'stone', StyleVariationManager::get_instance()->get_active_slug(), 'theme mod wins' );

		delete_option( 'theme_mods_' . get_stylesheet() );
		$this->brand( [ 'default' => 'missing' ] );
		$this->assertSame( '', BrandSkins::default_slug(), 'unregistered default' );
		$this->assertSame( '', StyleVariationManager::get_instance()->get_active_slug(), 'unregistered default' );
	}

	public function test_untrusted_brand_values_are_dropped_not_fatal(): void {
		$this->brand(
			[
				'collections' => 'rugged',
				'include'     => [ 42, [ 'arch' ], null, ' Fresh<script> ' ],
				'exclude'     => [ 'x' => [ 'y' ] ],
				'default'     => [ 'arch' ],
			]
		);

		$this->assertSame(
			[ 'collections' => [], 'include' => [ 'freshscript' ], 'exclude' => [], 'default' => '' ],
			BrandSkins::config()
		);
		$this->assertSame( [], StyleVariationManager::get_available_styles() );
		$this->assertSame( '', BrandSkins::default_slug() );
	}

	/**
	 * @param array<string, mixed> $skins The brand file's "skins" object.
	 */
	private function brand( array $skins ): void {
		$this->brand_raw( (string) wp_json_encode( [ 'skins' => $skins ] ) );
	}

	private function brand_raw( string $raw ): void {
		file_put_contents( $this->root . '/theme/' . BrandSkins::FILE, $raw ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- a test fixture.
		$this->reset();
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function skin( string $slug, array $meta ): void {
		$dir = $this->root . '/pack/design-skins/' . $slug;
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/variation.json', '{"title":"' . $slug . '"}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- a test fixture.
		if ( [] !== $meta ) {
			file_put_contents( $dir . '/meta.json', (string) wp_json_encode( $meta ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- a test fixture.
		}
	}

	/** Forget every per-request cache and re-register the pack. */
	private function reset(): void {
		BrandSkins::reset();

		$pack = new ReflectionProperty( SkinPackRegistry::class, 'pack' );
		$pack->setAccessible( true );
		$pack->setValue( SkinPackRegistry::get_instance(), null );

		$slugs = new ReflectionProperty( StyleVariationManager::class, 'discovered_skin_slugs' );
		$slugs->setAccessible( true );
		$slugs->setValue( null, null );

		$active = new ReflectionProperty( StyleVariationManager::class, 'active_slug' );
		$active->setAccessible( true );
		$active->setValue( StyleVariationManager::get_instance(), null );

		if ( '' !== $this->root ) {
			SkinPackRegistry::get_instance()->register( [ 'id' => 'test', 'path' => $this->root . '/pack', 'url' => 'https://example.test/pack/' ] );
		}
	}
}
