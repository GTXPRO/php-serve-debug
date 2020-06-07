<?php
namespace QTCS\CLI;

class CLIException extends \RuntimeException {
	public static function forInvalidColor(string $type, string $color)
	{
		return "Invalid {$type} color: {$color}.";
	}
}