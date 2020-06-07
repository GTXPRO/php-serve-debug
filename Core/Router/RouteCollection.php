<?php
namespace QTCS\Router;

use QTCS\Autoloader\FileLocator;

class RouteCollection implements RouteCollectionInterface {
	protected $defaultNamespace = '\\';
	protected $defaultController = 'Home';
	protected $defaultMethod = 'index';
	protected $defaultPlaceholder = 'any';
	protected $translateURIDashes = false;
	protected $autoRoute = true;
	protected $override404;
	protected $placeholders = [
		'any'      => '.*',
		'segment'  => '[^/]+',
		'alphanum' => '[a-zA-Z0-9]+',
		'num'      => '[0-9]+',
		'alpha'    => '[a-zA-Z]+',
		'hash'     => '[^/]+',
	];
	protected $routes = [
		'*'       => [],
		'options' => [],
		'get'     => [],
		'head'    => [],
		'post'    => [],
		'put'     => [],
		'delete'  => [],
		'trace'   => [],
		'connect' => [],
		'cli'     => [],
	];
	protected $routesOptions = [];
	protected $HTTPVerb;
	protected $defaultHTTPMethods = [
		'options',
		'get',
		'head',
		'post',
		'put',
		'delete',
		'trace',
		'connect',
		'cli',
	];
	protected $group;
	protected $currentSubdomain;
	protected $currentOptions;
	protected $didDiscover = false;
	protected $fileLocator;
	protected $moduleConfig;

	public function __construct(FileLocator $locator, $moduleConfig)
	{
		$this->fileLocator  = $locator;
		$this->moduleConfig = $moduleConfig;
	}

	public function addPlaceholder($placeholder, string $pattern = null): RouteCollectionInterface
	{
		if (! is_array($placeholder))
		{
			$placeholder = [$placeholder => $pattern];
		}

		$this->placeholders = array_merge($this->placeholders, $placeholder);

		return $this;
	}

	public function setDefaultNamespace(string $value): RouteCollectionInterface
	{
		$this->defaultNamespace = filter_var($value, FILTER_SANITIZE_STRING);
		$this->defaultNamespace = rtrim($this->defaultNamespace, '\\') . '\\';

		return $this;
	}

	public function setDefaultController(string $value): RouteCollectionInterface
	{
		$this->defaultController = filter_var($value, FILTER_SANITIZE_STRING);

		return $this;
	}

	public function setDefaultMethod(string $value): RouteCollectionInterface
	{
		$this->defaultMethod = filter_var($value, FILTER_SANITIZE_STRING);

		return $this;
	}

	public function setTranslateURIDashes(bool $value): RouteCollectionInterface
	{
		$this->translateURIDashes = $value;

		return $this;
	}

	public function setAutoRoute(bool $value): RouteCollectionInterface
	{
		$this->autoRoute = $value;

		return $this;
	}

	public function set404Override($callable = null): RouteCollectionInterface
	{
		$this->override404 = $callable;

		return $this;
	}

	public function get404Override()
	{
		return $this->override404;
	}

	protected function discoverRoutes() {}

	public function setDefaultConstraint(string $placeholder): RouteCollectionInterface
	{
		if (array_key_exists($placeholder, $this->placeholders))
		{
			$this->defaultPlaceholder = $placeholder;
		}

		return $this;
	}

	public function getDefaultController(): string
	{
		return $this->defaultController;
	}

	public function getDefaultMethod(): string
	{
		return $this->defaultMethod;
	}

	public function getDefaultNamespace(): string
	{
		return $this->defaultNamespace;
	}

	public function shouldTranslateURIDashes(): bool
	{
		return $this->translateURIDashes;
	}

	public function shouldAutoRoute(): bool
	{
		return $this->autoRoute;
	}

	public function getRoutes($verb = null): array
	{
		if (empty($verb))
		{
			$verb = $this->getHTTPVerb();
		}

		$this->discoverRoutes();

		$routes = [];

		if (isset($this->routes[$verb]))
		{
			// Keep current verb's routes at the beginning so they're matched
			// before any of the generic, "add" routes.
			if (isset($this->routes['*']))
			{
				$extraRules = array_diff_key($this->routes['*'], $this->routes[$verb]);
				$collection = array_merge($this->routes[$verb], $extraRules);
			}
			foreach ($collection as $r)
			{
				$key          = key($r['route']);
				$routes[$key] = $r['route'][$key];
			}
		}

		return $routes;
	}

	public function getRoutesOptions(string $from = null): array
	{
		return $from ? $this->routesOptions[$from] ?? [] : $this->routesOptions;
	}

	public function getHTTPVerb(): string
	{
		return $this->HTTPVerb;
	}

	public function setHTTPVerb(string $verb)
	{
		$this->HTTPVerb = $verb;

		return $this;
	}

	public function map(array $routes = [], array $options = null): RouteCollectionInterface
	{
		foreach ($routes as $from => $to)
		{
			$this->add($from, $to, $options);
		}

		return $this;
	}

	public function add(string $from, $to, array $options = null): RouteCollectionInterface
	{
		$this->create('*', $from, $to, $options);

		return $this;
	}

	public function addRedirect(string $from, string $to, int $status = 302)
	{
		// Use the named route's pattern if this is a named route.
		if (array_key_exists($to, $this->routes['*']))
		{
			$to = $this->routes['*'][$to]['route'];
		}
		else if (array_key_exists($to, $this->routes['get']))
		{
			$to = $this->routes['get'][$to]['route'];
		}

		$this->create('*', $from, $to, ['redirect' => $status]);

		return $this;
	}

	public function isRedirect(string $from): bool
	{
		foreach ($this->routes['*'] as $name => $route)
		{
			// Named route?
			if ($name === $from || key($route['route']) === $from)
			{
				return isset($route['redirect']) && is_numeric($route['redirect']);
			}
		}

		return false;
	}

	public function getRedirectCode(string $from): int
	{
		foreach ($this->routes['*'] as $name => $route)
		{
			// Named route?
			if ($name === $from || key($route['route']) === $from)
			{
				return $route['redirect'] ?? 0;
			}
		}

		return 0;
	}

	public function group(string $name, ...$params)
	{
		$oldGroup   = $this->group;
		$oldOptions = $this->currentOptions;

		// To register a route, we'll set a flag so that our router
		// so it will see the group name.
		$this->group = ltrim($oldGroup . '/' . $name, '/');

		$callback = array_pop($params);

		if ($params && is_array($params[0]))
		{
			$this->currentOptions = array_shift($params);
		}

		if (is_callable($callback))
		{
			$callback($this);
		}

		$this->group          = $oldGroup;
		$this->currentOptions = $oldOptions;
	}

	public function resource(string $name, array $options = null): RouteCollectionInterface
	{
		// In order to allow customization of the route the
		// resources are sent to, we need to have a new name
		// to store the values in.
		$new_name = ucfirst($name);

		// If a new controller is specified, then we replace the
		// $name value with the name of the new controller.
		if (isset($options['controller']))
		{
			$new_name = ucfirst(filter_var($options['controller'], FILTER_SANITIZE_STRING));
		}

		// In order to allow customization of allowed id values
		// we need someplace to store them.
		$id = $this->placeholders[$this->defaultPlaceholder] ?? '(:segment)';

		if (isset($options['placeholder']))
		{
			$id = $options['placeholder'];
		}

		// Make sure we capture back-references
		$id = '(' . trim($id, '()') . ')';

		$methods = isset($options['only']) ? is_string($options['only']) ? explode(',', $options['only']) : $options['only'] : ['index', 'show', 'create', 'update', 'delete', 'new', 'edit'];

		if (isset($options['except']))
		{
			$options['except'] = is_array($options['except']) ? $options['except'] : explode(',', $options['except']);
			$c                 = count($methods);
			for ($i = 0; $i < $c; $i ++)
			{
				if (in_array($methods[$i], $options['except']))
				{
					unset($methods[$i]);
				}
			}
		}

		if (in_array('index', $methods))
		{
			$this->get($name, $new_name . '::index', $options);
		}
		if (in_array('new', $methods))
		{
			$this->get($name . '/new', $new_name . '::new', $options);
		}
		if (in_array('edit', $methods))
		{
			$this->get($name . '/' . $id . '/edit', $new_name . '::edit/$1', $options);
		}
		if (in_array('show', $methods))
		{
			$this->get($name . '/' . $id, $new_name . '::show/$1', $options);
		}
		if (in_array('create', $methods))
		{
			$this->post($name, $new_name . '::create', $options);
		}
		if (in_array('update', $methods))
		{
			$this->put($name . '/' . $id, $new_name . '::update/$1', $options);
			$this->patch($name . '/' . $id, $new_name . '::update/$1', $options);
		}
		if (in_array('delete', $methods))
		{
			$this->delete($name . '/' . $id, $new_name . '::delete/$1', $options);
		}

		// Web Safe? delete needs checking before update because of method name
		if (isset($options['websafe']))
		{
			if (in_array('delete', $methods))
			{
				$this->post($name . '/' . $id . '/delete', $new_name . '::delete/$1', $options);
			}
			if (in_array('update', $methods))
			{
				$this->post($name . '/' . $id, $new_name . '::update/$1', $options);
			}
		}

		return $this;
	}

	public function presenter(string $name, array $options = null): RouteCollectionInterface
	{
		// In order to allow customization of the route the
		// resources are sent to, we need to have a new name
		// to store the values in.
		$newName = ucfirst($name);

		// If a new controller is specified, then we replace the
		// $name value with the name of the new controller.
		if (isset($options['controller']))
		{
			$newName = ucfirst(filter_var($options['controller'], FILTER_SANITIZE_STRING));
		}

		// In order to allow customization of allowed id values
		// we need someplace to store them.
		$id = $this->placeholders[$this->defaultPlaceholder] ?? '(:segment)';

		if (isset($options['placeholder']))
		{
			$id = $options['placeholder'];
		}

		// Make sure we capture back-references
		$id = '(' . trim($id, '()') . ')';

		$methods = isset($options['only']) ? is_string($options['only']) ? explode(',', $options['only']) : $options['only'] : ['index', 'show', 'new', 'create', 'edit', 'update', 'remove', 'delete'];

		if (isset($options['except']))
		{
			$options['except'] = is_array($options['except']) ? $options['except'] : explode(',', $options['except']);
			$c                 = count($methods);
			for ($i = 0; $i < $c; $i ++)
			{
				if (in_array($methods[$i], $options['except']))
				{
					unset($methods[$i]);
				}
			}
		}

		if (in_array('index', $methods))
		{
			$this->get($name, $newName . '::index', $options);
		}
		if (in_array('show', $methods))
		{
			$this->get($name . '/show/' . $id, $newName . '::show/$1', $options);
		}
		if (in_array('new', $methods))
		{
			$this->get($name . '/new', $newName . '::new', $options);
		}
		if (in_array('create', $methods))
		{
			$this->post($name . '/create', $newName . '::create', $options);
		}
		if (in_array('edit', $methods))
		{
			$this->get($name . '/edit/' . $id, $newName . '::edit/$1', $options);
		}
		if (in_array('update', $methods))
		{
			$this->post($name . '/update/' . $id, $newName . '::update/$1', $options);
		}
		if (in_array('remove', $methods))
		{
			$this->get($name . '/remove/' . $id, $newName . '::remove/$1', $options);
		}
		if (in_array('delete', $methods))
		{
			$this->post($name . '/delete/' . $id, $newName . '::delete/$1', $options);
		}
		if (in_array('show', $methods))
		{
			$this->get($name . '/' . $id, $newName . '::show/$1', $options);
		}
		if (in_array('create', $methods))
		{
			$this->post($name, $newName . '::create', $options);
		}

		return $this;
	}

	public function match(array $verbs = [], string $from, $to, array $options = null): RouteCollectionInterface
	{
		foreach ($verbs as $verb)
		{
			$verb = strtolower($verb);

			$this->{$verb}($from, $to, $options);
		}

		return $this;
	}

	public function get(string $from, $to, array $options = null): RouteCollectionInterface
	{
		$this->create('get', $from, $to, $options);

		return $this;
	}

	public function post(string $from, $to, array $options = null): RouteCollectionInterface
	{
		$this->create('post', $from, $to, $options);

		return $this;
	}

	public function put(string $from, $to, array $options = null): RouteCollectionInterface
	{
		$this->create('put', $from, $to, $options);

		return $this;
	}

	public function delete(string $from, $to, array $options = null): RouteCollectionInterface
	{
		$this->create('delete', $from, $to, $options);

		return $this;
	}

	public function head(string $from, $to, array $options = null): RouteCollectionInterface
	{
		$this->create('head', $from, $to, $options);

		return $this;
	}

	public function patch(string $from, $to, array $options = null): RouteCollectionInterface
	{
		$this->create('patch', $from, $to, $options);

		return $this;
	}

	public function options(string $from, $to, array $options = null): RouteCollectionInterface
	{
		$this->create('options', $from, $to, $options);

		return $this;
	}

	public function cli(string $from, $to, array $options = null): RouteCollectionInterface
	{
		$this->create('cli', $from, $to, $options);

		return $this;
	}

	public function environment(string $env, \Closure $callback): RouteCollectionInterface
	{
		if (ENVIRONMENT === $env)
		{
			$callback($this);
		}

		return $this;
	}

	public function reverseRoute(string $search, ...$params)
	{
		// Named routes get higher priority.
		foreach ($this->routes as $collection)
		{
			if (array_key_exists($search, $collection))
			{
				$route = $this->fillRouteParams(key($collection[$search]['route']), $params);
				return $this->localizeRoute($route);
			}
		}

		foreach ($this->routes as $collection)
		{
			foreach ($collection as $route)
			{
				$from = key($route['route']);
				$to   = $route['route'][$from];

				// ignore closures
				if (! is_string($to))
				{
					continue;
				}

				$to     = ltrim($to, '\\');
				$search = ltrim($search, '\\');

				if (strpos($to, $search) !== 0)
				{
					continue;
				}

				if (substr_count($to, '$') !== count($params))
				{
					continue;
				}

				$route = $this->fillRouteParams($from, $params);
				return $this->localizeRoute($route);
			}
		}

		return false;
	}

	protected function localizeRoute(string $route) :string
	{
		return strtr($route, ['{locale}' => 'en']);
	}

	public function isFiltered(string $search): bool
	{
		return isset($this->routesOptions[$search]['filter']);
	}

	public function getFilterForRoute(string $search): string
	{
		if (! $this->isFiltered($search))
		{
			return '';
		}

		return $this->routesOptions[$search]['filter'];
	}

	protected function fillRouteParams(string $from, array $params = null): string
	{
		// Find all of our back-references in the original route
		preg_match_all('/\(([^)]+)\)/', $from, $matches);

		if (empty($matches[0]))
		{
			return '/' . ltrim($from, '/');
		}

		// Build our resulting string, inserting the $params in
		// the appropriate places.
		foreach ($matches[0] as $index => $pattern)
		{
			// Ensure that the param we're inserting matches
			// the expected param type.
			$pos = strpos($from, $pattern);

			if (preg_match("|{$pattern}|", $params[$index]))
			{
				$from = substr_replace($from, $params[$index], $pos, strlen($pattern));
			}
			else
			{
				throw new \RuntimeException("A parameter does not match the expected type.");
			}
		}

		return '/' . ltrim($from, '/');
	}

	protected function create(string $verb, string $from, $to, array $options = null)
	{
		$overwrite = false;
		$prefix    = is_null($this->group) ? '' : $this->group . '/';

		$from = filter_var($prefix . $from, FILTER_SANITIZE_STRING);

		if ($from !== '/')
		{
			$from = trim($from, '/');
		}

		$options = array_merge((array) $this->currentOptions, (array) $options);

		// Hostname limiting?
		if (! empty($options['hostname']))
		{
			// @todo determine if there's a way to whitelist hosts?
			if (isset($_SERVER['HTTP_HOST']) && strtolower($_SERVER['HTTP_HOST']) !== strtolower($options['hostname']))
			{
				return;
			}

			$overwrite = true;
		}

		// Limiting to subdomains?
		else if (! empty($options['subdomain']))
		{
			if (! $this->checkSubdomains($options['subdomain']))
			{
				return;
			}

			$overwrite = true;
		}

		if (isset($options['offset']) && is_string($to))
		{
			$to = preg_replace('/(\$\d+)/', '$X', $to);

			for ($i = (int) $options['offset'] + 1; $i < (int) $options['offset'] + 7; $i ++)
			{
				$to = preg_replace_callback(
						'/\$X/', function ($m) use ($i) {
							return '$' . $i;
						}, $to, 1
				);
			}
		}

		foreach ($this->placeholders as $tag => $pattern)
		{
			$from = str_ireplace(':' . $tag, $pattern, $from);
		}

		if (is_string($to) && (strpos($to, '\\') === false || strpos($to, '\\') > 0))
		{
			$namespace = $options['namespace'] ?? $this->defaultNamespace;
			$to        = trim($namespace, '\\') . '\\' . $to;
		}

		if (is_string($to))
		{
			$to = '\\' . ltrim($to, '\\');
		}

		$name = $options['as'] ?? $from;

		if (isset($this->routes[$verb][$name]) && ! $overwrite)
		{
			return;
		}

		$this->routes[$verb][$name] = [
			'route' => [$from => $to],
		];

		$this->routesOptions[$from] = $options;

		// Is this a redirect?
		if (isset($options['redirect']) && is_numeric($options['redirect']))
		{
			$this->routes['*'][$name]['redirect'] = $options['redirect'];
		}
	}

	private function checkSubdomains($subdomains): bool
	{
		// CLI calls can't be on subdomain.
		if (! isset($_SERVER['HTTP_HOST']))
		{
			return false;
		}

		if (is_null($this->currentSubdomain))
		{
			$this->currentSubdomain = $this->determineCurrentSubdomain();
		}

		if (! is_array($subdomains))
		{
			$subdomains = [$subdomains];
		}

		// Routes can be limited to any sub-domain. In that case, though,
		// it does require a sub-domain to be present.
		if (! empty($this->currentSubdomain) && in_array('*', $subdomains))
		{
			return true;
		}

		foreach ($subdomains as $subdomain)
		{
			if ($subdomain === $this->currentSubdomain)
			{
				return true;
			}
		}

		return false;
	}

	private function determineCurrentSubdomain()
	{
		$url = $_SERVER['HTTP_HOST'];
		if (strpos($url, 'http') !== 0)
		{
			$url = 'http://' . $url;
		}

		$parsedUrl = parse_url($url);

		$host = explode('.', $parsedUrl['host']);

		if ($host[0] === 'www')
		{
			unset($host[0]);
		}

		unset($host[count($host)]);

		if (end($host) === 'co')
		{
			$host = array_slice($host, 0, -1);
		}

		if (count($host) === 1)
		{
			return false;
		}

		return array_shift($host);
	}

	public function resetRoutes()
	{
		$this->routes = ['*' => []];
		foreach ($this->defaultHTTPMethods as $verb)
		{
			$this->routes[$verb] = [];
		}
	}
}