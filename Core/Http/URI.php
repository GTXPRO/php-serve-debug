<?php
namespace QTCS\Http;

class URI {
	const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
	protected $segments = [];
	protected $scheme = 'http';
	protected $user;
	protected $password;
	protected $host;
	protected $port;
	protected $path;
	protected $fragment = '';
	protected $query = [];
	protected $defaultPorts = [
		'http'  => 80,
		'https' => 443,
		'ftp'   => 21,
		'sftp'  => 22,
	];
	protected $showPassword = false;

	public function __construct(string $uri = null)
	{
		if (! is_null($uri))
		{
			$this->setURI($uri);
		}
	}

	public function setURI(string $uri = null)
	{
		if (! is_null($uri))
		{
			$parts = parse_url($uri);

			if ($parts === false)
			{
				throw new \RuntimeException("Unable to parse URI: {$uri}");
			}

			$this->applyParts($parts);
		}

		return $this;
	}

	public function getScheme(): string
	{
		return $this->scheme;
	}

	public function getPath(): string
	{
		return (is_null($this->path)) ? '' : $this->path;
	}

	public function getQuery(array $options = []): string
	{
		$vars = $this->query;

		if (array_key_exists('except', $options))
		{
			if (! is_array($options['except']))
			{
				$options['except'] = [$options['except']];
			}

			foreach ($options['except'] as $var)
			{
				unset($vars[$var]);
			}
		}
		elseif (array_key_exists('only', $options))
		{
			$temp = [];

			if (! is_array($options['only']))
			{
				$options['only'] = [$options['only']];
			}

			foreach ($options['only'] as $var)
			{
				if (array_key_exists($var, $vars))
				{
					$temp[$var] = $vars[$var];
				}
			}

			$vars = $temp;
		}

		return empty($vars) ? '' : http_build_query($vars);
	}

	public function getFragment(): string
	{
		return is_null($this->fragment) ? '' : $this->fragment;
	}

	public function getSegments(): array
	{
		return $this->segments;
	}

	public function setSegment(int $number, $value)
	{
		// The segment should treat the array as 1-based for the user
		// but we still have to deal with a zero-based array.
		$number -= 1;

		if ($number > count($this->segments) + 1)
		{
			throw new \RuntimeException("Request URI segment is our of range: {$number}");
		}

		$this->segments[$number] = $value;
		$this->refreshPath();

		return $this;
	}

	public function __toString(): string
	{
		return static::createURIString(
						$this->getScheme(), $this->getAuthority(), $this->getPath(), // Absolute URIs should use a "/" for an empty path
						$this->getQuery(), $this->getFragment()
		);
	}

	public static function createURIString(string $scheme = null, string $authority = null, string $path = null, string $query = null, string $fragment = null): string
	{
		$uri = '';
		if (! empty($scheme))
		{
			$uri .= $scheme . '://';
		}

		if (! empty($authority))
		{
			$uri .= $authority;
		}

		if ($path !== '')
		{
			$uri .= substr($uri, -1, 1) !== '/' ? '/' . ltrim($path, '/') : $path;
		}

		if ($query)
		{
			$uri .= '?' . $query;
		}

		if ($fragment)
		{
			$uri .= '#' . $fragment;
		}

		return $uri;
	}

	public function getAuthority(bool $ignorePort = false): string
	{
		if (empty($this->host))
		{
			return '';
		}

		$authority = $this->host;

		if (! empty($this->getUserInfo()))
		{
			$authority = $this->getUserInfo() . '@' . $authority;
		}

		if (! empty($this->port) && ! $ignorePort)
		{
			// Don't add port if it's a standard port for
			// this scheme
			if ($this->port !== $this->defaultPorts[$this->scheme])
			{
				$authority .= ':' . $this->port;
			}
		}

		$this->showPassword = false;

		return $authority;
	}

	public function getUserInfo()
	{
		$userInfo = $this->user;

		if ($this->showPassword === true && ! empty($this->password))
		{
			$userInfo .= ':' . $this->password;
		}

		return $userInfo;
	}

	public function setAuthority(string $str)
	{
		$parts = parse_url($str);

		if (! isset($parts['path']))
		{
			$parts['path'] = $this->getPath();
		}

		if (empty($parts['host']) && $parts['path'] !== '')
		{
			$parts['host'] = $parts['path'];
			unset($parts['path']);
		}

		$this->applyParts($parts);

		return $this;
	}

	public function setHost(string $str)
	{
		$this->host = trim($str);

		return $this;
	}

	public function setPort(int $port = null)
	{
		if (is_null($port))
		{
			return $this;
		}

		if ($port <= 0 || $port > 65535)
		{
			throw new \RuntimeException("Ports must be between 0 and 65535. Given: {$port}");
		}

		$this->port = $port;

		return $this;
	}

	public function refreshPath()
	{
		$this->path = $this->filterPath(implode('/', $this->segments));

		$tempPath = trim($this->path, '/');

		$this->segments = ($tempPath === '') ? [] : explode('/', $tempPath);

		return $this;
	}

	public function setPath(string $path)
	{
		$this->path = $this->filterPath($path);

		$tempPath = trim($this->path, '/');

		$this->segments = ($tempPath === '') ? [] : explode('/', $tempPath);

		return $this;
	}

	protected function filterPath(string $path = null): string
	{
		$orig = $path;

		// Decode/normalize percent-encoded chars so
		// we can always have matching for Routes, etc.
		$path = urldecode($path);

		// Remove dot segments
		$path = $this->removeDotSegments($path);

		// Fix up some leading slash edge cases...
		if (strpos($orig, './') === 0)
		{
			$path = '/' . $path;
		}
		if (strpos($orig, '../') === 0)
		{
			$path = '/' . $path;
		}

		// Encode characters
		$path = preg_replace_callback(
				'/(?:[^' . static::CHAR_UNRESERVED . ':@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/', function (array $matches) {
					return rawurlencode($matches[0]);
				}, $path
		);

		return $path;
	}

	protected function applyParts(array $parts)
	{
		if (! empty($parts['host']))
		{
			$this->host = $parts['host'];
		}
		if (! empty($parts['user']))
		{
			$this->user = $parts['user'];
		}
		if (isset($parts['path']) && $parts['path'] !== '')
		{
			$this->path = $this->filterPath($parts['path']);
		}
		if (! empty($parts['query']))
		{
			$this->setQuery($parts['query']);
		}
		if (! empty($parts['fragment']))
		{
			$this->fragment = $parts['fragment'];
		}

		// Scheme
		if (isset($parts['scheme']))
		{
			$this->setScheme(rtrim($parts['scheme'], ':/'));
		}
		else
		{
			$this->setScheme('http');
		}

		// Port
		if (isset($parts['port']))
		{
			if (! is_null($parts['port']))
			{
				// Valid port numbers are enforced by earlier parse_url or setPort()
				$port       = $parts['port'];
				$this->port = $port;
			}
		}

		if (isset($parts['pass']))
		{
			$this->password = $parts['pass'];
		}

		// Populate our segments array
		if (isset($parts['path']) && $parts['path'] !== '')
		{
			$tempPath = trim($parts['path'], '/');

			$this->segments = ($tempPath === '') ? [] : explode('/', $tempPath);
		}
	}

	public function setScheme(string $str)
	{
		$str = strtolower($str);
		$str = preg_replace('#:(//)?$#', '', $str);

		$this->scheme = $str;

		return $this;
	}

	public function setQuery(string $query)
	{
		if (strpos($query, '#') !== false)
		{
			throw new \RuntimeException("Query strings may not include URI fragments.");
		}

		// Can't have leading ?
		if (! empty($query) && strpos($query, '?') === 0)
		{
			$query = substr($query, 1);
		}

		parse_str($query, $this->query);

		return $this;
	}

	public function removeDotSegments(string $path): string
	{
		if ($path === '' || $path === '/')
		{
			return $path;
		}

		$output = [];

		$input = explode('/', $path);

		if ($input[0] === '')
		{
			unset($input[0]);
			$input = array_values($input);
		}

		foreach ($input as $segment)
		{
			if ($segment === '..')
			{
				array_pop($output);
			}
			else if ($segment !== '.' && $segment !== '')
			{
				array_push($output, $segment);
			}
		}

		$output = implode('/', $output);
		$output = ltrim($output, '/ ');

		if ($output !== '/')
		{
			// Add leading slash if necessary
			if (strpos($path, '/') === 0)
			{
				$output = '/' . $output;
			}

			// Add trailing slash if necessary
			if (substr($path, -1, 1) === '/')
			{
				$output .= '/';
			}
		}

		return $output;
	}

	public function resolveRelativeURI(string $uri)
	{
		$relative = new URI();
		$relative->setURI($uri);

		if ($relative->getScheme() === $this->getScheme())
		{
			$relative->setScheme('');
		}

		$transformed = clone $relative;

		// 5.2.2 Transform References in a non-strict method (no scheme)
		if (! empty($relative->getAuthority()))
		{
			$transformed->setAuthority($relative->getAuthority())
					->setPath($relative->getPath())
					->setQuery($relative->getQuery());
		}
		else
		{
			if ($relative->getPath() === '')
			{
				$transformed->setPath($this->getPath());

				if ($relative->getQuery())
				{
					$transformed->setQuery($relative->getQuery());
				}
				else
				{
					$transformed->setQuery($this->getQuery());
				}
			}
			else
			{
				if (strpos($relative->getPath(), '/') === 0)
				{
					$transformed->setPath($relative->getPath());
				}
				else
				{
					$transformed->setPath($this->mergePaths($this, $relative));
				}

				$transformed->setQuery($relative->getQuery());
			}

			$transformed->setAuthority($this->getAuthority());
		}

		$transformed->setScheme($this->getScheme());

		$transformed->setFragment($relative->getFragment());

		return $transformed;
	}

	protected function mergePaths(URI $base, URI $reference): string
	{
		if (! empty($base->getAuthority()) && $base->getPath() === '')
		{
			return '/' . ltrim($reference->getPath(), '/ ');
		}

		$path = explode('/', $base->getPath());

		if ($path[0] === '')
		{
			unset($path[0]);
		}

		array_pop($path);
		array_push($path, $reference->getPath());

		return implode('/', $path);
	}

	public function setFragment(string $string)
	{
		$this->fragment = trim($string, '# ');

		return $this;
	}
}