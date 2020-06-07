<?php
namespace QTCS\Http;

class IncomingRequest extends Request {
	protected $enableCSRF = false;
	public $uri;
	protected $files;
	protected $negotiator;
	protected $defaultLocale;
	protected $locale;
	protected $validLocales = [];
	public $config;
	protected $oldInput = [];
	protected $userAgent;

	public function __construct($config, URI $uri = null, $body = 'php://input', UserAgent $userAgent)
	{
		// Get our body from php://input
		if ($body === 'php://input')
		{
			$body = file_get_contents('php://input');
		}

		$this->body      = $body;
		$this->config    = $config;
		$this->userAgent = $userAgent;

		parent::__construct($config);

		$this->populateHeaders();

		$this->uri = $uri;

		$this->detectURI($config->uriProtocol, $config->baseURL);

		$this->validLocales = $config->supportedLocales;

		$this->detectLocale($config);
	}

	public function detectLocale($config)
	{
		$this->locale = $this->defaultLocale = $config->defaultLocale;

		if (! $config->negotiateLocale)
		{
			return;
		}

		$this->setLocale($this->negotiate('language', $config->supportedLocales));
	}

	public function getDefaultLocale(): string
	{
		return $this->defaultLocale;
	}

	public function getLocale(): string
	{
		return $this->locale ?? $this->defaultLocale;
	}

	public function setLocale(string $locale)
	{
		// If it's not a valid locale, set it
		// to the default locale for the site.
		if (! in_array($locale, $this->validLocales))
		{
			$locale = $this->defaultLocale;
		}

		$this->locale = $locale;

		// If the intl extension is loaded, make sure
		// that we set the locale for it... if not, though,
		// don't worry about it.
		// this should not block code coverage thru unit testing
		// @codeCoverageIgnoreStart
		try
		{
			if (class_exists('\Locale', false))
			{
				\Locale::setDefault($locale);
			}
		}
		catch (\Exception $e)
		{
		}
		// @codeCoverageIgnoreEnd

		return $this;
	}

	public function isCLI(): bool
	{
		return is_cli();
	}

	public function isAJAX(): bool
	{
		return ( ! empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
				strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
	}

	public function isSecure(): bool
	{
		if (! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
		{
			return true;
		}
		elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
		{
			return true;
		}
		elseif (! empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
		{
			return true;
		}

		return false;
	}

	public function getVar($index = null, $filter = null, $flags = null)
	{
		return $this->fetchGlobal('request', $index, $filter, $flags);
	}

	public function getJSON(bool $assoc = false, int $depth = 512, int $options = 0)
	{
		return json_decode($this->body, $assoc, $depth, $options);
	}

	public function getRawInput()
	{
		parse_str($this->body, $output);

		return $output;
	}

	public function getGet($index = null, $filter = null, $flags = null)
	{
		return $this->fetchGlobal('get', $index, $filter, $flags);
	}

	public function getPost($index = null, $filter = null, $flags = null)
	{
		return $this->fetchGlobal('post', $index, $filter, $flags);
	}

	public function getPostGet($index = null, $filter = null, $flags = null)
	{
		return isset($_POST[$index]) ? $this->getPost($index, $filter, $flags) : (isset($_GET[$index]) ? $this->getGet($index, $filter, $flags) : $this->getPost());
	}

	public function getGetPost($index = null, $filter = null, $flags = null)
	{
		return isset($_GET[$index]) ? $this->getGet($index, $filter, $flags) : (isset($_POST[$index]) ? $this->getPost($index, $filter, $flags) : $this->getGet());
	}

	public function getCookie($index = null, $filter = null, $flags = null)
	{
		return $this->fetchGlobal('cookie', $index, $filter, $flags);
	}

	public function getUserAgent()
	{
		return $this->userAgent;
	}

	public function getOldInput(string $key)
	{
		// If the session hasn't been started, or no
		// data was previously saved, we're done.
		if (empty($_SESSION['_ci_old_input']))
		{
			return;
		}

		// Check for the value in the POST array first.
		if (isset($_SESSION['_ci_old_input']['post'][$key]))
		{
			return $_SESSION['_ci_old_input']['post'][$key];
		}

		// Next check in the GET array.
		if (isset($_SESSION['_ci_old_input']['get'][$key]))
		{
			return $_SESSION['_ci_old_input']['get'][$key];
		}

		// Check for an array value in POST.
		if (isset($_SESSION['_ci_old_input']['post']))
		{
			$value = dot_array_search($key, $_SESSION['_ci_old_input']['post']);
			if (! is_null($value))
			{
				return $value;
			}
		}

		// Check for an array value in GET.
		if (isset($_SESSION['_ci_old_input']['get']))
		{
			$value = dot_array_search($key, $_SESSION['_ci_old_input']['get']);
			if (! is_null($value))
			{
				return $value;
			}
		}
	}

	public function getFiles()
	{
		if (is_null($this->files))
		{
			// $this->files = new FileCollection();
		}

		// return $this->files->all(); // return all files
	}

	public function getFileMultiple(string $fileID)
	{
		if (is_null($this->files))
		{
			// $this->files = new FileCollection();
		}

		// return $this->files->getFileMultiple($fileID);
	}

	public function getFile(string $fileID)
	{
		if (is_null($this->files))
		{
			// $this->files = new FileCollection();
		}

		// return $this->files->getFile($fileID);
	}

	public function negotiate(string $type, array $supported, bool $strictMatch = false): string
	{
		if (is_null($this->negotiator))
		{
			$this->negotiator = new Negotiate();
		}

		switch (strtolower($type))
		{
			case 'media':
				return $this->negotiator->media($supported, $strictMatch);
			case 'charset':
				return $this->negotiator->charset($supported);
			case 'encoding':
				return $this->negotiator->encoding($supported);
			case 'language':
				return $this->negotiator->language($supported);
		}

		throw new \RuntimeException("{$type} is not a valid negotiation type. Must be one of: media, charset, encoding, language.");
	}

	protected function detectURI(string $protocol, string $baseURL)
	{
		$this->uri->setPath($this->detectPath($protocol));

		// It's possible the user forgot a trailing slash on their
		// baseURL, so let's help them out.
		$baseURL = ! empty($baseURL) ? rtrim($baseURL, '/ ') . '/' : $baseURL;

		// Based on our baseURL provided by the developer
		// set our current domain name, scheme
		if (! empty($baseURL))
		{
			$this->uri->setScheme(parse_url($baseURL, PHP_URL_SCHEME));
			$this->uri->setHost(parse_url($baseURL, PHP_URL_HOST));
			$this->uri->setPort(parse_url($baseURL, PHP_URL_PORT));

			// Ensure we have any query vars
			$this->uri->setQuery($_SERVER['QUERY_STRING'] ?? '');
		}
		else
		{
			// @codeCoverageIgnoreStart
			if (! is_cli())
			{
				die('You have an empty or invalid base URL. The baseURL value must be set in Config\App.php, or through the .env file.');
			}
			// @codeCoverageIgnoreEnd
		}
	}

	public function detectPath(string $protocol = ''): string
	{
		if (empty($protocol))
		{
			$protocol = 'REQUEST_URI';
		}

		switch ($protocol)
		{
			case 'REQUEST_URI':
				$path = $this->parseRequestURI();
				break;
			case 'QUERY_STRING':
				$path = $this->parseQueryString();
				break;
			case 'PATH_INFO':
			default:
				$path = $this->fetchGlobal('server', $protocol) ?? $this->parseRequestURI();
				break;
		}

		return $path;
	}

	protected function parseRequestURI(): string
	{
		if (! isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME']))
		{
			return '';
		}

		// parse_url() returns false if no host is present, but the path or query string
		// contains a colon followed by a number. So we attach a dummy host since
		// REQUEST_URI does not include the host. This allows us to parse out the query string and path.
		$parts = parse_url('http://dummy' . $_SERVER['REQUEST_URI']);
		$query = $parts['query'] ?? '';
		$uri   = $parts['path'] ?? '';

		if (isset($_SERVER['SCRIPT_NAME'][0]) && pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_EXTENSION) === 'php')
		{
			// strip the script name from the beginning of the URI
			if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0)
			{
				$uri = (string) substr($uri, strlen($_SERVER['SCRIPT_NAME']));
			}
			// if the script is nested, strip the parent folder & script from the URI
			elseif (strpos($uri, $_SERVER['SCRIPT_NAME']) > 0)
			{
				$uri = (string) substr($uri, strpos($uri, $_SERVER['SCRIPT_NAME']) + strlen($_SERVER['SCRIPT_NAME']));
			}
			// or if index.php is implied
			elseif (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0)
			{
				$uri = (string) substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
			}
		}

		// This section ensures that even on servers that require the URI to contain the query string (Nginx) a correct
		// URI is found, and also fixes the QUERY_STRING getServer var and $_GET array.
		if (trim($uri, '/') === '' && strncmp($query, '/', 1) === 0)
		{
			$query                   = explode('?', $query, 2);
			$uri                     = $query[0];
			$_SERVER['QUERY_STRING'] = $query[1] ?? '';
		}
		else
		{
			$_SERVER['QUERY_STRING'] = $query;
		}

		parse_str($_SERVER['QUERY_STRING'], $_GET);

		if ($uri === '/' || $uri === '')
		{
			return '/';
		}

		return $this->removeRelativeDirectory($uri);
	}

	protected function parseQueryString(): string
	{
		$uri = $_SERVER['QUERY_STRING'] ?? @getenv('QUERY_STRING');

		if (trim($uri, '/') === '')
		{
			return '';
		}
		elseif (strncmp($uri, '/', 1) === 0)
		{
			$uri                     = explode('?', $uri, 2);
			$_SERVER['QUERY_STRING'] = $uri[1] ?? '';
			$uri                     = $uri[0];
		}

		parse_str($_SERVER['QUERY_STRING'], $_GET);

		return $this->removeRelativeDirectory($uri);
	}

	protected function removeRelativeDirectory(string $uri): string
	{
		$uris = [];
		$tok  = strtok($uri, '/');
		while ($tok !== false)
		{
			if (( ! empty($tok) || $tok === '0') && $tok !== '..')
			{
				$uris[] = $tok;
			}
			$tok = strtok('/');
		}

		return implode('/', $uris);
	}
}