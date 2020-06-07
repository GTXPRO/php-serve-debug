<?php
namespace QTCS\Security;

use QTCS\Http\IncomingRequest;
use QTCS\Http\RequestInterface;

class Security
{
	protected $CSRFHash;
	protected $CSRFExpire = 7200;
	protected $CSRFTokenName = 'CSRFToken';
	protected $CSRFHeaderName = 'CSRFToken';
	protected $CSRFCookieName = 'CSRFToken';
	protected $CSRFRegenerate = true;
	protected $cookiePath = '/';
	protected $cookieDomain = '';
	protected $cookieSecure = false;
	public $filenameBadChars = [
		'../',
		'<!--',
		'-->',
		'<',
		'>',
		"'",
		'"',
		'&',
		'$',
		'#',
		'{',
		'}',
		'[',
		']',
		'=',
		';',
		'?',
		'%20',
		'%22',
		'%3c', // <
		'%253c', // <
		'%3e', // >
		'%0e', // >
		'%28', // (
		'%29', // )
		'%2528', // (
		'%26', // &
		'%24', // $
		'%3f', // ?
		'%3b', // ;
		'%3d',       // =
	];

	public function __construct($config)
	{
		// Store our CSRF-related settings
		$this->CSRFExpire     = $config->CSRFExpire;
		$this->CSRFTokenName  = $config->CSRFTokenName;
		$this->CSRFHeaderName = $config->CSRFHeaderName;
		$this->CSRFCookieName = $config->CSRFCookieName;
		$this->CSRFRegenerate = $config->CSRFRegenerate;

		if (isset($config->cookiePrefix))
		{
			$this->CSRFCookieName = $config->cookiePrefix . $this->CSRFCookieName;
		}

		// Store cookie-related settings
		$this->cookiePath   = $config->cookiePath;
		$this->cookieDomain = $config->cookieDomain;
		$this->cookieSecure = $config->cookieSecure;

		$this->CSRFSetHash();

		unset($config);
	}

	/**
	 * @param IncomingRequest $request
	 */
	public function CSRFVerify(RequestInterface $request)
	{
		// If it's not a POST request we will set the CSRF cookie
		if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST')
		{
			return $this->CSRFSetCookie($request);
		}

		// Do the tokens exist in _POST, HEADER or optionally php:://input - json data
		$CSRFTokenValue = $_POST[$this->CSRFTokenName] ??
			(! is_null($request->getHeader($this->CSRFHeaderName)) && ! empty($request->getHeader($this->CSRFHeaderName)->getValue()) ?
				$request->getHeader($this->CSRFHeaderName)->getValue() :
				(! empty($request->getBody()) && ! empty($json = json_decode($request->getBody())) && json_last_error() === JSON_ERROR_NONE ?
					($json->{$this->CSRFTokenName} ?? null) :
					null));

		
		if (
			! isset($CSRFTokenValue, $_COOKIE[$this->CSRFCookieName]) || 
			$CSRFTokenValue !== $_COOKIE[$this->CSRFCookieName]
		)
		{
			throw new \RuntimeException("The action you requested is not allowed.");
		}

		// We kill this since we're done and we don't want to pollute the _POST array
		if (isset($_POST[$this->CSRFTokenName]))
		{
			unset($_POST[$this->CSRFTokenName]);
			$request->setGlobal('post', $_POST);
		}
		// We kill this since we're done and we don't want to pollute the JSON data
		elseif (isset($json->{$this->CSRFTokenName}))
		{
			unset($json->{$this->CSRFTokenName});
			$request->setBody(json_encode($json));
		}

		// Regenerate on every submission?
		if ($this->CSRFRegenerate)
		{
			// Nothing should last forever
			$this->CSRFHash = null;
			unset($_COOKIE[$this->CSRFCookieName]);
		}

		$this->CSRFSetHash();
		$this->CSRFSetCookie($request);

		log_message('info', 'CSRF token verified');

		return $this;
	}

	/**
	 * @param \QTCS\HTTP\IncomingRequest $request
	 */
	public function CSRFSetCookie(RequestInterface $request)
	{
		$expire        = time() + $this->CSRFExpire;
		$secure_cookie = (bool) $this->cookieSecure;

		if ($secure_cookie && ! $request->isSecure())
		{
			return false;
		}

		setcookie(
				$this->CSRFCookieName, $this->CSRFHash, $expire, $this->cookiePath, $this->cookieDomain, $secure_cookie, true                // Enforce HTTP only cookie for security
		);

		log_message('info', 'CSRF cookie sent');

		return $this;
	}

	public function getCSRFHash(): string
	{
		return $this->CSRFHash;
	}

	public function getCSRFTokenName(): string
	{
		return $this->CSRFTokenName;
	}

	protected function CSRFSetHash(): string
	{
		if ($this->CSRFHash === null)
		{
			// If the cookie exists we will use its value.
			// We don't necessarily want to regenerate it with
			// each page load since a page could contain embedded
			// sub-pages causing this feature to fail
			if (isset($_COOKIE[$this->CSRFCookieName]) && is_string($_COOKIE[$this->CSRFCookieName]) && preg_match('#^[0-9a-f]{32}$#iS', $_COOKIE[$this->CSRFCookieName]) === 1
			)
			{
				return $this->CSRFHash = $_COOKIE[$this->CSRFCookieName];
			}

			$rand           = random_bytes(16);
			$this->CSRFHash = bin2hex($rand);
		}

		return $this->CSRFHash;
	}

	public function sanitizeFilename(string $str, bool $relative_path = false): string
	{
		$bad = $this->filenameBadChars;

		if (! $relative_path)
		{
			$bad[] = './';
			$bad[] = '/';
		}

		$str = remove_invisible_characters($str, false);

		do
		{
			$old = $str;
			$str = str_replace($bad, '', $str);
		}
		while ($old !== $str);

		return stripslashes($str);
	}
}