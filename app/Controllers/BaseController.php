<?php
namespace App\Controllers;

use QTCS\Controller;

class BaseController extends Controller {
	protected $helpers = [];
	public function initController(
		\QTCS\Http\RequestInterface $request,
		\QTCS\Http\ResponseInterface $response,
		\Psr\Log\LoggerInterface $logger
	)
	{
		parent::initController($request, $response, $logger);
	}
}