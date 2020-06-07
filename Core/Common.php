<?php

use QTCS\Http\URI;
use QTCS\Config\Config;
use QTCS\Config\Services;

if (! function_exists('force_https'))
{
	function force_https(int $duration = 31536000, $request = null, $response = null)
	{
		if (is_null($request))
		{
			$request = Services::request(null, true);
		}
		if (is_null($response))
		{
			$response = Services::response(null, true);
		}

		$baseURL = 'http://localhost:8888';

		if (strpos($baseURL, 'http://') === 0)
		{
			$baseURL = (string) substr($baseURL, strlen('http://'));
		}

		$uri = URI::createURIString(
			'https', $baseURL, $request->uri->getPath(), // Absolute URIs should use a "/" for an empty path
			$request->uri->getQuery(), $request->uri->getFragment()
		);

		// Set an HSTS header
		$response->setHeader('Strict-Transport-Security', 'max-age=' . $duration);
		$response->redirect($uri);
		$response->sendHeaders();

		if (ENVIRONMENT !== 'testing')
		{
			// @codeCoverageIgnoreStart
			exit();
			// @codeCoverageIgnoreEnd
		}
	}
}

if (! function_exists('config'))
{
	function config(string $name, bool $getShared = true)
	{
		return Config::get($name, $getShared);
	}
}