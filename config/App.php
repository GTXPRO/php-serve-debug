<?php
namespace Config;

class App {
	public $baseURL = 'http://localhost:9999';
	public $indexPage = 'index.php';

	public $uriProtocol = 'REQUEST_URI';
	public $defaultLocale = 'en';
	public $negotiateLocale = false;
	public $supportedLocales = ['en'];

	public $cookiePrefix = '';
	public $cookieDomain = '';
	public $cookiePath = '';
	public $cookieSecure = '';
	public $cookieHTTPOnly = '';

	public $proxyIPs = '';

	public $CSPEnabled = false;
}