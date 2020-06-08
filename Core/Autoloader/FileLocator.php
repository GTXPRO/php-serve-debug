<?php
namespace QTCS\Autoloader;

class FileLocator {
	protected $autoloader;

	public function __construct(Autoloader $autoloader)
	{
		$this->autoloader = $autoloader;
	}

	public function locateFile(string $file, string $folder = null, string $ext = 'php')
	{
		$file = $this->ensureExt($file, $ext);

		// Clears the folder name if it is at the beginning of the filename
		if (! empty($folder) && ($pos = strpos($file, $folder)) === 0)
		{
			$file = substr($file, strlen($folder . '/'));
		}

		// Is not namespaced? Try the application folder.
		if (strpos($file, '\\') === false)
		{
			return $this->legacyLocate($file, $folder);
		}

		// Standardize slashes to handle nested directories.
		$file = strtr($file, '/', '\\');

		$segments = explode('\\', $file);

		if (empty($segments[0]))
		{
			unset($segments[0]);
		}

		$paths    = [];
		$prefix   = '';
		$filename = '';

		$namespaces = $this->autoloader->getNamespace();

		while (! empty($segments))
		{
			$prefix .= empty($prefix) ? array_shift($segments) : '\\' . array_shift($segments);

			if (empty($namespaces[$prefix]))
			{
				continue;
			}
			$paths = $namespaces[$prefix];

			$filename = implode('/', $segments);
			break;
		}

		if (empty($paths))
		{
			return false;
		}

		foreach ($paths as $path)
		{
			$path = rtrim($path, '/') . '/';

			if (! empty($folder) && strpos($path . $filename, '/' . $folder . '/') === false)
			{
				$path .= trim($folder, '/') . '/';
			}

			$path .= $filename;
			if (is_file($path))
			{
				return $path;
			}
		}

		return false;
	}

	public function getClassname(string $file) : string
	{
		$php        = file_get_contents($file);
		$tokens     = token_get_all($php);
		$count      = count($tokens);
		$dlm        = false;
		$namespace  = '';
		$class_name = '';

		for ($i = 2; $i < $count; $i++)
		{
			if ((isset($tokens[$i - 2][1]) && ($tokens[$i - 2][1] === 'phpnamespace' || $tokens[$i - 2][1] === 'namespace')) || ($dlm && $tokens[$i - 1][0] === T_NS_SEPARATOR && $tokens[$i][0] === T_STRING))
			{
				if (! $dlm)
				{
					$namespace = 0;
				}
				if (isset($tokens[$i][1]))
				{
					$namespace = $namespace ? $namespace . '\\' . $tokens[$i][1] : $tokens[$i][1];
					$dlm       = true;
				}
			}
			elseif ($dlm && ($tokens[$i][0] !== T_NS_SEPARATOR) && ($tokens[$i][0] !== T_STRING))
			{
				$dlm = false;
			}
			if (($tokens[$i - 2][0] === T_CLASS || (isset($tokens[$i - 2][1]) && $tokens[$i - 2][1] === 'phpclass'))
				&& $tokens[$i - 1][0] === T_WHITESPACE
				&& $tokens[$i][0] === T_STRING)
			{
				$class_name = $tokens[$i][1];
				break;
			}
		}

		if (empty( $class_name ))
		{
			return '';
		}

		return $namespace . '\\' . $class_name;
	}

	public function search(string $path, string $ext = 'php'): array
	{
		$path = $this->ensureExt($path, $ext);

		$foundPaths = [];

		foreach ($this->getNamespaces() as $namespace)
		{
			if (isset($namespace['path']) && is_file($namespace['path'] . $path))
			{
				$foundPaths[] = $namespace['path'] . $path;
			}
		}

		// Remove any duplicates
		$foundPaths = array_unique($foundPaths);

		return $foundPaths;
	}

	protected function ensureExt(string $path, string $ext): string
	{
		if ($ext)
		{
			$ext = '.' . $ext;

			if (substr($path, -strlen($ext)) !== $ext)
			{
				$path .= $ext;
			}
		}

		return $path;
	}

	protected function getNamespaces()
	{
		$namespaces = [];

		// Save system for last
		$system = [];

		foreach ($this->autoloader->getNamespace() as $prefix => $paths)
		{
			foreach ($paths as $path)
			{
				if ($prefix === 'QTCS')
				{
					$system = [
						'prefix' => $prefix,
						'path'   => rtrim($path, '\\/') . DIRECTORY_SEPARATOR,
					];

					continue;
				}

				$namespaces[] = [
					'prefix' => $prefix,
					'path'   => rtrim($path, '\\/') . DIRECTORY_SEPARATOR,
				];
			}
		}

		$namespaces[] = $system;

		return $namespaces;
	}

	public function findQualifiedNameFromPath(string $path)
	{
		$path = realpath($path);

		if (! $path)
		{
			return false;
		}

		foreach ($this->getNamespaces() as $namespace)
		{
			$namespace['path'] = realpath($namespace['path']);

			if (empty($namespace['path']))
			{
				continue;
			}

			if (mb_strpos($path, $namespace['path']) === 0)
			{
				$className = '\\' . $namespace['prefix'] . '\\' .
						ltrim(str_replace('/', '\\', mb_substr(
							$path, mb_strlen($namespace['path']))
						), '\\');
				// Remove the file extension (.php)
				$className = mb_substr($className, 0, -4);

				// Check if this exists
				if (class_exists($className))
				{
					return $className;
				}
			}
		}

		return false;
	}

	public function listFiles(string $path): array
	{
		if (empty($path))
		{
			return [];
		}

		$files = [];

		foreach ($this->getNamespaces() as $namespace)
		{
			$fullPath = realpath($namespace['path'] . $path);

			if (! is_dir($fullPath))
			{
				continue;
			}

			$tempFiles = get_filenames($fullPath, true);

			if (! empty($tempFiles))
			{
				$files = array_merge($files, $tempFiles);
			}
		}

		return $files;
	}

	public function listNamespaceFiles(string $prefix, string $path): array
	{
		if (empty($path) || empty($prefix))
		{
			return [];
		}

		$files = [];

		// autoloader->getNamespace($prefix) returns an array of paths for that namespace
		foreach ($this->autoloader->getNamespace($prefix) as $namespacePath)
		{
			$fullPath = realpath(rtrim($namespacePath, '/') . '/' . $path);

			if (! is_dir($fullPath))
			{
				continue;
			}

			$tempFiles = get_filenames($fullPath, true);

			if (! empty($tempFiles))
			{
				$files = array_merge($files, $tempFiles);
			}
		}

		return $files;
	}

	protected function legacyLocate(string $file, string $folder = null)
	{
		$paths = [
			APP_PATH,
			CORE_PATH,
		];

		foreach ($paths as $path)
		{
			$path .= empty($folder) ? $file : $folder . '/' . $file;

			if (is_file($path))
			{
				return $path;
			}
		}

		return false;
	}
}