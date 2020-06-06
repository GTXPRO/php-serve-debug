<?php
namespace NGUYEN\CLI;

class CLI {
	protected static $segments = [];

	public static function init() {
		static::$segments = [];
	}

	public static function getURI(): string
	{
		return implode('/', static::$segments);
	}
}