<?php
namespace QTCS\Config;

class Services extends BaseServices {
	public static function request(App $config = null, bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('request', $config);
		}

		if (! is_object($config))
		{
			$config = config(App::class);
		}

		return new IncomingRequest(
				$config,
				static::uri(),
				'php://input',
				new UserAgent()
		);
	}

	public static function response(App $config = null, bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('response', $config);
		}

		if (! is_object($config))
		{
			$config = config(App::class);
		}

		return new Response($config);
	}
}
