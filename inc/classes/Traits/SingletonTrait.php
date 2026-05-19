<?php

declare(strict_types=1);

namespace ThemeSkinSwitcher\Traits;

defined('ABSPATH') || defined('WP_CLI') || exit;

/**
 * Singleton helper for package services.
 */
trait SingletonTrait
{
	/** @var static|null */
	private static $instance = null;

	/**
	 * @return static
	 */
	public static function get_instance(...$args)
	{
		if (self::$instance === null) {
			self::$instance = new static(...$args);
		}

		return self::$instance;
	}

	protected function __construct()
	{
	}

	private function __clone()
	{
	}

	public function __wakeup()
	{
		throw new \Exception('Cannot unserialize singleton');
	}
}
