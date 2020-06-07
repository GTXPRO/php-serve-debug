<?php
namespace Config;

class App {
	public $baseURL = 'http://localhost:9999';
	public $indexPage = 'index.php';

	public $uriProtocol = 'REQUEST_URI';
	public $defaultLocale = 'en';
	public $negotiateLocale = false;
	public $supportedLocales = ['en'];
	public $appTimezone = 'Asia/Ho_Chi_Minh';
	public $charset = 'UTF-8';
	public $forceGlobalSecureRequests = false;

	public $sessionDriver            = 'QTCS\Session\Handlers\FileHandler';
	public $sessionCookieName        = 'debug_session';
	public $sessionExpiration        = 7200;
	public $sessionSavePath          = WRITE_PATH . 'session';
	public $sessionMatchIP           = false;
	public $sessionTimeToUpdate      = 300;
	public $sessionRegenerateDestroy = false;

	public $cookiePrefix = '';
	public $cookieDomain = '';
	public $cookiePath = '';
	public $cookieSecure = '';
	public $cookieHTTPOnly = '';

	public $proxyIPs = '';

	public $CSRFTokenName  = 'csrf_test_name';
	public $CSRFHeaderName = 'X-CSRF-TOKEN';
	public $CSRFCookieName = 'csrf_cookie_name';
	public $CSRFExpire     = 7200;
	public $CSRFRegenerate = true;
	public $CSRFRedirect   = true;

	public $CSPEnabled = false;
}