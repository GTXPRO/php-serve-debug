<?php
namespace QTCS\Http;

use QTCS\Services\Services;

class RedirectResponse extends Response {
	public function to(string $uri, int $code = null, string $method = 'auto')
	{
		if (strpos($uri, 'http') !== 0)
		{
			$url = current_url(true)->resolveRelativeURI($uri);
			$uri = (string)$url;
		}

		return $this->redirect($uri, $method, $code);
	}

	public function route(string $route, array $params = [], int $code = 302, string $method = 'auto')
	{
		$routes = Services::routes(true);

		$route = $routes->reverseRoute($route, ...$params);

		if (! $route)
		{
			throw new \RuntimeException("{{$route}, string} route cannot be found while reverse-routing.");
		}

		return $this->redirect(site_url($route), $method, $code);
	}

	public function back(int $code = null, string $method = 'auto')
	{
		$this->ensureSession();

		return $this->redirect(previous_url(), $method, $code);
	}

	public function withInput()
	{
		$session = $this->ensureSession();

		$input = [
			'get'  => $_GET ?? [],
			'post' => $_POST ?? [],
		];

		$session->setFlashdata('_ci_old_input', $input);

		$validator = Services::validation();
		if (! empty($validator->getErrors()))
		{
			$session->setFlashdata('_ci_validation_errors', serialize($validator->getErrors()));
		}

		return $this;
	}

	public function with(string $key, $message)
	{
		$session = $this->ensureSession();

		$session->setFlashdata($key, $message);

		return $this;
	}

	protected function ensureSession()
	{
		return Services::session();
	}
}