<?php
namespace Config;

use QTCS\Config\BaseConfig;

class Cache extends BaseConfig {
	public $handler = 'file';
	public $backupHandler = 'dummy';
	public $storePath = WRITE_PATH . 'cache/';
	public $cacheQueryString = false;
	public $prefix = '';
	public $memcached = [
		'host'   => '127.0.0.1',
		'port'   => 11211,
		'weight' => 1,
		'raw'    => false,
	];
	public $redis = [
		'host'     => '127.0.0.1',
		'password' => null,
		'port'     => 6379,
		'timeout'  => 0,
		'database' => 0,
	];
	public $validHandlers = [
		'dummy'     => \QTCS\Cache\Handlers\DummyHandler::class,
		'file'      => \QTCS\Cache\Handlers\FileHandler::class,
		// 'memcached' => \CodeIgniter\Cache\Handlers\MemcachedHandler::class,
		// 'predis'    => \CodeIgniter\Cache\Handlers\PredisHandler::class,
		// 'redis'     => \CodeIgniter\Cache\Handlers\RedisHandler::class,
		// 'wincache'  => \CodeIgniter\Cache\Handlers\WincacheHandler::class,
	];
}