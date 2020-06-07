<?php
namespace QTCS\Cache\Handlers;

use QTCS\Cache\CacheInterface;

class DummyHandler implements CacheInterface
{
	public function initialize()
	{
		// Nothing to see here...
	}

	public function get(string $key)
	{
		return null;
	}

	public function save(string $key, $value, int $ttl = 60)
	{
		return true;
	}

	public function delete(string $key)
	{
		return true;
	}

	public function increment(string $key, int $offset = 1)
	{
		return true;
	}

	public function decrement(string $key, int $offset = 1)
	{
		return true;
	}

	public function clean()
	{
		return true;
	}

	public function getCacheInfo()
	{
		return null;
	}

	public function getMetaData(string $key)
	{
		return null;
	}

	public function isSupported(): bool
	{
		return true;
	}
}