<?php
/**
 * Kitaris' NavigationRefGuard with real menus: Kitaris has no WordPress test
 * suite of its own, and `kitarisMenu` is how a skin's parts name a menu.
 *
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

declare(strict_types=1);

namespace KitarisModule\ThemeSkinSwitcher\Tests\Integration;

use Kitaris\Blocks\NavigationRefGuard;
use Siratik\Config\Repository;
use WP_UnitTestCase;

class NavigationRefGuardMenusTest extends WP_UnitTestCase {

	private function guard( array $attrs ): array {
		$guard = new NavigationRefGuard( $this->createMock( Repository::class ) );

		return $guard->guard_ref(
			[
				'blockName' => 'core/navigation',
				'attrs'     => $attrs,
			]
		)['attrs'];
	}

	private function menu( string $slug, string $status = 'publish', string $type = 'wp_navigation' ): int {
		return self::factory()->post->create(
			[
				'post_type'   => $type,
				'post_name'   => $slug,
				'post_status' => $status,
			]
		);
	}

	public function test_a_published_menu_resolves_by_slug(): void {
		$menu = $this->menu( 'primary' );

		$this->assertSame( $menu, $this->guard( [ 'kitarisMenu' => 'primary' ] )['ref'] );
	}

	public function test_an_unknown_slug_leaves_cores_fallback(): void {
		$this->assertArrayNotHasKey( 'ref', $this->guard( [ 'kitarisMenu' => 'primary' ] ) );
	}

	public function test_a_draft_menu_is_not_used(): void {
		$this->menu( 'primary', 'draft' );

		$this->assertArrayNotHasKey( 'ref', $this->guard( [ 'kitarisMenu' => 'primary' ] ) );
	}

	public function test_a_private_or_future_menu_is_not_used(): void {
		$this->menu( 'one', 'private' );
		$this->menu( 'two', 'pending' );

		$this->assertArrayNotHasKey( 'ref', $this->guard( [ 'kitarisMenu' => 'one' ] ) );
		$this->assertArrayNotHasKey( 'ref', $this->guard( [ 'kitarisMenu' => 'two' ] ) );
	}

	public function test_a_trashed_menu_is_not_used(): void {
		$menu = $this->menu( 'primary' );
		wp_trash_post( $menu );

		$this->assertArrayNotHasKey( 'ref', $this->guard( [ 'kitarisMenu' => 'primary' ] ) );
		$this->assertArrayNotHasKey( 'ref', $this->guard( [ 'ref' => $menu ] ) );
	}

	public function test_a_trashed_menu_with_its_slug_back_is_not_used(): void {
		$menu = $this->menu( 'primary' );
		wp_trash_post( $menu );
		global $wpdb;
		$wpdb->update( $wpdb->posts, [ 'post_name' => 'primary' ], [ 'ID' => $menu ] );
		clean_post_cache( $menu );

		$this->assertArrayNotHasKey( 'ref', $this->guard( [ 'kitarisMenu' => 'primary' ] ) );
	}

	public function test_a_page_with_the_slug_is_not_a_menu(): void {
		$this->menu( 'primary', 'publish', 'page' );

		$this->assertArrayNotHasKey( 'ref', $this->guard( [ 'kitarisMenu' => 'primary' ] ) );
	}

	public function test_a_valid_ref_wins(): void {
		$named = $this->menu( 'primary' );
		$ref   = $this->menu( 'other' );

		$this->assertSame( $ref, $this->guard( [ 'ref' => $ref, 'kitarisMenu' => 'primary' ] )['ref'] );
		$this->assertNotSame( $named, $ref );
	}

	public function test_a_newer_draft_sharing_the_slug_does_not_hide_the_published_menu(): void {
		$menu = $this->menu( 'primary' );
		$this->menu( 'primary', 'draft' );

		$this->assertSame( $menu, $this->guard( [ 'kitarisMenu' => 'primary' ] )['ref'] ?? null );
	}

	public function test_an_older_draft_sharing_the_slug_does_not_hide_the_published_menu(): void {
		$draft = $this->menu( 'primary', 'draft' );
		$menu  = $this->menu( 'primary' );
		global $wpdb;
		$wpdb->update( $wpdb->posts, [ 'post_name' => 'primary' ], [ 'ID' => $menu ] );
		clean_post_cache( $menu );

		$this->assertNotSame( $draft, $menu );
		$this->assertSame( $menu, $this->guard( [ 'kitarisMenu' => 'primary' ] )['ref'] ?? null );
	}

	public function test_a_stale_ref_is_rescued_by_the_named_menu(): void {
		$menu = $this->menu( 'primary' );

		$this->assertSame( $menu, $this->guard( [ 'ref' => 9999999, 'kitarisMenu' => 'primary' ] )['ref'] ?? null );
	}

	public function test_a_ref_to_a_draft_is_rescued_by_the_named_menu(): void {
		$menu  = $this->menu( 'primary' );
		$draft = $this->menu( 'other', 'draft' );

		$this->assertSame( $menu, $this->guard( [ 'ref' => $draft, 'kitarisMenu' => 'primary' ] )['ref'] ?? null );
	}

	public function test_a_stale_ref_with_an_unknown_name_leaves_cores_fallback(): void {
		$this->assertArrayNotHasKey( 'ref', $this->guard( [ 'ref' => 9999999, 'kitarisMenu' => 'primary' ] ) );
	}
}
