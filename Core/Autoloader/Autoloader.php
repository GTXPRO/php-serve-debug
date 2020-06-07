<?php
namespace QTCS\Autoloader;

use Config\Autoload;

class Autoloader {
	protected $prefixes = [];
	protected $classmap = [];
	
	public function initialize(Autoload $config)
	{
		if (empty($config->psr4) && empty($config->classmap))
		{
			throw new \InvalidArgumentException('Config array must contain either the \'psr4\' key or the \'classmap\' key.');
		}

		if (isset($config->psr4))
		{
			$this->addNamespace($config->psr4);
		}

		if (isset($config->classmap))
		{
			$this->classmap = $config->classmap;
		}
	}

	public function register()
	{
		spl_autoload_extensions('.php,.inc');

		spl_autoload_register([$this, 'loadClass'], true, true);

		// Now prepend another loader for the files in our class map.
		$config = is_array($this->classmap) ? $this->classmap : [];

		spl_autoload_register(function ($class) use ($config) {
			if (empty($config[$class]))
			{
				return false;
			}

			include_once $config[$class];
		}, true, // Throw exception
			true // Prepend
		);
	}

	public function addNamespace($namespace, string $path = null)
	{
		if (is_array($namespace))
		{
			foreach ($namespace as $prefix => $path)
			{
				$prefix = trim($prefix, '\\');

				if (is_array($path))
				{
					foreach ($path as $dir)
					{
						$this->prefixes[$prefix][] = rtrim($dir, '/') . '/';
					}

					continue;
				}

				$this->prefixes[$prefix][] = rtrim($path, '/') . '/';
			}
		}
		else
		{
			$this->prefixes[trim($namespace, '\\')][] = rtrim($path, '/') . '/';
		}

		return $this;
	}

	public function getNamespace(string $prefix = null)
	{
		if ($prefix === null)
		{
			return $this->prefixes;
		}

		return $this->prefixes[trim($prefix, '\\')] ?? [];
	}

	public function removeNamespace(string $namespace)
	{
		unset($this->prefixes[trim($namespace, '\\')]);

		return $this;
	}

	public function loadClass(string $class)
	{
		$class = trim($class, '\\');
		$class = str_ireplace('.php', '', $class);

		$mapped_file = $this->loadInNamespace($class);

		// Nothing? One last chance by looking
		// in common CodeIgniter folders.
		if (! $mapped_file)
		{
			$mapped_file = $this->loadLegacy($class);
		}

		return $mapped_file;
	}

	protected function loadInNamespace(string $class)
	{
		if (strpos($class, '\\') === false)
		{
			return false;
		}

		foreach ($this->prefixes as $namespace => $directories)
		{
			foreach ($directories as $directory)
			{
				$directory = rtrim($directory, '/');

				if (strpos($class, $namespace) === 0)
				{
					$filePath = $directory . str_replace('\\', '/',
							substr($class, strlen($namespace))) . '.php';
					$filename = $this->requireFile($filePath);

					if ($filename)
					{
						return $filename;
					}
				}
			}
		}

		// never found a mapped file
		return false;
	}

	protected function loadLegacy(string $class)
	{
		// If there is a namespace on this class, then
		// we cannot load it from traditional locations.
		if (strpos($class, '\\') !== false)
		{
			return false;
		}

		$paths = [
			APP_PATH . 'Controllers/',
			APP_PATH . 'Models/',
		];

		$class = str_replace('\\', '/', $class) . '.php';

		foreach ($paths as $path)
		{
			if ($file = $this->requireFile($path . $class))
			{
				return $file;
			}
		}

		return false;
	}

	protected function requireFile(string $file)
	{
		$file = $this->sanitizeFilename($file);

		if (is_file($file))
		{
			require_once $file;

			return $file;
		}

		return false;
	}

	public function sanitizeFilename(string $filename): string
	{
		$filename = preg_replace('/[^0-9\p{L}\s\/\-\_\.\:\\\\]/u', '', $filename);

		// Clean up our filename edges.
		$filename = trim($filename, '.-_');

		return $filename;
	}

	protected function discoverComposerNamespaces()
	{
		echo "discoverComposerNamespaces";
		if (! is_file(COMPOSER_PATH))
		{
			return false;
		}

		$composer = include COMPOSER_PATH;

		$paths = $composer->getPrefixesPsr4();
		unset($composer);

		// Get rid of CodeIgniter so we don't have duplicates
		if (isset($paths['QTCS\\']))
		{
			unset($paths['QTCS\\']);
		}

		// Composer stores namespaces with trailing slash. We don't.
		$newPaths = [];
		foreach ($paths as $key => $value)
		{
			$newPaths[rtrim($key, '\\ ')] = $value;
		}

		$this->prefixes = array_merge($this->prefixes, $newPaths);
	}
}