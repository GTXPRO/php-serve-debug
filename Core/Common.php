<?php

use QTCS\Http\URI;
use QTCS\Config\Config;
use QTCS\Http\RedirectResponse;
use QTCS\Http\RequestInterface;
use QTCS\Http\Response;
use QTCS\Http\ResponseInterface;
use QTCS\Services\Services;

if (!function_exists('force_https')) {
	function force_https(int $duration = 31536000, RequestInterface $request = null, ResponseInterface $response = null)
	{
		if (is_null($request)) {
			$request = Services::request(null, true);
		}
		if (is_null($response)) {
			$response = Services::response(null, true);
		}

		if (ENVIRONMENT !== 'testing' && (is_cli() || $request->isSecure()))
		{
			return;
		}

		$app = new \Config\App();
		$baseURL = $app->baseURL ?? 'http://localhost:9999';

		if (strpos($baseURL, 'http://') === 0) {
			$baseURL = (string) substr($baseURL, strlen('http://'));
		}

		$uri = URI::createURIString(
			'https',
			$baseURL,
			$request->uri->getPath(), // Absolute URIs should use a "/" for an empty path
			$request->uri->getQuery(),
			$request->uri->getFragment()
		);

		// Set an HSTS header
		$response->setHeader('Strict-Transport-Security', 'max-age=' . $duration);
		$response->redirect($uri);
		$response->sendHeaders();

		if (ENVIRONMENT !== 'testing') {
			exit();
		}
	}
}

if (!function_exists('config')) {
	function config(string $name, bool $getShared = true)
	{
		return Config::get($name, $getShared);
	}
}

if (!function_exists('is_cli')) {
	function is_cli(): bool
	{
		return (PHP_SAPI === 'cli' || defined('STDIN'));
	}
}

if (!function_exists('dot_array_search')) {
	function dot_array_search(string $index, array $array)
	{
		$segments = explode('.', rtrim(rtrim($index, '* '), '.'));

		return _array_search_dot($segments, $array);
	}
}

if (!function_exists('_array_search_dot')) {
	function _array_search_dot(array $indexes, array $array)
	{
		// Grab the current index
		$currentIndex = $indexes
			? array_shift($indexes)
			: null;

		if ((empty($currentIndex)  && intval($currentIndex) !== 0) || (!isset($array[$currentIndex]) && $currentIndex !== '*')) {
			return null;
		}

		// Handle Wildcard (*)
		if ($currentIndex === '*') {
			// If $array has more than 1 item, we have to loop over each.
			if (is_array($array)) {
				foreach ($array as $value) {
					$answer = _array_search_dot($indexes, $value);

					if ($answer !== null) {
						return $answer;
					}
				}

				// Still here after searching all child nodes?
				return null;
			}
		}

		// If this is the last index, make sure to return it now,
		// and not try to recurse through things.
		if (empty($indexes)) {
			return $array[$currentIndex];
		}

		// Do we need to recursively search this value?
		if (is_array($array[$currentIndex]) && $array[$currentIndex]) {
			return _array_search_dot($indexes, $array[$currentIndex]);
		}

		// Otherwise we've found our match!
		return $array[$currentIndex];
	}
}

if (! function_exists('get_filenames'))
{
	function get_filenames(string $source_dir, ?bool $include_path = false, bool $hidden = false): array
	{
		$files = [];

		$source_dir = realpath($source_dir) ?: $source_dir;
		$source_dir = rtrim($source_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		try
		{
			foreach (new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
					RecursiveIteratorIterator::SELF_FIRST
				) as $name => $object)
			{
				$basename = pathinfo($name, PATHINFO_BASENAME);

				if (! $hidden && $basename[0] === '.')
				{
					continue;
				}
				elseif ($include_path === false)
				{
					$files[] = $basename;
				}
				elseif (is_null($include_path))
				{
					$files[] = str_replace($source_dir, '', $name);
				}
				else
				{
					$files[] = $name;
				}
			}
		}
		catch (\Throwable $e)
		{
			return [];
		}

		sort($files);

		return $files;
	}
}
if (! function_exists('redirect'))
{
	function redirect(string $uri = null): RedirectResponse
	{
		$response = Services::redirectResponse(null, true);

		if (! empty($uri))
		{
			return $response->route($uri);
		}

		return $response;
	}
}

if (! function_exists('current_url'))
{
	function current_url(bool $returnObject = false)
	{
		$uri = clone service('request')->uri;

		$app = new \Config\App();
		$baseUri = new \QTCS\HTTP\URI($app->baseURL);

		if (! empty($baseUri->getPath()))
		{
			$path = rtrim($baseUri->getPath(), '/ ') . '/' . $uri->getPath();

			$uri->setPath($path);
		}

		return $returnObject
			? $uri
			: (string)$uri->setQuery('');
	}
}

if (! function_exists('service'))
{
	function service(string $name, ...$params)
	{
		return Services::$name(...$params);
	}
}

if (! function_exists('site_url'))
{
	function site_url($uri = '', string $protocol = null, \Config\App $altConfig = null): string
	{
		// convert segment array to string
		if (is_array($uri))
		{
			$uri = implode('/', $uri);
		}

		// use alternate config if provided, else default one
		$config = $altConfig ?? config(\Config\App::class);

		$fullPath = rtrim(base_url(), '/') . '/';

		// Add index page, if so configured
		if (! empty($config->indexPage))
		{
			$fullPath .= rtrim($config->indexPage, '/');
		}
		if (! empty($uri))
		{
			$fullPath .= '/' . $uri;
		}

		$url = new \QTCS\HTTP\URI($fullPath);

		// allow the scheme to be over-ridden; else, use default
		if (! empty($protocol))
		{
			$url->setScheme($protocol);
		}

		return (string) $url;
	}
}

if (! function_exists('base_url'))
{
	function base_url($uri = '', string $protocol = null): string
	{
		// convert segment array to string
		if (is_array($uri))
		{
			$uri = implode('/', $uri);
		}
		$uri = trim($uri, '/');

		// We should be using the configured baseURL that the user set;
		// otherwise get rid of the path, because we have
		// no way of knowing the intent...
		$config = \QTCS\Services\Services::request()->config;

		// If baseUrl does not have a trailing slash it won't resolve
		// correctly for users hosting in a subfolder.
		$baseUrl = ! empty($config->baseURL) && $config->baseURL !== '/'
			? rtrim($config->baseURL, '/ ') . '/'
			: $config->baseURL;

		$url = new \QTCS\HTTP\URI($baseUrl);
		unset($config);

		// Merge in the path set by the user, if any
		if (! empty($uri))
		{
			$url = $url->resolveRelativeURI($uri);
		}

		// If the scheme wasn't provided, check to
		// see if it was a secure request
		if (empty($protocol) && \QTCS\Services\Services::request()->isSecure())
		{
			$protocol = 'https';
		}

		if (! empty($protocol))
		{
			$url->setScheme($protocol);
		}

		return rtrim((string) $url, '/ ');
	}
}

if (! function_exists('dot_array_search'))
{
	function dot_array_search(string $index, array $array)
	{
		$segments = explode('.', rtrim(rtrim($index, '* '), '.'));

		return _array_search_dot($segments, $array);
	}
}

if (! function_exists('lang'))
{
	function lang(string $line, array $args = [], string $locale = null)
	{
		return Services::language($locale)
			->getLine($line, $args);
	}
}

if (! function_exists('log_message'))
{
	function log_message(string $level, string $message, array $context = [])
	{

		return Services::logger(true)
			->log($level, $message, $context);
	}
}

if (! function_exists('remove_invisible_characters'))
{
	function remove_invisible_characters(string $str, bool $urlEncoded = true): string
	{
		$nonDisplayables = [];

		// every control character except newline (dec 10),
		// carriage return (dec 13) and horizontal tab (dec 09)
		if ($urlEncoded)
		{
			$nonDisplayables[] = '/%0[0-8bcef]/';  // url encoded 00-08, 11, 12, 14, 15
			$nonDisplayables[] = '/%1[0-9a-f]/';   // url encoded 16-31
		}

		$nonDisplayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';   // 00-08, 11, 12, 14-31, 127

		do
		{
			$str = preg_replace($nonDisplayables, '', $str, -1, $count);
		}
		while ($count);

		return $str;
	}
}

if (! function_exists('previous_url'))
{
	function previous_url(bool $returnObject = false)
	{
		$referer = $_SESSION['_ci_previous_url'] ?? \QTCS\Services\Services::request()->getServer('HTTP_REFERER', FILTER_SANITIZE_URL);

		$referer = $referer ?? site_url('/');

		return $returnObject ? new \QTCS\HTTP\URI($referer) : $referer;
	}
}
