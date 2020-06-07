<?php

namespace QTCS;

use Closure;
use Config\Cache;
use QTCS\Http\CLIRequest;
use QTCS\Http\DownloadResponse;
use QTCS\Http\RedirectResponse;
use QTCS\Http\Response;
use QTCS\Http\Request;
use QTCS\Http\ResponseInterface;
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

	}

	public function run(RouteCollectionInterface $routes = null, bool $returnResponse = false)
	{
		$this->getRequestObject();
		$this->getResponseObject();
		$this->forceSecureAccess();
		$this->spoofRequestMethod();

		$cacheConfig = new Cache();

		// Tam bo Cache
		
		// $response    = $this->displayCache($cacheConfig);

		// if ($response instanceof ResponseInterface)
		// {
		// 	if ($returnResponse)
		// 	{
		// 		return $response;
		// 	}

		// 	$this->response->pretend($this->useSafeOutput)->send();
		// 	$this->callExit(0);
		// }

		try {
			return $this->handleRequest($routes, $cacheConfig, $returnResponse);
		} catch (\RuntimeException $e) {
			echo "Run loader failed !!!". $e->getMessage();
		}
	}

	protected function handleRequest(RouteCollectionInterface $routes = null, $cacheConfig, bool $returnResponse = false)
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
			$possibleRedirect = $filters->run($uri, 'before');
			if ($possibleRedirect instanceof RedirectResponse)
			{
				return $possibleRedirect->send();
			}

			if ($possibleRedirect instanceof ResponseInterface)
			{
				return $possibleRedirect->send();
			}
		}

		$returned = $this->startController();
		
		if (! is_callable($this->controller))
		{
			$controller = $this->createController();

			$returned = $this->runController($controller);
		}

		$this->gatherOutput($cacheConfig, $returned);

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
		if ($this->config->forceGlobalSecureRequests !== true) {
			return;
		}

		force_https($duration, $this->request, $this->response);
	}

	public function displayCache($config)
	{
		if ($cachedResponse = cache()->get($this->generateCacheName($config)))
		{
			$cachedResponse = unserialize($cachedResponse);
			if (! is_array($cachedResponse) || ! isset($cachedResponse['output']) || ! isset($cachedResponse['headers']))
			{
				throw new \Exception('Error unserializing page cache');
			}

			$headers = $cachedResponse['headers'];
			$output  = $cachedResponse['output'];

			// Clear all default headers
			foreach ($this->response->getHeaders() as $key => $val)
			{
				$this->response->removeHeader($key);
			}

			// Set cached headers
			foreach ($headers as $name => $value)
			{
				$this->response->setHeader($name, $value);
			}

			$output = $this->displayPerformanceMetrics($output);
			$this->response->setBody($output);

			return $this->response;
		}

		return false;
	}

	public static function cache(int $time)
	{
		static::$cacheTTL = $time;
	}

	public function cachePage(Cache $config)
	{
		$headers = [];
		foreach ($this->response->getHeaders() as $header)
		{
			$headers[$header->getName()] = $header->getValueLine();
		}

		return cache()->save(
						$this->generateCacheName($config), serialize(['headers' => $headers, 'output' => $this->output]), static::$cacheTTL
		);
	}

	public function getPerformanceStats(): array
	{
		return [
			'startTime' => $this->startTime,
			'totalTime' => $this->totalTime,
		];
	}

	protected function generateCacheName($config): string
	{
		if (get_class($this->request) === CLIRequest::class)
		{
			return md5($this->request->getPath());
		}

		$uri = $this->request->uri;

		if ($config->cacheQueryString)
		{
			$name = URI::createURIString(
							$uri->getScheme(), $uri->getAuthority(), $uri->getPath(), $uri->getQuery()
			);
		}
		else
		{
			$name = URI::createURIString(
							$uri->getScheme(), $uri->getAuthority(), $uri->getPath()
			);
		}

		return md5($name);
	}

	public function displayPerformanceMetrics(string $output): string
	{
		$this->totalTime = 0;

		return str_replace('{elapsed_time}', $this->totalTime, $output);
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

	protected function display404errors(\RuntimeException $e)
	{
		// Is there a 404 Override available?
		if ($override = $this->router->get404Override())
		{
			if ($override instanceof Closure)
			{
				echo $override($e->getMessage());
			}
			else if (is_array($override))
			{
				$this->benchmark->start('controller');
				$this->benchmark->start('controller_constructor');

				$this->controller = $override[0];
				$this->method     = $override[1];

				unset($override);

				$controller = $this->createController();
				$this->runController($controller);
			}

			$cacheConfig = new Cache();
			$this->gatherOutput($cacheConfig);
			$this->sendResponse();

			return;
		}

		// Display 404 Errors
		$this->response->setStatusCode($e->getCode());

		if (ENVIRONMENT !== 'testing')
		{
			// @codeCoverageIgnoreStart
			if (ob_get_level() > 0)
			{
				ob_end_flush();
			}
			// @codeCoverageIgnoreEnd
		}
		else
		{
			// When testing, one is for phpunit, another is for test case.
			if (ob_get_level() > 2)
			{
				ob_end_flush();
			}
		}

		throw new \RuntimeException("Page Not Found");
		// throw PageNotFoundException::forPageNotFound(ENVIRONMENT !== 'production' || is_cli() ? $e->getMessage() : '');
	}

	protected function gatherOutput($cacheConfig = null, $returned = null)
	{
		$this->output = ob_get_contents();
		// If buffering is not null.
		// Clean (erase) the output buffer and turn off output buffering
		if (ob_get_length())
		{
			ob_end_clean();
		}

		if ($returned instanceof DownloadResponse)
		{
			$this->response = $returned;
			return;
		}
		// If the controller returned a response object,
		// we need to grab the body from it so it can
		// be added to anything else that might have been
		// echoed already.
		// We also need to save the instance locally
		// so that any status code changes, etc, take place.
		if ($returned instanceof Response)
		{
			$this->response = $returned;
			$returned       = $returned->getBody();
		}

		if (is_string($returned))
		{
			$this->output .= $returned;
		}

		// Cache it without the performance metrics replaced
		// so that we can have live speed updates along the way.
		if (static::$cacheTTL > 0)
		{
			$this->cachePage($cacheConfig);
		}

		$this->output = $this->displayPerformanceMetrics($this->output);

		$this->response->setBody($this->output);
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

	protected function callExit($code)
	{
		exit($code);
	}
}
