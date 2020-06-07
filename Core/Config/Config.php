<?php
namespace QTCS\Config;

class Config {
	static private $instances = [];

	public static function get(string $name, bool $getShared = true) {
		$class = $name;
		if (($pos = strrpos($name, '\\')) !== false)
		{
			$class = substr($name, $pos + 1);
		}

		if (! $getShared)
		{
			return self::createClass($name);
		}

		if (! isset( self::$instances[$class] ))
		{
			self::$instances[$class] = self::createClass($name);
		}
		return self::$instances[$class];
	}

	public static function reset() {
		static::$instances = [];
	}

	private static function createClass(string $name)
	{
		if (class_exists($name))
		{
			return new $name();
		}

		$locator = Services::locator();
		$file    = $locator->locateFile($name, 'Config');

		if (empty($file))
		{
			// No file found - check if the class was namespaced
			if (strpos($name, '\\') !== false)
			{
				// Class was namespaced and locateFile couldn't find it
				return null;
			}

			// Check all namespaces
			$files = $locator->search('Config/' . $name);
			if (empty($files))
			{
				return null;
			}

			// Get the first match (prioritizes user and framework)
			$file = reset($files);
		}

		$name = $locator->getClassname($file);

		if (empty($name))
		{
			return null;
		}

		return new $name();
	}
}