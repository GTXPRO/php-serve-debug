<?php
namespace QTCS\Http;

class URI {

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
}