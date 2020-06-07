<?php
namespace QTCS;

use QTCS\Http\RequestInterface;
use QTCS\Http\ResponseInterface;
use Psr\Log\LoggerInterface;

class Controller {
	protected $helpers = [];
	protected $request;
	protected $response;
	protected $logger;
	protected $forceHTTPS = 0;
	protected $validator;

	public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
	{
		$this->request  = $request;
		$this->response = $response;
		$this->logger   = $logger;
		
		if ($this->forceHTTPS > 0)
		{
			$this->forceHTTPS($this->forceHTTPS);
		}

		$this->loadHelpers();
	}

	protected function forceHTTPS(int $duration = 31536000)
	{
		force_https($duration, $this->request, $this->response);
	}

	protected function loadHelpers()
	{
		if (empty($this->helpers))
		{
			return;
		}

		foreach ($this->helpers as $helper)
		{
			// helper($helper);
		}
	}
}