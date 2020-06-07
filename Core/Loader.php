<?php

namespace QTCS;

use QTCS\Http\CLIRequest;
use QTCS\Http\Response;
use QTCS\Http\Request;
use QTCS\Http\URI;
use QTCS\Router\RouteCollectionInterface;
use QTCS\Services\Services;

class Loader
{
	const VERSION = '1.0';

	protected $config;
	protected $response;
	protected $request;
	protected $path;
	protected $router;
	protected $controller;
	protected $method;
	protected $output;
	protected static $cacheTTL = 0;
	protected $useSafeOutput = false;

	public function __construct($config)
	{
		$this->config = $config;
	}

	public function initialize()
	{
		date_default_timezone_set($this->config->appTimezone ?? 'Asia/Ho_Chi_Minh');

		$this->detectEnvironment();

		echo "Loader initialize \n";
	}

	public function run(RouteCollectionInterface $routes = null, bool $returnResponse = false)
	{
		$this->getRequestObject();
		$this->getResponseObject();
		$this->forceSecureAccess();
		$this->spoofRequestMethod();

		try {
			return $this->handleRequest($routes, $returnResponse);
		} catch (\RuntimeException $e) {
			echo "Run loader failed !!!". $e->getMessage();
		}
	}

	protected function handleRequest(RouteCollectionInterface $routes = null, bool $returnResponse = false)
	{
		$routeFilter = $this->tryToRouteIt($routes);

		$filters = Services::filters();

		if (! is_null($routeFilter))
		{
			$filters->enableFilter($routeFilter, 'before');
			$filters->enableFilter($routeFilter, 'after');
		}

		$uri = $this->request instanceof CLIRequest ? $this->request->getPath() : $this->request->uri->getPath();

		if (!defined('QTCS')) {
			echo "Run with browser";
		}

		$returned = $this->startController();
		
		if (! is_callable($this->controller))
		{
			$controller = $this->createController();

			$returned = $this->runController($controller);
		}

		if (! defined('QTCS')) {
			$filters->setResponse($this->response);
			$response = $filters->run($uri, 'after');
		} else {
			$response = $this->response;

			if (is_numeric($returned) || $returned === false)
			{
				$response->setStatusCode(400);
			}
		}

		if ($response instanceof Response)
		{
			$this->response = $response;
		}

		$this->storePreviousURL((string)current_url(true));

		unset($uri);

		if (! $returnResponse)
		{
			$this->sendResponse();
		}

		return $this->response;
	}

	protected function detectEnvironment()
	{
		if (!defined('ENVIRONMENT')) {
			if (getenv('ENVIRONMENT') !== false) {
				define('ENVIRONMENT', 'development');
			} else {
				define('ENVIRONMENT', $_SERVER['ENVIRONMENT'] ?? 'production');
			}
		}
		echo "Run detect environment \n";
	}

	protected function startController()
	{
		if (is_object($this->controller) && (get_class($this->controller) === 'Closure'))
		{
			$controller = $this->controller;
			return $controller(...$this->router->params());
		}

		if (empty($this->controller))
		{
			throw new \RuntimeException("No Controller specified.");
		}

		if (! class_exists($this->controller, true) || $this->method[0] === '_')
		{
			throw new \RuntimeException("Controller or its method is not found: {$this->controller}::{$this->method}");
		}
		else if (! method_exists($this->controller, '_remap') &&
				! is_callable([$this->controller, $this->method], false)
		)
		{
			throw new \RuntimeException("Controller method is not found: {$this->method}");
		}
	}

	protected function getRequestObject()
	{
		if ($this->request instanceof Request) {
			return;
		}

		if (is_cli() && ENVIRONMENT !== 'testing')
		{
			$this->request = Services::clirequest($this->config);
		} else {
			$this->request = Services::request($this->config);
			// guess at protocol if needed
			$this->request->setProtocolVersion($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
		}
	}

	protected function getResponseObject()
	{
		$this->response = Services::response($this->config);

		if (!is_cli() || ENVIRONMENT === 'testing') {
			$this->response->setProtocolVersion($this->request->getProtocolVersion());
		}

		// Assume success until proven otherwise.
		$this->response->setStatusCode(200);
	}

	protected function forceSecureAccess($duration = 31536000)
	{
		if (
			isset($this->config->forceGlobalSecureRequests) &&
			$this->config->forceGlobalSecureRequests !== true
		) {
			return;
		}

		force_https($duration, $this->request, $this->response);
	}

	public function setPath(string $path)
	{
		$this->path = $path;

		return $this;
	}

	public function spoofRequestMethod()
	{
		// Only works with POSTED forms
		if ($this->request->getMethod() !== 'post') {
			return;
		}

		$method = $this->request->getPost('_method');

		if (empty($method)) {
			return;
		}

		$this->request = $this->request->setMethod($method);
	}

	protected function tryToRouteIt(RouteCollectionInterface $routes = null)
	{
		if (empty($routes) || ! $routes instanceof RouteCollectionInterface) {
			require ROOT_PATH . 'Routes/Web.php';
		}

		$this->router = Services::router($routes, $this->request);
		$path = $this->determinePath();

		ob_start();

		$this->controller = $this->router->handle($path);
		$this->method     = $this->router->methodName();

		if ($this->router->hasLocale())
		{
			$this->request->setLocale($this->router->getLocale());
		}

		return $this->router->getFilter();
	}

	protected function createController()
	{
		$class = new $this->controller();
		$class->initController($this->request, $this->response, Services::logger());

		return $class;
	}

	protected function runController($class)
	{
		// If this is a console request then use the input segments as parameters
		$params = defined('QTCS') ? $this->request->getSegments() : $this->router->params();

		if (method_exists($class, '_remap'))
		{
			$output = $class->_remap($this->method, ...$params);
		}
		else
		{
			$output = $class->{$this->method}(...$params);
		}

		return $output;
	}

	public function storePreviousURL($uri)
	{
		// Ignore CLI requests
		if (is_cli())
		{
			return;
		}
		// Ignore AJAX requests
		if (method_exists($this->request, 'isAJAX') && $this->request->isAJAX())
		{
			return;
		}

		// This is mainly needed during testing...
		if (is_string($uri))
		{
			$uri = new URI($uri);
		}

		if (isset($_SESSION))
		{
			$_SESSION['_ci_previous_url'] = (string) $uri;
		}
	}

	protected function sendResponse()
	{
		$this->response->pretend($this->useSafeOutput)->send();
	}

	public function useSafeOutput(bool $safe = true)
	{
		$this->useSafeOutput = $safe;

		return $this;
	}

	protected function determinePath()
	{
		if (! empty($this->path))
		{
			return $this->path;
		}

		return (is_cli() && ! (ENVIRONMENT === 'testing')) ? $this->request->getPath() : $this->request->uri->getPath();
	}
}
