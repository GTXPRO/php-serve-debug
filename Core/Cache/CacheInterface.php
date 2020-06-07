<?php
namespace QTCS\Cache;

interface CacheInterface
{
	public function initialize();
	public function get(string $key);
	public function save(string $key, $value, int $ttl = 60);
	public function delete(string $key);
	public function increment(string $key, int $offset = 1);
	public function decrement(string $key, int $offset = 1);
	public function clean();
	public function getCacheInfo();
	public function getMetaData(string $key);
	public function isSupported(): bool;
}