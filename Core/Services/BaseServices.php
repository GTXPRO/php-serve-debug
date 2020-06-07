<?php
namespace QTCS\Services;

use Config\Autoload;
use Config\Modules;
use QTCS\Autoloader\Autoloader;
use QTCS\Autoloader\FileLocator;

class BaseServices {
	static protected $instances = [];
	static protected $mocks = [];
	static protected $discovered = false;
	static protected $services = [];

	protected static function getSharedInstance(string $key, ...$params)
	{
		$key = strtolower($key);

		// Returns mock if exists
		if (isset(static::$mocks[$key]))
		{
			return static::$mocks[$key];
		}

		if (! isset(static::$instances[$key]))
		{
			// Make sure $getShared is false
			array_push($params, false);

			static::$instances[$key] = static::$key(...$params);
		}

		return static::$instances[$key];
	}

	public static function autoloader(bool $getShared = true)
	{
		if ($getShared)
		{
			if (empty(static::$instances['autoloader']))
			{
				static::$instances['autoloader'] = new Autoloader();
			}

			return static::$instances['autoloader'];
		}

		return new Autoloader();
	}

	public static function locator(bool $getShared = true)
	{
		if ($getShared)
		{
			if (empty(static::$instances['locator']))
			{
				static::$instances['locator'] = new FileLocator(
					static::autoloader()
				);
			}

			return static::$instances['locator'];
		}

		return new FileLocator(static::autoloader());
	}

	public static function __callStatic(string $name, array $arguments)
	{
		$name = strtolower($name);

		if (method_exists(Services::class, $name))
		{
			return Services::$name(...$arguments);
		}

		return static::discoverServices($name, $arguments);
	}

	public static function reset(bool $init_autoloader = false)
	{
		static::$mocks = [];

		static::$instances = [];

		if ($init_autoloader)
		{
			static::autoloader()->initialize(new Autoload(), new Modules());
		}
	}

	public static function injectMock(string $name, $mock)
	{
		$name                 = strtolower($name);
		static::$mocks[$name] = $mock;
	}

	protected static function discoverServices(string $name, array $arguments)
	{
		if (! static::$discovered)
		{
			$config = new Modules();

			if ($config->shouldDiscover('services'))
			{
				$locator = static::locator();
				$files   = $locator->search('Config/Services');

				if (empty($files))
				{
					// no files at all found - this would be really, really bad
					return null;
				}

				// Get instances of all service classes and cache them locally.
				foreach ($files as $file)
				{
					$classname = $locator->getClassname($file);

					if (! in_array($classname, ['CodeIgniter\\Config\\Services']))
					{
						static::$services[] = new $classname();
					}
				}
			}

			static::$discovered = true;
		}

		if (! static::$services)
		{
			// we found stuff, but no services - this would be really bad
			return null;
		}

		// Try to find the desired service method
		foreach (static::$services as $class)
		{
			if (method_exists(get_class($class), $name))
			{
				return $class::$name(...$arguments);
			}
		}

		return null;
	}
}