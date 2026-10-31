<?php
/**
 * WordPress test configuration for wp-env (CI) and Lando (local).
 *
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

// Test database configuration
define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) ?: getenv( 'DB_NAME' ) ?: 'wordpress_test' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ?: getenv( 'DB_USER' ) ?: 'wordpress' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ?: getenv( 'DB_PASSWORD' ) ?: 'wordpress' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ?: getenv( 'DB_HOST' ) ?: 'database' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// Use these settings for tests (wp-env can override via getenv).
define( 'WP_TESTS_DOMAIN', getenv( 'WP_TESTS_DOMAIN' ) ?: 'example.org' );
define( 'WP_TESTS_EMAIL', getenv( 'WP_TESTS_EMAIL' ) ?: 'admin@example.org' );
define( 'WP_TESTS_TITLE', getenv( 'WP_TESTS_TITLE' ) ?: 'Test Blog' );
define( 'WP_PHP_BINARY', getenv( 'WP_PHP_BINARY' ) ?: 'php' );

// WordPress root: wp-env tests-cli uses /var/www/html; Lando uses /app/app.
if ( ! defined( 'ABSPATH' ) ) {
	$abspath = getenv( 'WORDPRESS_ABSPATH' );
	if ( ! $abspath && file_exists( '/var/www/html/wp-settings.php' ) ) {
		$abspath = '/var/www/html/';
	}
	if ( ! $abspath && file_exists( '/app/app/wp-settings.php' ) ) {
		$abspath = '/app/app/';
	}
	if ( ! $abspath ) {
		$abspath = '/var/www/html/';
	}
	define( 'ABSPATH', rtrim( $abspath, '/\\' ) . '/' );
}

// Table prefix
$table_prefix = getenv( 'WP_PHPUNIT__TABLE_PREFIX' ) ?: 'wptests_';

// Debug settings for tests
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );

// Isolated content dir so WP 6.8+ can register a themes root before
// wp_is_block_theme() runs. Do not use the live site wp-content (Redis
// object-cache.php drop-in).
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	$content = __DIR__ . '/wp-content';
	if ( ! is_dir( $content . '/themes' ) ) {
		mkdir( $content . '/themes', 0777, true );
	}
	define( 'WP_CONTENT_DIR', $content );
}
if ( ! defined( 'WP_CONTENT_URL' ) ) {
	define( 'WP_CONTENT_URL', 'http://' . WP_TESTS_DOMAIN . '/wp-content' );
}
