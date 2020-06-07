<?php
namespace Routes;

use QTCS\Services\Services;

$routes = Services::routes();

if (file_exists(CORE_PATH . 'Router/Routes.php'))
{
	require CORE_PATH . 'Router/Routes.php';
}

$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(true);

$routes->get('/', 'Home::index');
$routes->get('/demo', 'Home::demo');