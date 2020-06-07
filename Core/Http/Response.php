<?php

namespace QTCS\Http;

class Response extends Base implements ResponseInterface
{
	protected static $statusCodes = [
		// 1xx: Informational
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing', // http://www.iana.org/go/rfc2518
		103 => 'Early Hints', // http://www.ietf.org/rfc/rfc8297.txt
		// 2xx: Success
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information', // 1.1
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status', // http://www.iana.org/go/rfc4918
		208 => 'Already Reported', // http://www.iana.org/go/rfc5842
		226 => 'IM Used', // 1.1; http://www.ietf.org/rfc/rfc3229.txt
		// 3xx: Redirection
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found', // Formerly 'Moved Temporarily'
		303 => 'See Other', // 1.1
		304 => 'Not Modified',
		305 => 'Use Proxy', // 1.1
		306 => 'Switch Proxy', // No longer used
		307 => 'Temporary Redirect', // 1.1
		308 => 'Permanent Redirect', // 1.1; Experimental; http://www.ietf.org/rfc/rfc7238.txt
		// 4xx: Client error
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		418 => "I'm a teapot", // April's Fools joke; http://www.ietf.org/rfc/rfc2324.txt
		// 419 (Authentication Timeout) is a non-standard status code with unknown origin
		421 => 'Misdirected Request', // http://www.iana.org/go/rfc7540 Section 9.1.2
		422 => 'Unprocessable Entity', // http://www.iana.org/go/rfc4918
		423 => 'Locked', // http://www.iana.org/go/rfc4918
		424 => 'Failed Dependency', // http://www.iana.org/go/rfc4918
		425 => 'Too Early', // https://datatracker.ietf.org/doc/draft-ietf-httpbis-replay/
		426 => 'Upgrade Required',
		428 => 'Precondition Required', // 1.1; http://www.ietf.org/rfc/rfc6585.txt
		429 => 'Too Many Requests', // 1.1; http://www.ietf.org/rfc/rfc6585.txt
		431 => 'Request Header Fields Too Large', // 1.1; http://www.ietf.org/rfc/rfc6585.txt
		451 => 'Unavailable For Legal Reasons', // http://tools.ietf.org/html/rfc7725
		499 => 'Client Closed Request', // http://lxr.nginx.org/source/src/http/ngx_http_request.h#0133
		// 5xx: Server error
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates', // 1.1; http://www.ietf.org/rfc/rfc2295.txt
		507 => 'Insufficient Storage', // http://www.iana.org/go/rfc4918
		508 => 'Loop Detected', // http://www.iana.org/go/rfc5842
		510 => 'Not Extended', // http://www.ietf.org/rfc/rfc2774.txt
		511 => 'Network Authentication Required', // http://www.ietf.org/rfc/rfc6585.txt
		599 => 'Network Connect Timeout Error', // https://httpstatuses.com/599
	];

	protected $reason;
	protected $statusCode = 200;
	protected $formatters = [
		'application/json' 	=> \QTCS\Format\JSONFormatter::class,
		'application/xml'	=> \QTCS\Format\XMLFormatter::class,
		'text/xml'			=> \QTCS\Format\XMLFormatter::class,
	];

	protected $CSPEnabled = false;
	protected $cookiePrefix = '';
	protected $cookieDomain = '';
	protected $cookiePath = '/';
	protected $cookieSecure = false;
	protected $cookieHTTPOnly = false;
	protected $cookies = [];
	protected $pretend = false;
	protected $bodyFormat = 'html';

	public function __construct($config)
	{
		$this->CSPEnabled     = $config->CSPEnabled;
		$this->cookiePrefix   = $config->cookiePrefix;
		$this->cookieDomain   = $config->cookieDomain;
		$this->cookiePath     = $config->cookiePath;
		$this->cookieSecure   = $config->cookieSecure;
		$this->cookieHTTPOnly = $config->cookieHTTPOnly;

		$this->setContentType('text/html');
	}

	public function pretend(bool $pretend = true)
	{
		$this->pretend = $pretend;

		return $this;
	}

	public function getStatusCode(): int
	{
		if (empty($this->statusCode)) {
			throw new \RuntimeException("HTTP Response is missing a status code");
		}

		return $this->statusCode;
	}

	public function setStatusCode(int $code, string $reason = '')
	{
		// Valid range?
		if ($code < 100 || $code > 599) {
			throw new \RuntimeException("{{$code}, string} is not a valid HTTP return status code");
		}

		// Unknown and no message?
		if (!array_key_exists($code, static::$statusCodes) && empty($reason)) {
			throw new \RuntimeException("Unknown HTTP status code provided with no message: {$code}");
		}

		$this->statusCode = $code;

		if (!empty($reason)) {
			$this->reason = $reason;
		} else {
			$this->reason = static::$statusCodes[$code];
		}

		return $this;
	}

	public function getReason(): string
	{
		if (empty($this->reason)) {
			return !empty($this->statusCode) ? static::$statusCodes[$this->statusCode] : '';
		}

		return $this->reason;
	}

	public function setDate(\DateTime $date)
	{
		$date->setTimezone(new \DateTimeZone('UTC'));

		$this->setHeader('Date', $date->format('D, d M Y H:i:s') . ' GMT');

		return $this;
	}

	// public function setLink(PagerInterface $pager)
	// {
	// 	$links = '';

	// 	if ($previous = $pager->getPreviousPageURI())
	// 	{
	// 		$links .= '<' . $pager->getPageURI($pager->getFirstPage()) . '>; rel="first",';
	// 		$links .= '<' . $previous . '>; rel="prev"';
	// 	}

	// 	if (($next = $pager->getNextPageURI()) && $previous)
	// 	{
	// 		$links .= ',';
	// 	}

	// 	if ($next)
	// 	{
	// 		$links .= '<' . $next . '>; rel="next",';
	// 		$links .= '<' . $pager->getPageURI($pager->getLastPage()) . '>; rel="last"';
	// 	}

	// 	$this->setHeader('Link', $links);

	// 	return $this;
	// }

	public function setContentType(string $mime, string $charset = 'UTF-8')
	{
		// add charset attribute if not already there and provided as parm
		if ((strpos($mime, 'charset=') < 1) && !empty($charset)) {
			$mime .= '; charset=' . $charset;
		}

		$this->removeHeader('Content-Type'); // replace existing content type
		$this->setHeader('Content-Type', $mime);

		return $this;
	}

	public function setJSON($body, bool $unencoded = false)
	{
		$this->body = $this->formatBody($body, 'json' . ($unencoded ? '-unencoded' : ''));

		return $this;
	}

	public function getJSON()
	{
		$body = $this->body;

		if ($this->bodyFormat !== 'json') {
			$formatter = $this->getClassByMime('application/json');

			$body = $formatter->format($body);
		}

		return $body ?: null;
	}

	public function setXML($body)
	{
		$this->body = $this->formatBody($body, 'xml');

		return $this;
	}

	public function getXML()
	{
		$body = $this->body;

		if ($this->bodyFormat !== 'xml') {
			$formatter = $this->getClassByMime('application/xml');

			$body = $formatter->format($body);
		}

		return $body;
	}

	protected function formatBody($body, string $format)
	{
		$this->bodyFormat = ($format === 'json-unencoded' ? 'json' : $format);
		$mime             = "application/{$this->bodyFormat}";
		$this->setContentType($mime);

		if (!is_string($body) || $format === 'json-unencoded') {
			$formatter = $this->getClassByMime($mime);

			$body = $formatter->format($body);
		}

		return $body;
	}

	protected function getClassByMime(string $mime)
	{
		if (!array_key_exists($mime, $this->formatters)) {
			throw new \InvalidArgumentException('No Formatter defined for mime type: ' . $mime);
		}

		$class = $this->formatters[$mime];

		if (!class_exists($class)) {
			throw new \BadMethodCallException($class . ' is not a valid Formatter.');
		}
		return new $class();
	}

	public function noCache(): self
	{
		$this->removeHeader('Cache-control');

		$this->setHeader('Cache-control', ['no-store', 'max-age=0', 'no-cache']);

		return $this;
	}

	public function setCache(array $options = [])
	{
		if (empty($options)) {
			return $this;
		}

		$this->removeHeader('Cache-Control');
		$this->removeHeader('ETag');

		// ETag
		if (isset($options['etag'])) {
			$this->setHeader('ETag', $options['etag']);
			unset($options['etag']);
		}

		// Last Modified
		if (isset($options['last-modified'])) {
			$this->setLastModified($options['last-modified']);

			unset($options['last-modified']);
		}

		$this->setHeader('Cache-control', $options);

		return $this;
	}

	public function setLastModified($date)
	{
		if ($date instanceof \DateTime) {
			$date->setTimezone(new \DateTimeZone('UTC'));
			$this->setHeader('Last-Modified', $date->format('D, d M Y H:i:s') . ' GMT');
		} elseif (is_string($date)) {
			$this->setHeader('Last-Modified', $date);
		}

		return $this;
	}

	public function send()
	{
		// If we're enforcing a Content Security Policy,
		// we need to give it a chance to build out it's headers.
		if ($this->CSPEnabled === true) {
			$this->CSP->finalize($this);
		} else {
			$this->body = str_replace(['{csp-style-nonce}', '{csp-script-nonce}'], '', $this->body);
		}

		$this->sendHeaders();
		$this->sendCookies();
		$this->sendBody();

		return $this;
	}
	public function sendHeaders()
	{
		if ($this->pretend || headers_sent()) {
			return $this;
		}

		if (!isset($this->headers['Date']) && php_sapi_name() !== 'cli-server') {
			$this->setDate(\DateTime::createFromFormat('U', (string) time()));
		}

		// HTTP Status
		header(sprintf('HTTP/%s %s %s', $this->getProtocolVersion(), $this->statusCode, $this->reason), true, $this->statusCode);

		// Send all of our headers
		foreach ($this->getHeaders() as $name => $values) {
			header($name . ': ' . $this->getHeaderLine($name), true, $this->statusCode);
		}

		return $this;
	}

	public function sendBody()
	{
		echo $this->body;

		return $this;
	}

	public function getBody()
	{
		return $this->body;
	}

	public function redirect(string $uri, string $method = 'auto', int $code = null)
	{
		// Assume 302 status code response; override if needed
		if (empty($code)) {
			$code = 302;
		}

		// IIS environment likely? Use 'refresh' for better compatibility
		if ($method === 'auto' && isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false) {
			$method = 'refresh';
		}

		// override status code for HTTP/1.1 & higher
		// reference: http://en.wikipedia.org/wiki/Post/Redirect/Get
		if (isset($_SERVER['SERVER_PROTOCOL'], $_SERVER['REQUEST_METHOD']) && $this->getProtocolVersion() >= 1.1) {
			if ($method !== 'refresh') {
				$code = ($_SERVER['REQUEST_METHOD'] !== 'GET') ? 303 : 307;
			}
		}

		switch ($method) {
			case 'refresh':
				$this->setHeader('Refresh', '0;url=' . $uri);
				break;
			default:
				$this->setHeader('Location', $uri);
				break;
		}

		$this->setStatusCode($code);

		return $this;
	}

	public function setCookie(
		$name,
		$value = '',
		$expire = '',
		$domain = '',
		$path = '/',
		$prefix = '',
		$secure = false,
		$httponly = false
	) {
		if (is_array($name)) {
			// always leave 'name' in last place, as the loop will break otherwise, due to $$item
			foreach (['value', 'expire', 'domain', 'path', 'prefix', 'secure', 'httponly', 'name'] as $item) {
				if (isset($name[$item])) {
					$$item = $name[$item];
				}
			}
		}

		if ($prefix === '' && $this->cookiePrefix !== '') {
			$prefix = $this->cookiePrefix;
		}

		if ($domain === '' && $this->cookieDomain !== '') {
			$domain = $this->cookieDomain;
		}

		if ($path === '/' && $this->cookiePath !== '/') {
			$path = $this->cookiePath;
		}

		if ($secure === false && $this->cookieSecure === true) {
			$secure = $this->cookieSecure;
		}

		if ($httponly === false && $this->cookieHTTPOnly !== false) {
			$httponly = $this->cookieHTTPOnly;
		}

		if (!is_numeric($expire)) {
			$expire = time() - 86500;
		} else {
			$expire = ($expire > 0) ? time() + $expire : 0;
		}

		$this->cookies[] = [
			'name'     => $prefix . $name,
			'value'    => $value,
			'expires'  => $expire,
			'path'     => $path,
			'domain'   => $domain,
			'secure'   => $secure,
			'httponly' => $httponly,
		];

		return $this;
	}

	public function hasCookie(string $name, string $value = null, string $prefix = ''): bool
	{
		if ($prefix === '' && $this->cookiePrefix !== '') {
			$prefix = $this->cookiePrefix;
		}

		$name = $prefix . $name;

		foreach ($this->cookies as $cookie) {
			if ($cookie['name'] !== $name) {
				continue;
			}

			if ($value === null) {
				return true;
			}

			return $cookie['value'] === $value;
		}

		return false;
	}

	public function getCookie(string $name = null, string $prefix = '')
	{
		// if no name given, return them all
		if (empty($name)) {
			return $this->cookies;
		}

		if ($prefix === '' && $this->cookiePrefix !== '') {
			$prefix = $this->cookiePrefix;
		}

		$name = $prefix . $name;

		foreach ($this->cookies as $cookie) {
			if ($cookie['name'] === $name) {
				return $cookie;
			}
		}
		return null;
	}

	public function deleteCookie(string $name = '', string $domain = '', string $path = '/', string $prefix = '')
	{
		if (empty($name)) {
			return $this;
		}

		if ($prefix === '' && $this->cookiePrefix !== '') {
			$prefix = $this->cookiePrefix;
		}

		$name = $prefix . $name;

		$cookieHasFlag = false;
		foreach ($this->cookies as &$cookie) {
			if ($cookie['name'] === $name) {
				if (!empty($domain) && $cookie['domain'] !== $domain) {
					continue;
				}
				if (!empty($path) && $cookie['path'] !== $path) {
					continue;
				}
				$cookie['value']   = '';
				$cookie['expires'] = '';
				$cookieHasFlag     = true;
				break;
			}
		}

		if (!$cookieHasFlag) {
			$this->setCookie($name, '', '', $domain, $path, $prefix);
		}

		return $this;
	}

	protected function sendCookies()
	{
		if ($this->pretend) {
			return;
		}

		foreach ($this->cookies as $params) {
			// PHP cannot unpack array with string keys
			$params = array_values($params);

			setcookie(...$params);
		}
	}
}
