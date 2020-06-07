<?php
namespace Config;

use QTCS\Config\AutoloadConfig;

class Autoload extends AutoloadConfig {
	public $psr4 = [];
	public $classmap = [];

	public function __construct()
	{
		parent::__construct();

		$psr4 = [];
		$classmap = [];

		$this->psr4     = array_merge($this->psr4, $psr4);
		$this->classmap = array_merge($this->classmap, $classmap);
		unset($psr4, $classmap);
	}
}