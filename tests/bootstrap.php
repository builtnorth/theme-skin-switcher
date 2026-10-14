<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Pure unit suite: WordPress functions are mocked with WP_Mock, and the few WP core
 * classes touched (WP_Block_Editor_Context) come from the shared
 * builtnorth/wp-mock-stand-ins package. Skin packs are real temporary directories
 * built by tests/Support/SkinPackFixture.php. No WordPress install or test database
 * is needed.
 *
 * @package KitarisModule\ThemeSkinSwitcher\Tests
 */

// Suppress PHP 8.4 deprecation warnings from WP_Mock (vendor) until upstream fixes signatures.
error_reporting(E_ALL & ~E_DEPRECATED);

/*
 * Define ABSPATH before the autoloader runs. Every class in this package guards its file
 * with `defined('ABSPATH') || defined('WP_CLI') || exit;` — without it, autoloading any
 * of them silently exit()s the PHP process (exit code 0, no test ever runs).
 */
if (! defined('ABSPATH')) {
	define('ABSPATH', '/tmp/wordpress/');
}

// Falls back to the monorepo root's autoloader when this package has no
// standalone vendor/ install (the normal dev setup — see "Autoloader
// Architecture" in the root CLAUDE.md).
$autoload = __DIR__ . '/../vendor/autoload.php';
$using_root_autoloader = ! file_exists($autoload);
if ($using_root_autoloader) {
	$autoload = __DIR__ . '/../../../vendor/autoload.php';
}

if (! file_exists($autoload)) {
	fwrite(STDERR, "Run `composer install` before the test suite.\n");
	exit(1);
}

require $autoload;

// The root autoloader only carries this package's own runtime `autoload`
// PSR-4 mapping, never a dependency's `autoload-dev` — register the Tests
// namespace by hand when running under the root autoloader.
if ($using_root_autoloader) {
	spl_autoload_register(static function (string $class): void {
		$prefix = 'KitarisModule\\ThemeSkinSwitcher\\Tests\\';
		if (! str_starts_with($class, $prefix)) {
			return;
		}
		$relative = substr($class, strlen($prefix));
		$file     = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
		if (file_exists($file)) {
			require $file;
		}
	});
}

// Shared WP core class stand-ins (WP_Error, WP_Block_Editor_Context, etc.) — lives in
// whichever vendor/ $autoload above actually resolved to (local or root).
require_once dirname($autoload) . '/builtnorth/wp-mock-stand-ins/inc/stand-ins.php';

WP_Mock::bootstrap();
