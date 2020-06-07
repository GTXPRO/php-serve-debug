<?php

use Config\View;
use Laminas\Escaper\Escaper;
use QTCS\Http\URI;
use QTCS\Config\Config;
use QTCS\Http\RedirectResponse;
use QTCS\Http\RequestInterface;
use QTCS\Http\Response;
use QTCS\Http\ResponseInterface;
use QTCS\Services\Services;

if (! function_exists('esc'))
{
	/**
	 * Performs simple auto-escaping of data for security reasons.
	 * Might consider making this more complex at a later date.
	 *
	 * If $data is a string, then it simply escapes and returns it.
	 * If $data is an array, then it loops over it, escaping each
	 * 'value' of the key/value pairs.
	 *
	 * Valid context values: html, js, css, url, attr, raw, null
	 *
	 * @param string|array $data
	 * @param string       $context
	 * @param string       $encoding
	 *
	 * @return string|array
	 * @throws \InvalidArgumentException
	 */
	function esc($data, string $context = 'html', string $encoding = null)
	{
		if (is_array($data))
		{
			foreach ($data as &$value)
			{
				$value = esc($value, $context);
			}
		}

		if (is_string($data))
		{
			$context = strtolower($context);

			// Provide a way to NOT escape data since
			// this could be called automatically by
			// the View library.
			if (empty($context) || $context === 'raw')
			{
				return $data;
			}

			if (! in_array($context, ['html', 'js', 'css', 'url', 'attr']))
			{
				throw new InvalidArgumentException('Invalid escape context provided.');
			}

			if ($context === 'attr')
			{
				$method = 'escapeHtmlAttr';
			}
			else
			{
				$method = 'escape' . ucfirst($context);
			}

			static $escaper;
			if (! $escaper)
			{
				$escaper = new Escaper($encoding);
			}

			if ($encoding && $escaper->getEncoding() !== $encoding)
			{
				$escaper = new Escaper($encoding);
			}

			$data = $escaper->$method($data);
		}

		return $data;
	}
}

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

if (! function_exists('view'))
{
	function view(string $name, array $data = [], array $options = []): string
	{
		$renderer = Services::renderer();

		$view = new \Config\View();
		$saveData = $view->saveData;

		if (array_key_exists('saveData', $options))
		{
			$saveData = (bool) $options['saveData'];
			unset($options['saveData']);
		}

		return $renderer->setData($data, 'raw')
						->render($name, $options, $saveData);
	}
}

if (! function_exists('route_to'))
{
	function route_to(string $method, ...$params)
	{
		return Services::routes()->reverseRoute($method, ...$params);
	}
}

if (! function_exists('safe_mailto'))
{
	function safe_mailto(string $email, string $title = '', $attributes = ''): string
	{
		if (trim($title) === '')
		{
			$title = $email;
		}

		$x = str_split('<a href="mailto:', 1);

		for ($i = 0, $l = strlen($email); $i < $l; $i ++)
		{
			$x[] = '|' . ord($email[$i]);
		}

		$x[] = '"';

		if ($attributes !== '')
		{
			if (is_array($attributes))
			{
				foreach ($attributes as $key => $val)
				{
					$x[] = ' ' . $key . '="';
					for ($i = 0, $l = strlen($val); $i < $l; $i ++)
					{
						$x[] = '|' . ord($val[$i]);
					}
					$x[] = '"';
				}
			}
			else
			{
				for ($i = 0, $l = mb_strlen($attributes); $i < $l; $i ++)
				{
					$x[] = mb_substr($attributes, $i, 1);
				}
			}
		}

		$x[] = '>';

		$temp = [];
		for ($i = 0, $l = strlen($title); $i < $l; $i ++)
		{
			$ordinal = ord($title[$i]);

			if ($ordinal < 128)
			{
				$x[] = '|' . $ordinal;
			}
			else
			{
				if (empty($temp))
				{
					$count = ($ordinal < 224) ? 2 : 3;
				}

				$temp[] = $ordinal;
				if (count($temp) === $count)
				{
					$number = ($count === 3) ? (($temp[0] % 16) * 4096) + (($temp[1] % 64) * 64) + ($temp[2] % 64) : (($temp[0] % 32) * 64) + ($temp[1] % 64);
					$x[]    = '|' . $number;
					$count  = 1;
					$temp   = [];
				}
			}
		}

		$x[] = '<';
		$x[] = '/';
		$x[] = 'a';
		$x[] = '>';

		$x = array_reverse($x);

		// improve obfuscation by eliminating newlines & whitespace
		$output = '<script type="text/javascript">'
				. 'var l=new Array();';

		for ($i = 0, $c = count($x); $i < $c; $i ++)
		{
			$output .= 'l[' . $i . "] = '" . $x[$i] . "';";
		}

		return $output . ('for (var i = l.length-1; i >= 0; i=i-1) {'
				. "if (l[i].substring(0, 1) === '|') document.write(\"&#\"+unescape(l[i].substring(1))+\";\");"
				. 'else document.write(unescape(l[i]));'
				. '}'
				. '</script>');
	}
}

if (! function_exists('mailto'))
{
	function mailto(string $email, string $title = '', $attributes = ''): string
	{
		if (trim($title) === '')
		{
			$title = $email;
		}

		return '<a href="mailto:' . $email . '"' . stringify_attributes($attributes) . '>' . $title . '</a>';
	}
}

if (! function_exists('stringify_attributes'))
{
	function stringify_attributes($attributes, bool $js = false): string
	{
		$atts = '';

		if (empty($attributes))
		{
			return $atts;
		}

		if (is_string($attributes))
		{
			return ' ' . $attributes;
		}

		$attributes = (array) $attributes;

		foreach ($attributes as $key => $val)
		{
			$atts .= ($js) ? $key . '=' . esc($val, 'js') . ',' : ' ' . $key . '="' . esc($val, 'attr') . '"';
		}

		return rtrim($atts, ',');
	}
}

if (! function_exists('excerpt'))
{
	function excerpt(string $text, string $phrase = null, int $radius = 100, string $ellipsis = '...'): string
	{
		if (isset($phrase))
		{
			$phrasePos = strpos(strtolower($text), strtolower($phrase));
			$phraseLen = strlen($phrase);
		}
		elseif (! isset($phrase))
		{
			$phrasePos = $radius / 2;
			$phraseLen = 1;
		}

		$pre = explode(' ', substr($text, 0, $phrasePos));
		$pos = explode(' ', substr($text, $phrasePos + $phraseLen));

		$prev  = ' ';
		$post  = ' ';
		$count = 0;

		foreach (array_reverse($pre) as $e)
		{
			if ((strlen($e) + $count + 1) < $radius)
			{
				$prev = ' ' . $e . $prev;
			}
			$count = ++ $count + strlen($e);
		}

		$count = 0;

		foreach ($pos as $s)
		{
			if ((strlen($s) + $count + 1) < $radius)
			{
				$post .= $s . ' ';
			}
			$count = ++ $count + strlen($s);
		}

		$ellPre = $phrase ? $ellipsis : '';

		return str_replace('  ', ' ', $ellPre . $prev . $phrase . $post . $ellipsis);
	}
}

if (! function_exists('highlight_phrase'))
{
	function highlight_phrase(string $str, string $phrase, string $tag_open = '<mark>', string $tag_close = '</mark>'): string
	{
		return ($str !== '' && $phrase !== '') ? preg_replace('/(' . preg_quote($phrase, '/') . ')/i', $tag_open . '\\1' . $tag_close, $str) : $str;
	}
}

if (! function_exists('highlight_code'))
{
	function highlight_code(string $str): string
	{
		/* The highlight string function encodes and highlights
		 * brackets so we need them to start raw.
		 *
		 * Also replace any existing PHP tags to temporary markers
		 * so they don't accidentally break the string out of PHP,
		 * and thus, thwart the highlighting.
		 */
		$str = str_replace([
			'&lt;',
			'&gt;',
			'<?',
			'?>',
			'<%',
			'%>',
			'\\',
			'</script>',
		], [
			'<',
			'>',
			'phptagopen',
			'phptagclose',
			'asptagopen',
			'asptagclose',
			'backslashtmp',
			'scriptclose',
		], $str
		);

		// The highlight_string function requires that the text be surrounded
		// by PHP tags, which we will remove later
		$str = highlight_string('<?php ' . $str . ' ?>', true);

		// Remove our artificially added PHP, and the syntax highlighting that came with it
		$str = preg_replace([
			'/<span style="color: #([A-Z0-9]+)">&lt;\?php(&nbsp;| )/i',
			'/(<span style="color: #[A-Z0-9]+">.*?)\?&gt;<\/span>\n<\/span>\n<\/code>/is',
			'/<span style="color: #[A-Z0-9]+"\><\/span>/i',
		], [
			'<span style="color: #$1">',
			"$1</span>\n</span>\n</code>",
			'',
		], $str
		);

		// Replace our markers back to PHP tags.
		return str_replace([
			'phptagopen',
			'phptagclose',
			'asptagopen',
			'asptagclose',
			'backslashtmp',
			'scriptclose',
		], [
			'&lt;?',
			'?&gt;',
			'&lt;%',
			'%&gt;',
			'\\',
			'&lt;/script&gt;',
		], $str
		);
	}
}

if (! function_exists('character_limiter'))
{
	function character_limiter(string $str, int $n = 500, string $end_char = '&#8230;'): string
	{
		if (mb_strlen($str) < $n)
		{
			return $str;
		}

		// a bit complicated, but faster than preg_replace with \s+
		$str = preg_replace('/ {2,}/', ' ', str_replace(["\r", "\n", "\t", "\x0B", "\x0C"], ' ', $str));

		if (mb_strlen($str) <= $n)
		{
			return $str;
		}

		$out = '';

		foreach (explode(' ', trim($str)) as $val)
		{
			$out .= $val . ' ';
			if (mb_strlen($out) >= $n)
			{
				$out = trim($out);
				break;
			}
		}
		return (mb_strlen($out) === mb_strlen($str)) ? $out : $out . $end_char;
	}
}

if (! function_exists('word_limiter'))
{
	function word_limiter(string $str, int $limit = 100, string $end_char = '&#8230;'): string
	{
		if (trim($str) === '')
		{
			return $str;
		}

		preg_match('/^\s*+(?:\S++\s*+){1,' . (int) $limit . '}/', $str, $matches);

		if (strlen($str) === strlen($matches[0]))
		{
			$end_char = '';
		}

		return rtrim($matches[0]) . $end_char;
	}
}

if (! function_exists('format_number'))
{
	function format_number(float $num, int $precision = 1, string $locale = null, array $options = []): string
	{
		// Locale is either passed in here, negotiated with client, or grabbed from our config file.
		$locale = $locale ?? Services::request()->getLocale();

		// Type can be any of the NumberFormatter options, but provide a default.
		$type = (int) ($options['type'] ?? NumberFormatter::DECIMAL);

		$formatter = new NumberFormatter($locale, $type);

		// Try to format it per the locale
		if ($type === NumberFormatter::CURRENCY)
		{
			$formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $options['fraction']);
			$output = $formatter->formatCurrency($num, $options['currency']);
		}
		else
		{
			// In order to specify a precision, we'll have to modify
			// the pattern used by NumberFormatter.
			$pattern = '#,##0.' . str_repeat('#', $precision);

			$formatter->setPattern($pattern);
			$output = $formatter->format($num);
		}

		// This might lead a trailing period if $precision == 0
		$output = trim($output, '. ');

		if (intl_is_failure($formatter->getErrorCode()))
		{
			throw new BadFunctionCallException($formatter->getErrorMessage());
		}

		// Add on any before/after text.
		if (isset($options['before']) && is_string($options['before']))
		{
			$output = $options['before'] . $output;
		}

		if (isset($options['after']) && is_string($options['after']))
		{
			$output .= $options['after'];
		}

		return $output;
	}
}
if (! function_exists('format_number'))
{
	function format_number(float $num, int $precision = 1, string $locale = null, array $options = []): string
	{
		// Locale is either passed in here, negotiated with client, or grabbed from our config file.
		$locale = $locale ?? Services::request()->getLocale();

		// Type can be any of the NumberFormatter options, but provide a default.
		$type = (int) ($options['type'] ?? NumberFormatter::DECIMAL);

		$formatter = new NumberFormatter($locale, $type);

		// Try to format it per the locale
		if ($type === NumberFormatter::CURRENCY)
		{
			$formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $options['fraction']);
			$output = $formatter->formatCurrency($num, $options['currency']);
		}
		else
		{
			// In order to specify a precision, we'll have to modify
			// the pattern used by NumberFormatter.
			$pattern = '#,##0.' . str_repeat('#', $precision);

			$formatter->setPattern($pattern);
			$output = $formatter->format($num);
		}

		// This might lead a trailing period if $precision == 0
		$output = trim($output, '. ');

		if (intl_is_failure($formatter->getErrorCode()))
		{
			throw new BadFunctionCallException($formatter->getErrorMessage());
		}

		// Add on any before/after text.
		if (isset($options['before']) && is_string($options['before']))
		{
			$output = $options['before'] . $output;
		}

		if (isset($options['after']) && is_string($options['after']))
		{
			$output .= $options['after'];
		}

		return $output;
	}
}

if (! function_exists('cache'))
{
	function cache(string $key = null)
	{
		$cache = Services::cache();

		// No params - return cache object
		if (is_null($key))
		{
			return $cache;
		}

		// Still here? Retrieve the value.
		return $cache->get($key);
	}
}

if (! function_exists('is_really_writable'))
{
	function is_really_writable(string $file): bool
	{
		// If we're on a Unix server with safe_mode off we call is_writable
		if (DIRECTORY_SEPARATOR === '/' || ! ini_get('safe_mode'))
		{
			return is_writable($file);
		}

		/* For Windows servers and safe_mode "on" installations we'll actually
		 * write a file then read it. Bah...
		 */
		if (is_dir($file))
		{
			$file = rtrim($file, '/') . '/' . bin2hex(random_bytes(16));
			if (($fp = @fopen($file, 'ab')) === false)
			{
				return false;
			}

			fclose($fp);
			@chmod($file, 0777);
			@unlink($file);

			return true;
		}
		elseif (! is_file($file) || ( $fp = @fopen($file, 'ab')) === false)
		{
			return false;
		}

		fclose($fp);

		return true;
	}
}