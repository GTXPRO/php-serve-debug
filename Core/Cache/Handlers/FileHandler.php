<?php
namespace QTCS\Cache\Handlers;

use QTCS\Cache\CacheInterface;

class FileHandler implements CacheInterface
{
	protected $prefix;
	protected $path;
	public function __construct($config)
	{
		$path = ! empty($config->storePath) ? $config->storePath : WRITE_PATH . 'cache';
		if (! is_really_writable($path))
		{
			throw new \RuntimeException("Cache unable to write to {{$path}}");
		}

		$this->prefix = $config->prefix ?: '';
		$this->path   = rtrim($path, '/') . '/';
	}

	public function initialize()
	{
		// Not to see here...
	}

	public function get(string $key)
	{
		$key = $this->prefix . $key;

		$data = $this->getItem($key);

		return is_array($data) ? $data['data'] : null;
	}


	public function save(string $key, $value, int $ttl = 60)
	{
		$key = $this->prefix . $key;

		$contents = [
			'time' => time(),
			'ttl'  => $ttl,
			'data' => $value,
		];

		if ($this->writeFile($this->path . $key, serialize($contents)))
		{
			chmod($this->path . $key, 0640);

			return true;
		}

		return false;
	}

	
	public function delete(string $key)
	{
		$key = $this->prefix . $key;

		return is_file($this->path . $key) && unlink($this->path . $key);
	}

	
	
	public function increment(string $key, int $offset = 1)
	{
		$key = $this->prefix . $key;

		$data = $this->getItem($key);

		if ($data === false)
		{
			$data = [
				'data' => 0,
				'ttl'  => 60,
			];
		}
		elseif (! is_int($data['data']))
		{
			return false;
		}

		$new_value = $data['data'] + $offset;

		return $this->save($key, $new_value, $data['ttl']) ? $new_value : false;
	}

	
	
	public function decrement(string $key, int $offset = 1)
	{
		$key = $this->prefix . $key;

		$data = $this->getItem($key);

		if ($data === false)
		{
			$data = [
				'data' => 0,
				'ttl'  => 60,
			];
		}
		elseif (! is_int($data['data']))
		{
			return false;
		}

		$new_value = $data['data'] - $offset;

		return $this->save($key, $new_value, $data['ttl']) ? $new_value : false;
	}

	
	
	public function clean()
	{
		return $this->deleteFiles($this->path, false, true);
	}

	
	
	public function getCacheInfo()
	{
		return $this->getDirFileInfo($this->path);
	}

	
	public function getMetaData(string $key)
	{
		$key = $this->prefix . $key;

		if (! is_file($this->path . $key))
		{
			return false;
		}

		$data = @unserialize(file_get_contents($this->path . $key));

		if (is_array($data))
		{
			$mtime = filemtime($this->path . $key);

			if (! isset($data['ttl']))
			{
				return false;
			}

			return [
				'expire' => $mtime + $data['ttl'],
				'mtime'  => $mtime,
				'data'   => $data['data'],
			];
		}

		return false;
	}

	
	public function isSupported(): bool
	{
		return is_writable($this->path);
	}


	protected function getItem(string $key)
	{
		if (! is_file($this->path . $key))
		{
			return false;
		}

		$data = unserialize(file_get_contents($this->path . $key));

		if ($data['ttl'] > 0 && time() > $data['time'] + $data['ttl'])
		{
			// If the file is still there then remove it
			if (is_file($this->path . $key))
			{
				unlink($this->path . $key);
			}

			return false;
		}

		return $data;
	}

	
	protected function writeFile($path, $data, $mode = 'wb')
	{
		if (($fp = @fopen($path, $mode)) === false)
		{
			return false;
		}

		flock($fp, LOCK_EX);

		for ($result = $written = 0, $length = strlen($data); $written < $length; $written += $result)
		{
			if (($result = fwrite($fp, substr($data, $written))) === false)
			{
				break;
			}
		}

		flock($fp, LOCK_UN);
		fclose($fp);

		return is_int($result);
	}

	protected function deleteFiles(string $path, bool $del_dir = false, bool $htdocs = false, int $_level = 0): bool
	{
		// Trim the trailing slash
		$path = rtrim($path, '/\\');

		if (! $current_dir = @opendir($path))
		{
			return false;
		}

		while (false !== ($filename = @readdir($current_dir)))
		{
			if ($filename !== '.' && $filename !== '..')
			{
				if (is_dir($path . DIRECTORY_SEPARATOR . $filename) && $filename[0] !== '.')
				{
					$this->deleteFiles($path . DIRECTORY_SEPARATOR . $filename, $del_dir, $htdocs, $_level + 1);
				}
				elseif ($htdocs !== true || ! preg_match('/^(\.htaccess|index\.(html|htm|php)|web\.config)$/i', $filename))
				{
					@unlink($path . DIRECTORY_SEPARATOR . $filename);
				}
			}
		}

		closedir($current_dir);

		return ($del_dir === true && $_level > 0) ? @rmdir($path) : true;
	}

	protected function getDirFileInfo(string $source_dir, bool $top_level_only = true, bool $_recursion = false)
	{
		static $_filedata = [];
		$relative_path    = $source_dir;

		if ($fp = @opendir($source_dir))
		{
			// reset the array and make sure $source_dir has a trailing slash on the initial call
			if ($_recursion === false)
			{
				$_filedata  = [];
				$source_dir = rtrim(realpath($source_dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
			}

			// Used to be foreach (scandir($source_dir, 1) as $file), but scandir() is simply not as fast
			while (false !== ($file = readdir($fp)))
			{
				if (is_dir($source_dir . $file) && $file[0] !== '.' && $top_level_only === false)
				{
					$this->getDirFileInfo($source_dir . $file . DIRECTORY_SEPARATOR, $top_level_only, true);
				}
				elseif ($file[0] !== '.')
				{
					$_filedata[$file]                  = $this->getFileInfo($source_dir . $file);
					$_filedata[$file]['relative_path'] = $relative_path;
				}
			}

			closedir($fp);

			return $_filedata;
		}

		return false;
	}

	protected function getFileInfo(string $file, $returned_values = ['name', 'server_path', 'size', 'date'])
	{
		if (! is_file($file))
		{
			return false;
		}

		if (is_string($returned_values))
		{
			$returned_values = explode(',', $returned_values);
		}

		foreach ($returned_values as $key)
		{
			switch ($key)
			{
				case 'name':
					$fileInfo['name'] = basename($file);
					break;
				case 'server_path':
					$fileInfo['server_path'] = $file;
					break;
				case 'size':
					$fileInfo['size'] = filesize($file);
					break;
				case 'date':
					$fileInfo['date'] = filemtime($file);
					break;
				case 'readable':
					$fileInfo['readable'] = is_readable($file);
					break;
				case 'writable':
					$fileInfo['writable'] = is_writable($file);
					break;
				case 'executable':
					$fileInfo['executable'] = is_executable($file);
					break;
				case 'fileperms':
					$fileInfo['fileperms'] = fileperms($file);
					break;
			}
		}

		return $fileInfo;
	}
}