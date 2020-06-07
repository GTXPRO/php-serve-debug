<?php

require_once __DIR__ . '/../vendor/autoload.php';
$realPath = __DIR__ . '/..';
if (! defined('CORE_PATH'))
{
	define('CORE_PATH', realpath($realPath. '/Core') . DIRECTORY_SEPARATOR);
}

if (! defined('APP_PATH'))
{
	define('APP_PATH', realpath($realPath. '/app') . DIRECTORY_SEPARATOR);
}

if (! defined('ROOT_PATH'))
{
	define('ROOT_PATH', realpath(APP_PATH . '../') . DIRECTORY_SEPARATOR);
}

if (! defined('COMPOSER_PATH'))
{
	define('COMPOSER_PATH', ROOT_PATH . 'vendor/autoload.php');
}

if (! defined('WRITE_PATH'))
{
	define('WRITE_PATH', realpath(__DIR__. '/../../storage') . DIRECTORY_SEPARATOR);
}

if (! defined('DEBUG')) {
	define('DEBUG', 1);
}

require_once __DIR__. '/Common.php';

// require_once CORE_PATH . 'Config/AutoloadConfig.php';
if (! class_exists(Config\Autoload::class, false))
{
	// require_once ROOT_PATH . 'config/Autoload.php';
	// require_once ROOT_PATH . 'config/Modules.php';
}

if (! class_exists(Config\App::class, false)) {
	// require_once ROOT_PATH . 'config/App.php';
}

// require_once CORE_PATH . 'Autoloader/Autoloader.php';
// require_once CORE_PATH. '/Services/BaseServices.php';
// require_once CORE_PATH. '/Services/Services.php';

$loader = QTCS\Services\Services::autoloader();
$loader->initialize(new Config\Autoload(), new Config\Modules());
$loader->register();

$config = new Config\App();
$app = new \QTCS\Loader($config);

$app->initialize();

return $app;