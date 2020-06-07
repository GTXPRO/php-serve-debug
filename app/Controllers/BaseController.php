<?php
namespace App\Controller;

use Psr\Log\LoggerInterface;
use QTCS\Controller;
use QTCS\Http\RequestInterface;
use QTCS\Http\ResponseInterface;

class BaseController extends Controller {
	protected $helpers = [];
	public function initController(
		RequestInterface $request,
		ResponseInterface $response,
		LoggerInterface $logger
	)
	{
		parent::initController($request, $response, $logger);
	}
}