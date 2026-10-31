<?php
/**
 * PHPUnit bootstrap for the integration tests (real WordPress and the test DB).
 *
 * Separate from tests/bootstrap.php (WP_Mock): template parts, menus and theme
 * mods are core's own behavior. Only WordPress and the autoloader load, not the
 * module: the tests use its classes directly.
 *
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

declare(strict_types=1);

if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
}

$_root      = dirname( __DIR__, 3 );
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: $_root . '/vendor/wp-phpunit/wp-phpunit';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test library. Run the tests with `lando test`.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output
	exit( 1 );
}

require_once $_root . '/vendor/autoload.php';
require_once $_tests_dir . '/includes/functions.php';
require $_tests_dir . '/includes/bootstrap.php';
