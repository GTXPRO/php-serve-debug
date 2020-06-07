<?php
namespace QTCS\Services;

use Config\App;
use Config\Logger;
use Config\Modules;
use QTCS\Filters\Filters;
use QTCS\Http\CLIRequest;
use QTCS\Http\IncomingRequest;
use QTCS\Http\Request;
use QTCS\Http\Response;
use QTCS\Http\URI;
use QTCS\Http\UserAgent;
use QTCS\Language\Language;
use QTCS\Router\RouteCollection;
use QTCS\Router\RouteCollectionInterface;
use QTCS\Router\Router;
use QTCS\Security\Security;
use QTCS\Validation\Validation;

class Services extends BaseServices {
	public static function request(App $config = null, bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('request', $config);
		}

		if (! is_object($config))
		{
			$config = new App();
		}

		return new IncomingRequest(
				$config,
				static::uri(),
				'php://input',
				new UserAgent()
		);
	}

	public static function clirequest(App $config = null, bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('clirequest', $config);
		}

		if (! is_object($config))
		{
			$config = new App();
		}

		return new CLIRequest($config);
	}

	public static function response(App $config = null, bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('response', $config);
		}

		if (! is_object($config))
		{
			$config = new App();
		}

		return new Response($config);
	}

	public static function filters($config = null, bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('filters', $config);
		}

		if (empty($config))
		{
			$config = new \Config\Filters();
		}

		return new Filters($config, static::request(), static::response());
	}

	public static function security(App $config = null, bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('security', $config);
		}

		if (! is_object($config))
		{
			$config = new App();
		}

		return new Security($config);
	}

	public static function routes(bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('routes');
		}

		return new RouteCollection(static::locator(), new Modules());
	}

	public static function validation(\Config\Validation $config = null, bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('validation', $config);
		}

		if (is_null($config))
		{
			$config = config('Validation');
		}

		return new Validation($config, static::renderer());
	}

	public static function language(string $locale = null, bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('language', $locale)
							->setLocale($locale);
		}

		$locale = ! empty($locale) ? $locale : static::request()
						->getLocale();

		return new Language($locale);
	}

	public static function logger(bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('logger');
		}

		return new \QTCS\Log\Logger(new Logger());
	}

	public static function uri(string $uri = null, bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('uri', $uri);
		}

		return new URI($uri);
	}

	public static function router(RouteCollectionInterface $routes = null, Request $request = null, bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('router', $routes, $request);
		}

		if (empty($routes))
		{
			$routes = static::routes();
		}

		return new Router($routes, $request);
	}
}