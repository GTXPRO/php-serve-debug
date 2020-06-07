<?php

namespace QTCS\Router;

use QTCS\Http\Request;

class Router implements RouterInterface
{
	protected $collection;
	protected $directory;
	protected $controller;
	protected $method;
	protected $params = [];
	protected $indexPage = 'index.php';
	protected $translateURIDashes = false;
	protected $matchedRoute;
	protected $matchedRouteOptions;
	protected $detectedLocale;
	protected $filterInfo;

	/**
	 * @param RouteCollection $routes
	 */
	public function __construct(RouteCollectionInterface $routes, Request $request = null)
	{
		$this->collection = $routes;

		$this->controller = $this->collection->getDefaultController();
		$this->method     = $this->collection->getDefaultMethod();

		$this->collection->setHTTPVerb($request->getMethod() ?? strtolower($_SERVER['REQUEST_METHOD']));
	}

	public function handle(string $uri = null)
	{
		$this->translateURIDashes = $this->collection->shouldTranslateURIDashes();

		// If we cannot find a URI to match against, then
		// everything runs off of it's default settings.
		if ($uri === null || $uri === '')
		{
			return strpos($this->controller, '\\') === false
				? $this->collection->getDefaultNamespace() . $this->controller
				: $this->controller;
		}

		if ($this->checkRoutes($uri))
		{
			if ($this->collection->isFiltered($this->matchedRoute[0]))
			{
				$this->filterInfo = $this->collection->getFilterForRoute($this->matchedRoute[0]);
			}

			return $this->controller;
		}

		// Still here? Then we can try to match the URI against
		// Controllers/directories, but the application may not
		// want this, like in the case of API's.
		if (! $this->collection->shouldAutoRoute())
		{
			throw new PageNotFoundException("Can't find a route for '{$uri}'.");
		}

		$this->autoRoute($uri);

		return $this->controllerName();
	}

	public function getFilter()
	{
		return $this->filterInfo;
	}

	public function controllerName()
	{
		return $this->translateURIDashes
			? str_replace('-', '_', $this->controller)
			: $this->controller;
	}

	public function methodName(): string
	{
		return $this->translateURIDashes
			? str_replace('-', '_', $this->method)
			: $this->method;
	}

	public function get404Override()
	{
		$route = $this->collection->get404Override();

		if (is_string($route))
		{
			$routeArray = explode('::', $route);

			return [
				$routeArray[0], // Controller
				$routeArray[1] ?? 'index',   // Method
			];
		}

		if (is_callable($route))
		{
			return $route;
		}

		return null;
	}

	public function params(): array
	{
		return $this->params;
	}

	public function directory(): string
	{
		return ! empty($this->directory) ? $this->directory : '';
	}

	public function getMatchedRoute()
	{
		return $this->matchedRoute;
	}

	public function getMatchedRouteOptions()
	{
		return $this->matchedRouteOptions;
	}

	public function setIndexPage($page): self
	{
		$this->indexPage = $page;

		return $this;
	}

	public function setTranslateURIDashes(bool $val = false): self
	{
		$this->translateURIDashes = $val;

		return $this;
	}

	public function hasLocale()
	{
		return (bool) $this->detectedLocale;
	}

	public function getLocale()
	{
		return $this->detectedLocale;
	}

	protected function checkRoutes(string $uri): bool
	{
		$routes = $this->collection->getRoutes($this->collection->getHTTPVerb());

		$uri = $uri === '/'
			? $uri
			: ltrim($uri, '/ ');

		// Don't waste any time
		if (empty($routes))
		{
			return false;
		}

		// Loop through the route array looking for wildcards
		foreach ($routes as $key => $val)
		{
			$key = $key === '/'
				? $key
				: ltrim($key, '/ ');

			// Are we dealing with a locale?
			if (strpos($key, '{locale}') !== false)
			{
				$localeSegment = array_search('{locale}', preg_split('/[\/]*((^[a-zA-Z0-9])|\(([^()]*)\))*[\/]+/m', $key));

				// Replace it with a regex so it
				// will actually match.
				$key = str_replace('{locale}', '[^/]+', $key);
			}

			// Does the RegEx match?
			if (preg_match('#^' . $key . '$#', $uri, $matches))
			{
				// Is this route supposed to redirect to another?
				if ($this->collection->isRedirect($key))
				{
					throw new RedirectException(key($val), $this->collection->getRedirectCode($key));
				}
				// Store our locale so CodeIgniter object can
				// assign it to the Request.
				if (isset($localeSegment))
				{
					// The following may be inefficient, but doesn't upset NetBeans :-/
					$temp                 = (explode('/', $uri));
					$this->detectedLocale = $temp[$localeSegment];
					unset($localeSegment);
				}

				// Are we using Closures? If so, then we need
				// to collect the params into an array
				// so it can be passed to the controller method later.
				if (! is_string($val) && is_callable($val))
				{
					$this->controller = $val;

					// Remove the original string from the matches array
					array_shift($matches);

					$this->params = $matches;

					$this->matchedRoute = [
						$key,
						$val,
					];

					$this->matchedRouteOptions = $this->collection->getRoutesOptions($key);

					return true;
				}
				// Are we using the default method for back-references?

				// Support resource route when function with subdirectory
				// ex: $routes->resource('Admin/Admins');
				if (strpos($val, '$') !== false && strpos($key, '(') !== false && strpos($key, '/') !== false)
				{
					$replacekey = str_replace('/(.*)', '', $key);
					$val        = preg_replace('#^' . $key . '$#', $val, $uri);
					$val        = str_replace($replacekey, str_replace('/', '\\', $replacekey), $val);
				}
				elseif (strpos($val, '$') !== false && strpos($key, '(') !== false)
				{
					$val = preg_replace('#^' . $key . '$#', $val, $uri);
				}
				elseif (strpos($val, '/') !== false)
				{
					[
						$controller,
						$method,
					] = explode( '::', $val );

					// Only replace slashes in the controller, not in the method.
					$controller = str_replace('/', '\\', $controller);

					$val = $controller . '::' . $method;
				}

				$this->setRequest(explode('/', $val));

				$this->matchedRoute = [
					$key,
					$val,
				];

				$this->matchedRouteOptions = $this->collection->getRoutesOptions($key);

				return true;
			}
		}

		return false;
	}

	public function autoRoute(string $uri)
	{
		$segments = explode('/', $uri);

		$segments = $this->validateRequest($segments);

		// If we don't have any segments left - try the default controller;
		// WARNING: Directories get shifted out of the segments array.
		if (empty($segments))
		{
			$this->setDefaultController();
		}
		// If not empty, then the first segment should be the controller
		else
		{
			$this->controller = ucfirst(array_shift($segments));
		}

		// Use the method name if it exists.
		// If it doesn't, no biggie - the default method name
		// has already been set.
		if (! empty($segments))
		{
			$this->method = array_shift($segments);
		}

		if (! empty($segments))
		{
			$this->params = $segments;
		}

		if ($this->collection->getHTTPVerb() !== 'cli')
		{
			$controller  = '\\' . $this->collection->getDefaultNamespace();
			$controller .= $this->directory ? str_replace('/', '\\', $this->directory) : '';
			$controller .= $this->controllerName();
			$controller  = strtolower($controller);
			$methodName  = strtolower($this->methodName());

			foreach ($this->collection->getRoutes('cli') as $route)
			{
				if (is_string($route))
				{
					$route = strtolower($route);
					if (strpos($route, $controller . '::' . $methodName) === 0)
					{
						throw new PageNotFoundException();
					}

					if ($route === $controller)
					{
						throw new PageNotFoundException();
					}
				}
			}
		}

		// Load the file so that it's available for CodeIgniter.
		$file = APP_PATH . 'Controllers/' . $this->directory . $this->controllerName() . '.php';
		if (is_file($file))
		{
			include_once $file;
		}

		// Ensure the controller stores the fully-qualified class name
		// We have to check for a length over 1, since by default it will be '\'
		if (strpos($this->controller, '\\') === false && strlen($this->collection->getDefaultNamespace()) > 1)
		{
			$this->controller = '\\' . ltrim(str_replace('/', '\\', $this->collection->getDefaultNamespace() . $this->directory . $this->controllerName()), '\\');
		}
	}

	protected function validateRequest(array $segments): array
	{
		$segments = array_filter($segments, function ($segment) {
			return ! empty($segment) || ($segment !== '0' || $segment !== 0);
		});
		$segments = array_values($segments);

		$c                  = count($segments);
		$directory_override = isset($this->directory);

		// Loop through our segments and return as soon as a controller
		// is found or when such a directory doesn't exist
		while ($c-- > 0)
		{
			$test = $this->directory . ucfirst($this->translateURIDashes === true ? str_replace('-', '_', $segments[0]) : $segments[0]);

			if (! is_file(APP_PATH . 'Controllers/' . $test . '.php') && $directory_override === false && is_dir(APP_PATH . 'Controllers/' . $this->directory . ucfirst($segments[0])))
			{
				$this->setDirectory(array_shift($segments), true);
				continue;
			}

			return $segments;
		}

		// This means that all segments were actually directories
		return $segments;
	}

	public function setDirectory(string $dir = null, bool $append = false)
	{
		if (empty($dir))
		{
			$this->directory = null;
			return;
		}

		$dir = ucfirst($dir);

		if ($append !== true || empty($this->directory))
		{
			$this->directory = str_replace('.', '', trim($dir, '/')) . '/';
		}
		else
		{
			$this->directory .= str_replace('.', '', trim($dir, '/')) . '/';
		}
	}

	protected function setRequest(array $segments = [])
	{
		// If we don't have any segments - try the default controller;
		if (empty($segments))
		{
			$this->setDefaultController();

			return;
		}

		list($controller, $method) = array_pad(explode('::', $segments[0]), 2, null);

		$this->controller = $controller;

		// $this->method already contains the default method name,
		// so don't overwrite it with emptiness.
		if (! empty($method))
		{
			$this->method = $method;
		}

		array_shift($segments);

		$this->params = $segments;
	}

	protected function setDefaultController()
	{
		if (empty($this->controller))
		{
			throw new \RuntimeException("Unable to determine what should be displayed. A default route has not been specified in the routing file.");
		}

		// Is the method being specified?
		if (sscanf($this->controller, '%[^/]/%s', $class, $this->method) !== 2)
		{
			$this->method = 'index';
		}

		if (! is_file(APP_PATH . 'Controllers/' . $this->directory . ucfirst($class) . '.php'))
		{
			return;
		}

		$this->controller = ucfirst($class);

		log_message('info', 'Used the default controller.');
	}
}

class PageNotFoundException extends \OutOfBoundsException
{
	protected $code = 404;
}

class RedirectException extends \Exception
{
	protected $code = 302;
}