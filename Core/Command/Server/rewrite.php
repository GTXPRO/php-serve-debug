<?php

if (php_sapi_name() === 'cli')
{
	return;
}
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$fcpath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR;

// Full path
$path = $fcpath . ltrim($uri, '/');

if ($uri !== '/' && (is_file($path) || is_dir($path)))
{
	return false;
}

require_once $fcpath . 'index.php';
