<?php
namespace QTCS\Config;

class AutoloadConfig {
	public $psr4 = [];
	public $classmap = [];
	public function __construct()
	{
		$this->psr4 = [
			'QTCS' => realpath(CORE_PATH),
		];
		$this->classmap = [];
	}
}