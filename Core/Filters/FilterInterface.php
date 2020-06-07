<?php
namespace QTCS\Filters;

use QTCS\Http\RequestInterface;
use QTCS\Http\ResponseInterface;

interface FilterInterface {
	public function before(RequestInterface $request);
	public function after(RequestInterface $request, ResponseInterface $response);
}