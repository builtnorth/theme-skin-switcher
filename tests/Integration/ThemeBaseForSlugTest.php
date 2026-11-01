<?php
/**
 * The theme's styles with a skin merged in: StyleVariationManager::get_theme_base_for_slug()
 * and GET /theme-skin-switcher/v1/style/base, which the editor rebuilds its global
 * stylesheet from after a skin switch.
 *
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

declare(strict_types=1);

namespace KitarisModule\ThemeSkinSwitcher\Tests\Integration;

use FilesystemIterator;
use KitarisModule\ThemeSkinSwitcher\Provisioning\SkinParts;
use KitarisModule\ThemeSkinSwitcher\RestAPI\StyleRoute;
use KitarisModule\ThemeSkinSwitcher\SkinPackRegistry;
use KitarisModule\ThemeSkinSwitcher\Style\StyleVariationManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;
use RuntimeException;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Theme_JSON_Resolver;
use WP_UnitTestCase;

final class ThemeBaseForSlugTest extends WP_UnitTestCase {

	private const ROUTE = '/theme-skin-switcher/v1/style/base';

	private string $pack = '';

	private int $purges = 0;

	public function set_up(): void {
		parent::set_up();

		$this->pack = untrailingslashit( get_temp_dir() ) . '/sst-theme-base-' . wp_generate_password( 8, false );

		$this->skin( 'fresh', 'fresh-token', '#010101' );
		$this->skin( 'second', 'second-token', '#020202' );
		$this->skin( '_default', 'shared-token', '#030303' );

		$this->reset_skins();
		SkinPackRegistry::get_instance()->register(
			[
				'id'   => 'test',
				'path' => $this->pack,
				'url'  => 'https://example.test/pack/',
			]
		);

		delete_option( 'theme_mods_' . get_stylesheet() );

		StyleVariationManager::get_instance()->register_theme_json_merge_hooks();
		SkinParts::register();
		WP_Theme_JSON_Resolver::clean_cached_data();

		add_action(
			'kitaris_performance_purge_all',
			function (): void {
				++$this->purges;
			}
		);

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		add_action( 'rest_api_init', [ StyleRoute::class, 'register' ] );
		do_action( 'rest_api_init', $wp_rest_server );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		$this->reset_skins();
		WP_Theme_JSON_Resolver::clean_cached_data();

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

		// Core restores its hooks after each test, so the merge hook has to go back on.
		$hooks = new ReflectionProperty( StyleVariationManager::class, 'theme_json_hooks_registered' );
		$hooks->setAccessible( true );
		$hooks->setValue( null, false );
	}

	private function skin( string $slug, string $token, string $background ): void {
		$dir = $this->pack . '/design-skins/' . $slug;
		wp_mkdir_p( $dir );
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions -- a test fixture.
			$dir . '/variation.json',
			(string) wp_json_encode(
				[
					'$schema'  => 'https://schemas.wp.org/trunk/theme.json',
					'version'  => 3,
					'title'    => $slug,
					'settings' => [ 'custom' => [ 'skinToken' => $token ] ],
					'styles'   => [ 'color' => [ 'background' => $background ] ],
				]
			)
		);
	}

	/**
	 * Make a skin the saved, active one.
	 */
	private function activate( string $slug ): void {
		$this->assertTrue( StyleVariationManager::set_active_style( $slug ) );
		WP_Theme_JSON_Resolver::clean_cached_data();
		$this->purges = 0;
	}

	/** @return array<string, mixed> The resolver's merged data now. */
	private function resolved(): array {
		return WP_Theme_JSON_Resolver::get_merged_data( 'theme' )->get_raw_data();
	}

	/**
	 * The resolver's data once it is cached: core only serves its cache from
	 * the third read after a clean, having seen the registered blocks on the second.
	 *
	 * @return array<string, mixed>
	 */
	private function cached(): array {
		$this->resolved();
		$this->resolved();

		return $this->resolved();
	}

	private function saved_mods(): mixed {
		return get_option( 'theme_mods_' . get_stylesheet() );
	}

	private function request( string $method = 'GET', ?string $slug = 'fresh' ): WP_REST_Response {
		$request = new WP_REST_Request( $method, self::ROUTE );
		if ( null !== $slug ) {
			$request->set_query_params( [ 'slug' => $slug ] );
		}

		return rest_get_server()->dispatch( $request );
	}

	private function as_role( string $role ): int {
		$id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $id );

		return $id;
	}

	// --- get_theme_base_for_slug()

	public function test_returns_settings_and_styles_with_the_skin_merged(): void {
		$base = StyleVariationManager::get_theme_base_for_slug( 'fresh' );

		$this->assertSame( [ 'settings', 'styles' ], array_keys( $base ) );
		$this->assertSame( 'fresh-token', $base['settings']['custom']['skinToken'] );
		$this->assertSame( '#010101', $base['styles']['color']['background'] );
	}

	public function test_keeps_what_the_skin_leaves_alone(): void {
		$without = $this->resolved();

		$base = StyleVariationManager::get_theme_base_for_slug( 'fresh' );

		$this->assertSame( $without['settings']['appearanceTools'] ?? null, $base['settings']['appearanceTools'] ?? null );
		$this->assertSame( $without['settings']['color']['palette'] ?? null, $base['settings']['color']['palette'] ?? null );
		$this->assertArrayNotHasKey( 'skinToken', $without['settings']['custom'] ?? [] );
	}

	public function test_each_skin_gets_its_own_data(): void {
		$first  = StyleVariationManager::get_theme_base_for_slug( 'fresh' );
		$second = StyleVariationManager::get_theme_base_for_slug( 'second' );

		$this->assertSame( 'fresh-token', $first['settings']['custom']['skinToken'] );
		$this->assertSame( 'second-token', $second['settings']['custom']['skinToken'] );
		$this->assertSame( '#020202', $second['styles']['color']['background'] );
	}

	/**
	 * @dataProvider unregistered_skins
	 */
	public function test_an_unregistered_skin_returns_null( string $slug ): void {
		$this->activate( 'fresh' );
		$before = $this->resolved();

		$this->assertNull( StyleVariationManager::get_theme_base_for_slug( $slug ) );

		$this->assertSame( 'fresh', StyleVariationManager::get_instance()->get_active_slug() );
		$this->assertSame( $before, $this->resolved() );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function unregistered_skins(): array {
		return [
			'unknown'         => [ 'missing' ],
			'empty'           => [ '' ],
			'a path'          => [ '../fresh' ],
			'capitals'        => [ 'FRESH' ],
			'the shared skin' => [ '_default' ],
		];
	}

	public function test_another_skin_leaves_the_active_one_in_place(): void {
		$this->activate( 'fresh' );
		$before = $this->cached();

		$base = StyleVariationManager::get_theme_base_for_slug( 'second' );

		$this->assertSame( 'second-token', $base['settings']['custom']['skinToken'] );
		$this->assertSame( 'fresh', StyleVariationManager::get_instance()->get_active_slug() );
		$this->assertSame( $before, $this->resolved() );
		$this->assertSame( 'fresh-token', $this->resolved()['settings']['custom']['skinToken'] );
	}

	public function test_with_no_active_skin_none_stays_afterward(): void {
		$before = $this->cached();

		StyleVariationManager::get_theme_base_for_slug( 'second' );

		$this->assertSame( '', StyleVariationManager::get_instance()->get_active_slug() );
		$this->assertSame( $before, $this->resolved() );
	}

	public function test_a_failure_while_resolving_still_puts_things_back(): void {
		$this->activate( 'fresh' );

		$throw = static function (): void {
			throw new RuntimeException( 'Broken filter' );
		};
		add_filter( 'wp_theme_json_data_theme', $throw, 1 );
		WP_Theme_JSON_Resolver::clean_cached_data();

		try {
			StyleVariationManager::get_theme_base_for_slug( 'second' );
			$this->fail( 'The filter should have thrown.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'Broken filter', $e->getMessage() );
		}

		remove_filter( 'wp_theme_json_data_theme', $throw, 1 );

		$this->assertSame( 'fresh', StyleVariationManager::get_instance()->get_active_slug() );
		$this->assertSame( 'fresh-token', $this->resolved()['settings']['custom']['skinToken'] );
	}

	public function test_asking_for_another_skin_saves_nothing(): void {
		$this->activate( 'fresh' );
		$mods = $this->saved_mods();

		StyleVariationManager::get_theme_base_for_slug( 'second' );

		$this->assertSame( $mods, $this->saved_mods() );
		$this->assertSame( 'fresh', get_theme_mod( StyleVariationManager::THEME_MOD_KEY ) );
		$this->assertSame( 0, $this->purges );
	}

	public function test_asking_with_nothing_saved_writes_no_mod(): void {
		StyleVariationManager::get_theme_base_for_slug( 'second' );

		$this->assertFalse( $this->saved_mods() );
	}

	// --- the route: who can call it

	public function test_logged_out_is_refused(): void {
		wp_set_current_user( 0 );

		$this->assertContains( $this->request()->get_status(), [ 401, 403 ] );
	}

	/**
	 * @dataProvider roles_without_the_capability
	 */
	public function test_roles_without_edit_theme_options_are_refused( string $role ): void {
		$this->as_role( $role );

		$this->assertSame( 403, $this->request()->get_status() );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function roles_without_the_capability(): array {
		return [
			'subscriber' => [ 'subscriber' ],
			'author'     => [ 'author' ],
			'editor'     => [ 'editor' ],
		];
	}

	public function test_edit_theme_options_is_what_opens_it(): void {
		$this->as_role( 'subscriber' );
		wp_get_current_user()->add_cap( 'edit_theme_options' );

		$this->assertSame( 200, $this->request()->get_status() );
	}

	// --- the route: what it takes

	public function test_slug_is_required(): void {
		$this->as_role( 'administrator' );

		$response = $this->request( 'GET', null );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'] );
	}

	/**
	 * @dataProvider bad_slugs
	 */
	public function test_a_bad_slug_pattern_is_refused( string $slug ): void {
		$this->as_role( 'administrator' );

		$response = $this->request( 'GET', $slug );

		$this->assertSame( 400, $response->get_status(), $slug );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function bad_slugs(): array {
		return [
			'traversal'     => [ '../fresh' ],
			'capitals'      => [ 'Fresh' ],
			'a space'       => [ 'fresh look' ],
			'an underscore' => [ '_default' ],
			'a slash'       => [ 'a/b' ],
			'a dot'         => [ 'fresh.json' ],
			'empty'         => [ '' ],
			'unicode'       => [ 'frésh' ],
		];
	}

	public function test_a_trailing_newline_never_finds_a_skin(): void {
		$this->as_role( 'administrator' );

		$this->assertNotSame( 200, $this->request( 'GET', "fresh\n" )->get_status() );
	}

	public function test_an_array_slug_is_refused(): void {
		$this->as_role( 'administrator' );

		$request = new WP_REST_Request( 'GET', self::ROUTE );
		$request->set_query_params( [ 'slug' => [ 'fresh' ] ] );

		$this->assertSame( 400, rest_get_server()->dispatch( $request )->get_status() );
	}

	public function test_an_unknown_skin_is_a_404(): void {
		$this->as_role( 'administrator' );

		$response = $this->request( 'GET', 'missing' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'theme_skin_switcher_unknown_skin', $response->get_data()['code'] );
	}

	public function test_a_known_skin_returns_its_settings_and_styles(): void {
		$this->as_role( 'administrator' );

		$response = $this->request();
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'settings', 'styles' ], array_keys( $data ) );
		$this->assertSame( 'fresh-token', $data['settings']['custom']['skinToken'] );
		$this->assertSame( '#010101', $data['styles']['color']['background'] );
	}

	public function test_the_route_reads_only(): void {
		$this->as_role( 'administrator' );

		$this->assertSame( 404, $this->request( 'POST' )->get_status() );
		$this->assertSame( 404, $this->request( 'DELETE' )->get_status() );
	}

	public function test_a_request_changes_nothing_saved(): void {
		$this->as_role( 'administrator' );
		$this->activate( 'fresh' );
		$mods  = $this->saved_mods();
		$parts = ( new WP_Query(
			[
				'post_type' => 'wp_template_part',
				'fields'    => 'ids',
			]
		) )->post_count;

		$this->request( 'GET', 'second' );

		$this->assertSame( $mods, $this->saved_mods() );
		$this->assertSame( 'fresh', StyleVariationManager::get_instance()->get_active_slug() );
		$this->assertSame(
			$parts,
			( new WP_Query(
				[
					'post_type' => 'wp_template_part',
					'fields'    => 'ids',
				]
			) )->post_count
		);
	}
}
