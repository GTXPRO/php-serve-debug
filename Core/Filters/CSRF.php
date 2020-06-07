<?php
namespace QTCS\Filters;

use QTCS\Http\RequestInterface;
use QTCS\Http\ResponseInterface;
use QTCS\Services\Services;

class CSRF implements FilterInterface {
	/**
	 * @param \QTCS\HTTP\IncomingRequest $request
	 */
	public function before(RequestInterface $request)
	{
		if ($request->isCLI())
		{
			return;
		}

		$security = Services::security();

		try
		{
			$security->CSRFVerify($request);
		}
		catch (\RuntimeException $e)
		{
			$app = new \Config\App();
			if ($app->CSRFRedirect && ! $request->isAJAX())
			{
				return redirect()->back()->with('error', $e->getMessage());
			}

			throw $e;
		}
	}

	public function after(RequestInterface $request, ResponseInterface $response)
	{
	}
}