<?php
namespace QTCS\Database;

class BaseConfig {
	public static $registrars = [];
	protected static $didDiscovery = false;
	protected static $moduleConfig;

	public function __construct() {
		
	}
}