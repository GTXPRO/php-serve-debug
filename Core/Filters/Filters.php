<?php
namespace QTCS\Filters;

use QTCS\Http\RequestInterface;
use QTCS\Http\ResponseInterface;

class Filters {
	protected $filters = [
		'before' => [],
		'after'  => [],
	];
	protected $config;
	protected $request;
	protected $response;
	protected $initialized = false;
	protected $arguments = [];

	public function __construct($config, RequestInterface $request, ResponseInterface $response)
	{
		$this->config  = $config;
		$this->request = & $request;
		$this->setResponse($response);
	}

	public function setResponse(ResponseInterface $response)
	{
		$this->response = & $response;
	}

	public function run(string $uri, string $position = 'before')
	{
		$this->initialize(strtolower($uri));

		foreach ($this->filters[$position] as $alias => $rules)
		{
			if (is_numeric($alias) && is_string($rules))
			{
				$alias = $rules;
			}

			if (! array_key_exists($alias, $this->config->aliases))
			{
				throw new \RuntimeException("{$alias} filter must have a matching alias defined.");
			}

			if (is_array($this->config->aliases[$alias]))
			{
				$classNames = $this->config->aliases[$alias];
			}
			else
			{
				$classNames = [$this->config->aliases[$alias]];
			}

			foreach ($classNames as $className)
			{
				$class = new $className();

				if (! $class instanceof FilterInterface)
				{
					$nameClass = get_class($class);
					throw new \RuntimeException("{$nameClass} must implement QTCS\Filters\FilterInterface.");
				}

				if ($position === 'before')
				{
					$result = $class->before($this->request, $this->arguments[$alias] ?? null);

					if ($result instanceof RequestInterface)
					{
						$this->request = $result;
						continue;
					}

					// If the response object was sent back,
					// then send it and quit.
					if ($result instanceof ResponseInterface)
					{
						// short circuit - bypass any other filters
						return $result;
					}

					// Ignore an empty result
					if (empty($result))
					{
						continue;
					}

					return $result;
				}
				elseif ($position === 'after')
				{
					$result = $class->after($this->request, $this->response);

					if ($result instanceof ResponseInterface)
					{
						$this->response = $result;
						continue;
					}
				}
			}
		}

		return $position === 'before' ? $this->request : $this->response;
	}

	public function initialize(string $uri = null)
	{
		if ($this->initialized === true)
		{
			return $this;
		}

		$this->processGlobals($uri);
		$this->processMethods();
		$this->processFilters($uri);

		$this->initialized = true;

		return $this;
	}

	public function getFilters(): array
	{
		return $this->filters;
	}

	public function addFilter(string $class, string $alias = null, string $when = 'before', string $section = 'globals')
	{
		$alias = $alias ?? md5($class);

		if (! isset($this->config->{$section}))
		{
			$this->config->{$section} = [];
		}

		if (! isset($this->config->{$section}[$when]))
		{
			$this->config->{$section}[$when] = [];
		}

		$this->config->aliases[$alias] = $class;

		$this->config->{$section}[$when][] = $alias;

		return $this;
	}

	public function enableFilter(string $name, string $when = 'before')
	{
		// Get parameters and clean name
		if (strpos($name, ':') !== false)
		{
			list($name, $params) = explode(':', $name);

			$params = explode(',', $params);
			array_walk($params, function (&$item) {
				$item = trim($item);
			});

			$this->arguments[$name] = $params;
		}

		if (! array_key_exists($name, $this->config->aliases))
		{
			throw new \RuntimeException("{$name} filter must have a matching alias defined.");
		}

		if (! isset($this->filters[$when][$name]))
		{
			$this->filters[$when][] = $name;
		}

		return $this;
	}

	public function getArguments(string $key = null)
	{
		return is_null($key) ? $this->arguments : $this->arguments[$key];
	}

	protected function processGlobals(string $uri = null)
	{
		if (! isset($this->config->globals) || ! is_array($this->config->globals))
		{
			return;
		}

		$uri = strtolower(trim($uri, '/ '));

		// Add any global filters, unless they are excluded for this URI
		$sets = [
			'before',
			'after',
		];
		foreach ($sets as $set)
		{
			if (isset($this->config->globals[$set]))
			{
				// look at each alias in the group
				foreach ($this->config->globals[$set] as $alias => $rules)
				{
					$keep = true;
					if (is_array($rules))
					{
						// see if it should be excluded
						if (isset($rules['except']))
						{
							// grab the exclusion rules
							$check = $rules['except'];
							if ($this->pathApplies($uri, $check))
							{
								$keep = false;
							}
						}
					}
					else
					{
						$alias = $rules; // simple name of filter to apply
					}
					if ($keep)
					{
						$this->filters[$set][] = $alias;
					}
				}
			}
		}
	}

	protected function processMethods()
	{
		if (! isset($this->config->methods) || ! is_array($this->config->methods))
		{
			return;
		}

		// Request method won't be set for CLI-based requests
		$method = strtolower($_SERVER['REQUEST_METHOD'] ?? 'cli');

		if (array_key_exists($method, $this->config->methods))
		{
			$this->filters['before'] = array_merge($this->filters['before'], $this->config->methods[$method]);
			return;
		}
	}

	protected function processFilters(string $uri = null)
	{
		if (! isset($this->config->filters) || ! $this->config->filters)
		{
			return;
		}

		$uri = strtolower(trim($uri, '/ '));

		// Add any filters that apply to this URI
		foreach ($this->config->filters as $alias => $settings)
		{
			// Look for inclusion rules
			if (isset($settings['before']))
			{
				$path = $settings['before'];
				if ($this->pathApplies($uri, $path))
				{
					$this->filters['before'][] = $alias;
				}
			}
			if (isset($settings['after']))
			{
				$path = $settings['after'];
				if ($this->pathApplies($uri, $path))
				{
					$this->filters['after'][] = $alias;
				}
			}
		}
	}

	private function pathApplies(string $uri, $paths)
	{
		// empty path matches all
		if (empty($paths))
		{
			return true;
		}

		// make sure the paths are iterable
		if (is_string($paths))
		{
			$paths = [$paths];
		}

		// treat each paths as pseudo-regex
		foreach ($paths as $path)
		{
			// need to escape path separators
			$path = str_replace('/', '\/', trim($path, '/ '));
			// need to make pseudo wildcard real
			$path = strtolower(str_replace('*', '.*', $path));
			// Does this rule apply here?
			if (preg_match('#^' . $path . '$#', $uri, $match) === 1)
			{
				return true;
			}
		}
		return false;
	}
}