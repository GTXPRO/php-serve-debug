<?php
namespace QTCS\Router;

$routes->add('basecontroller(:any)', function () {
	throw new \RuntimeException("Page Not Found");
});

$routes->cli('migrations/(:segment)/(:segment)', '\QTCS\Commands\MigrationsCommand::$1/$2');
$routes->cli('migrations/(:segment)', '\QTCS\Commands\MigrationsCommand::$1');
$routes->cli('migrations', '\QTCS\Commands\MigrationsCommand::index');

// CLI Catchall - uses a _remap to call Commands
$routes->cli('qtcs(:any)', '\QTCS\Command\Command::index/$1');

// Prevent access to initController method
$routes->add('(:any)/initController', function () {
	throw new \RuntimeException("Page Not Found");
});