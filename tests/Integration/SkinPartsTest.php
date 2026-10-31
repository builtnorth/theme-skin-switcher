<?php
/**
 * SkinParts: a skin's header and footer, saved as this site's template parts.
 *
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

declare(strict_types=1);

namespace KitarisModule\ThemeSkinSwitcher\Tests\Integration;

use FilesystemIterator;
use KitarisModule\ThemeSkinSwitcher\Provisioning\SkinParts;
use KitarisModule\ThemeSkinSwitcher\SkinPackRegistry;
use KitarisModule\ThemeSkinSwitcher\Style\StyleVariationManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;
use RuntimeException;
use WP_Block_Template;
use WP_UnitTestCase;

class SkinPartsTest extends WP_UnitTestCase {

	private const APPLIED_META = '_theme_skin_switcher_applied';

	private string $pack = '';

	private int $purges = 0;

	public function set_up(): void {
		parent::set_up();

		$this->pack = untrailingslashit( get_temp_dir() ) . '/sst-skin-parts-' . wp_generate_password( 8, false );

		$this->skin( 'fresh', [ 'header' => 'one', 'footer' => 'one' ] );
		$this->skin( 'second', [ 'header' => 'two', 'footer' => 'two' ] );
		$this->skin( 'nopair', [] );
		$this->skin( 'half', [ 'header' => 'one', 'footer' => 'gone' ] );
		$this->skin( 'nasty', [ 'header' => '../secret', 'footer' => '../../up' ] );
		$this->skin( 'traversal', [ 'header' => '..', 'footer' => '/etc/passwd' ] );

		$this->pattern( 'header/one', '<!-- wp:paragraph --><p>Header one</p><!-- /wp:paragraph -->' );
		$this->pattern( 'footer/one', '<!-- wp:paragraph --><p>Footer one</p><!-- /wp:paragraph -->' );
		$this->pattern( 'header/two', '<!-- wp:paragraph --><p>Header two</p><!-- /wp:paragraph -->' );
		$this->pattern( 'footer/two', '<!-- wp:paragraph --><p>Footer two</p><!-- /wp:paragraph -->' );
		$this->pattern( 'secret', 'SECRET OUTSIDE THE AREA' );

		$this->reset_skins();
		SkinPackRegistry::get_instance()->register(
			[
				'id'   => 'test',
				'path' => $this->pack,
				'url'  => 'https://example.test/pack/',
			]
		);

		SkinParts::register();
		delete_option( 'theme_mods_' . get_stylesheet() );

		add_action(
			'kitaris_performance_purge_all',
			function (): void {
				++$this->purges;
			}
		);
	}

	public function tear_down(): void {
		$this->reset_skins();

		$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->pack, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $this->pack );

		parent::tear_down();
	}

	private function reset_skins(): void {
		$pack = new ReflectionProperty( SkinPackRegistry::class, 'pack' );
		$pack->setAccessible( true );
		$pack->setValue( SkinPackRegistry::get_instance(), null );

		$slugs = new ReflectionProperty( StyleVariationManager::class, 'discovered_skin_slugs' );
		$slugs->setAccessible( true );
		$slugs->setValue( null, null );

		$active = new ReflectionProperty( StyleVariationManager::class, 'active_slug' );
		$active->setAccessible( true );
		$active->setValue( StyleVariationManager::get_instance(), null );
	}

	/**
	 * @param array<string, string> $meta
	 */
	private function skin( string $slug, array $meta ): void {
		$dir = $this->pack . '/design-skins/' . $slug;
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/variation.json', '{"title":"' . $slug . '"}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- a test fixture.
		if ( [] !== $meta ) {
			file_put_contents( $dir . '/meta.json', (string) wp_json_encode( $meta ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- a test fixture.
		}
	}

	private function pattern( string $path, string $markup ): void {
		wp_mkdir_p( dirname( $this->pack . '/patterns/' . $path ) );
		file_put_contents( $this->pack . '/patterns/' . $path . '.php', $markup ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- a test fixture.
	}

	private function part( string $area ): ?WP_Block_Template {
		$part = get_block_template( get_stylesheet() . '//' . $area, 'wp_template_part' );

		return $part && $part->wp_id ? $part : null;
	}

	private function menu( string $slug, string $status = 'publish' ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'wp_navigation',
				'post_name'   => $slug,
				'post_status' => $status,
			]
		);
	}

	/**
	 * Apply a one-off header with this navigation comment and read its attributes.
	 *
	 * @return array<string, mixed>|null
	 */
	private function navigation_attrs( string $attrs ): ?array {
		$this->pattern( 'header/one', "<!-- wp:navigation {$attrs} /-->" );

		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );

		$blocks = parse_blocks( $this->part( 'header' )->content );

		return $blocks[0]['attrs'];
	}

	// --- routes

	public function test_set_active_style_applies_the_pair(): void {
		$this->assertTrue( StyleVariationManager::set_active_style( 'fresh' ) );

		$this->assertStringContainsString( 'Header one', $this->part( 'header' )->content );
		$this->assertStringContainsString( 'Footer one', $this->part( 'footer' )->content );
	}

	public function test_the_mod_written_directly_applies_the_pair(): void {
		update_option( 'theme_mods_' . get_stylesheet(), [ 'other' => 1 ] );

		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );

		$this->assertStringContainsString( 'Header one', $this->part( 'header' )->content );
	}

	public function test_a_raw_option_update_applies_the_pair(): void {
		update_option( 'theme_mods_' . get_stylesheet(), [ 'other' => 1 ] );

		update_option(
			'theme_mods_' . get_stylesheet(),
			[
				'other'                                  => 1,
				StyleVariationManager::THEME_MOD_KEY => 'fresh',
			]
		);

		$this->assertNotNull( $this->part( 'header' ) );
	}

	public function test_the_first_ever_mod_applies_the_pair(): void {
		$this->assertFalse( get_option( 'theme_mods_' . get_stylesheet() ) );

		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );

		$this->assertStringContainsString( 'Header one', $this->part( 'header' )->content );
	}

	public function test_an_unchanged_skin_applies_nothing(): void {
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );
		wp_delete_post( $this->part( 'header' )->wp_id, true );

		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );
		set_theme_mod( 'something_else', 1 );

		$this->assertNull( $this->part( 'header' ) );
	}

	public function test_a_skin_with_no_pair_applies_nothing(): void {
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'nopair' );

		$this->assertNull( $this->part( 'header' ) );
		$this->assertNull( $this->part( 'footer' ) );
		$this->assertSame( 0, $this->purges );
	}

	public function test_a_skin_that_does_not_exist_applies_nothing(): void {
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'unknown' );

		$this->assertNull( $this->part( 'header' ) );
	}

	public function test_a_skin_with_no_pair_leaves_the_old_pair(): void {
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'nopair' );

		$this->assertStringContainsString( 'Header one', $this->part( 'header' )->content );
	}

	public function test_a_change_purges_the_page_cache_once(): void {
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );

		$this->assertSame( 1, $this->purges );
	}

	public function test_a_missing_file_skips_that_part_only(): void {
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'half' );

		$this->assertNotNull( $this->part( 'header' ) );
		$this->assertNull( $this->part( 'footer' ) );
	}

	/**
	 * @dataProvider bad_name_skins
	 */
	public function test_a_bad_name_cannot_leave_the_folder( string $skin ): void {
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, $skin );

		$this->assertNull( $this->part( 'header' ) );
		$this->assertNull( $this->part( 'footer' ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function bad_name_skins(): array {
		return [
			'dots and slashes' => [ 'nasty' ],
			'bare dots'        => [ 'traversal' ],
		];
	}

	public function test_meta_names_are_cleaned(): void {
		$meta = StyleVariationManager::get_skin_meta( 'nasty' );

		$this->assertSame( 'secret', $meta['header'] );
		$this->assertSame( 'up', $meta['footer'] );
	}

	// --- menus

	/**
	 * @dataProvider menu_positions
	 *
	 * @param array<string, mixed> $without What the attributes are once it is gone.
	 */
	public function test_a_named_menu_becomes_its_ref( string $attrs, array $without ): void {
		$menu = $this->menu( 'primary' );

		$this->assertEquals( $without + [ 'ref' => $menu ], $this->navigation_attrs( $attrs ) );
	}

	/**
	 * @dataProvider removal_positions
	 *
	 * @param array<string, mixed> $without What the attributes are once it is gone.
	 */
	public function test_an_unknown_menu_is_removed_cleanly( string $attrs, array $without ): void {
		$this->assertSame( $without, $this->navigation_attrs( $attrs ) );
	}

	/**
	 * @dataProvider removal_positions
	 *
	 * @param array<string, mixed> $without What the attributes are once it is gone.
	 */
	public function test_a_draft_menu_is_removed_cleanly( string $attrs, array $without ): void {
		$this->menu( 'primary', 'draft' );

		$this->assertSame( $without, $this->navigation_attrs( $attrs ) );
	}

	/**
	 * @return array<string, array{string, array<string, mixed>}>
	 */
	public function menu_positions(): array {
		return [
			'first'  => [ '{"kitarisMenu":"primary","overlayMenu":"never"}', [ 'overlayMenu' => 'never' ] ],
			'middle' => [ '{"a":1,"kitarisMenu":"primary","overlayMenu":"never"}', [ 'a' => 1, 'overlayMenu' => 'never' ] ],
			'last'   => [ '{"overlayMenu":"never","kitarisMenu":"primary"}', [ 'overlayMenu' => 'never' ] ],
			'only'   => [ '{"kitarisMenu":"primary"}', [] ],
		];
	}

	/**
	 * @return array<string, array{string, array<string, mixed>}>
	 */
	public function removal_positions(): array {
		return $this->menu_positions();
	}

	public function test_a_trashed_menu_is_removed_cleanly(): void {
		$menu = $this->menu( 'primary' );
		wp_trash_post( $menu );

		$this->assertSame( [ 'overlayMenu' => 'never' ], $this->navigation_attrs( '{"kitarisMenu":"primary","overlayMenu":"never"}' ) );
	}

	public function test_two_named_menus_in_one_pattern_both_resolve(): void {
		$menu = $this->menu( 'primary' );
		$this->pattern( 'header/one', '<!-- wp:navigation {"kitarisMenu":"primary"} /--><!-- wp:navigation {"a":1,"kitarisMenu":"nope"} /-->' );

		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );

		$blocks = parse_blocks( $this->part( 'header' )->content );

		$this->assertSame( [ 'ref' => $menu ], $blocks[0]['attrs'] );
		$this->assertSame( [ 'a' => 1 ], $blocks[1]['attrs'] );
	}

	public function test_a_pattern_that_throws_leaves_no_output_buffer_open(): void {
		$this->pattern( 'header/one', '<?php throw new \\RuntimeException( "Broken pattern" );' );
		$level = ob_get_level();

		try {
			set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );
			$this->fail( 'The pattern should have thrown.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'Broken pattern', $e->getMessage() );
		}

		$this->assertSame( $level, ob_get_level() );
		$this->assertNull( $this->part( 'header' ) );
	}

	// --- the owner's parts

	public function test_a_part_the_owner_changed_is_never_replaced(): void {
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );
		$part = $this->part( 'header' );
		wp_update_post(
			[
				'ID'           => $part->wp_id,
				'post_content' => '<!-- wp:paragraph --><p>The owner wrote this</p><!-- /wp:paragraph -->',
			]
		);

		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'second' );

		$this->assertStringContainsString( 'The owner wrote this', $this->part( 'header' )->content );
		$this->assertStringContainsString( 'Footer two', $this->part( 'footer' )->content, 'The other part still follows.' );
	}

	public function test_a_part_with_no_marker_that_was_already_there_is_never_replaced(): void {
		$id = self::factory()->post->create(
			[
				'post_type'    => 'wp_template_part',
				'post_name'    => 'header',
				'post_title'   => 'Header',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Already here</p><!-- /wp:paragraph -->',
			]
		);
		wp_set_object_terms( $id, get_stylesheet(), 'wp_theme' );
		wp_set_object_terms( $id, 'header', 'wp_template_part_area' );

		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );

		$this->assertSame( $id, $this->part( 'header' )->wp_id );
		$this->assertStringContainsString( 'Already here', $this->part( 'header' )->content );
		$this->assertSame( '', get_post_meta( $id, self::APPLIED_META, true ) );
	}

	public function test_nothing_changed_means_no_purge(): void {
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );
		foreach ( [ 'header', 'footer' ] as $area ) {
			wp_update_post(
				[
					'ID'           => $this->part( $area )->wp_id,
					'post_content' => 'The owner wrote this',
				]
			);
		}
		$this->purges = 0;

		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'second' );

		$this->assertSame( 0, $this->purges );
	}

	public function test_a_second_skin_overwrites_a_part_still_as_applied(): void {
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );
		$id = $this->part( 'header' )->wp_id;

		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'second' );

		$this->assertSame( $id, $this->part( 'header' )->wp_id, 'The same post, not a second one.' );
		$this->assertStringContainsString( 'Header two', $this->part( 'header' )->content );
	}

	public function test_a_part_kses_changed_is_still_as_applied(): void {
		$this->pattern( 'header/one', '<!-- wp:paragraph --><p onclick="x()">Header one</p><!-- /wp:paragraph -->' );
		add_filter( 'map_meta_cap', static fn ( array $caps, string $cap ): array => 'unfiltered_html' === $cap ? [ 'do_not_allow' ] : $caps, 10, 2 );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		kses_init();

		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'second' );
		kses_remove_filters();

		$this->assertStringContainsString( 'Header two', $this->part( 'header' )->content );
	}

	// --- how core finds it

	public function test_terms_let_core_find_the_part(): void {
		set_theme_mod( StyleVariationManager::THEME_MOD_KEY, 'fresh' );

		$part = $this->part( 'header' );

		$this->assertSame( 'custom', $part->source );
		$this->assertSame( 'header', $part->area );
		$this->assertSame( get_stylesheet(), $part->theme );
		$this->assertSame( 'publish', get_post_status( $part->wp_id ) );
		$this->assertSame( [ get_stylesheet() ], wp_get_object_terms( $part->wp_id, 'wp_theme', [ 'fields' => 'names' ] ) );

		$listed = get_block_templates( [ 'area' => 'footer' ], 'wp_template_part' );

		$this->assertContains( 'footer', array_column( $listed, 'slug' ) );
	}
}
