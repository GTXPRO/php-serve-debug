<?php
namespace QTCS\Router;

use QTCS\Http\Request;

interface RouterInterface {
	public function __construct(RouteCollectionInterface $routes, Request $request = null);
	public function handle(string $uri = null);
	public function controllerName();
	public function methodName();
	public function params();
	public function setIndexPage($page);
}