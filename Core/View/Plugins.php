<?php
namespace QTCS\View;

use QTCS\Services\Services;

class Plugins {
	public static function currentURL()
	{
		return current_url();
	}

	public static function previousURL()
	{
		return previous_url();
	}

	public static function mailto(array $params = []): string
	{
		$email = $params['email'] ?? '';
		$title = $params['title'] ?? '';
		$attrs = $params['attributes'] ?? '';

		return mailto($email, $title, $attrs);
	}

	public static function safeMailto(array $params = []): string
	{
		$email = $params['email'] ?? '';
		$title = $params['title'] ?? '';
		$attrs = $params['attributes'] ?? '';

		return safe_mailto($email, $title, $attrs);
	}

	public static function lang(array $params = []): string
	{
		$line = array_shift($params);

		return lang($line, $params);
	}

	public static function ValidationErrors(array $params = []): string
	{
		$validator = Services::validation();
		if (empty($params))
		{
			return $validator->listErrors();
		}

		return $validator->showError($params['field']);
	}

	public static function route(array $params = [])
	{
		return route_to(...$params);
	}

	public static function siteURL(array $params = []): string
	{
		return site_url(...$params);
	}
}