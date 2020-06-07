<?php
namespace QTCS;

class Loader {
	const VERSION = '1.0';

	protected $config;

	protected $response;

	protected $request;

	public function __construct($config) {
		$this->config = $config;
	}

	public function initialize() {
		date_default_timezone_set($this->config->appTimezone ?? 'Asia/Ho_Chi_Minh');

		$this->detectEnvironment();

		echo "Loader initialize \n";
	}

	public function run() {
		$this->forceSecureAccess();

		try {
			return $this->handler();
		} catch(\RuntimeException $e) {
			echo "Run loader failed !!!";
		}
	}

	protected function handler() {
		if (! defined('DEBUG')) {
			echo "Run with browser";
		}

		$this->startController();

		return $this->response;
	}

	protected function detectEnvironment() {
		echo "Run detect environment \n";
	}

	protected function startController() {
		echo "startController \n";
	}

	protected function getResponseObject()
	{
		$this->response = Services::response($this->config);

		if (! is_cli() || ENVIRONMENT === 'testing')
		{
			$this->response->setProtocolVersion($this->request->getProtocolVersion());
		}

		// Assume success until proven otherwise.
		$this->response->setStatusCode(200);
	}

	protected function forceSecureAccess($duration = 31536000)
	{
		if (isset($this->config->forceGlobalSecureRequests) && $this->config->forceGlobalSecureRequests !== true)
		{
			return;
		}

		force_https($duration, $this->request, $this->response);
	}
}