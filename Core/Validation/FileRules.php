<?php
namespace QTCS\Validation;

use QTCS\Http\RequestInterface;
use QTCS\Services\Services;

class FileRules {
	protected $request;

	public function __construct(RequestInterface $request = null)
	{
		if (is_null($request))
		{
			$request = Services::request();
		}

		$this->request = $request;
	}

	public function uploaded(string $blank = null, string $name): bool
	{
		$file = $this->request->getFile($name);

		if (is_null($file))
		{
			return false;
		}

		if (ENVIRONMENT === 'testing')
		{
			return $file->getError() === 0;
		}

		return $file->isValid();
	}

	public function max_size(string $blank = null, string $params): bool
	{
		// Grab the file name off the top of the $params
		// after we split it.
		$params = explode(',', $params);
		$name   = array_shift($params);

		if (! ($files = $this->request->getFileMultiple($name)))
		{
			$files = [$this->request->getFile($name)];
		}

		foreach ($files as $file)
		{
			if (is_null($file))
			{
				return false;
			}

			if ($file->getError() === UPLOAD_ERR_NO_FILE)
			{
				return true;
			}

			if ($file->getSize() / 1024 > $params[0])
			{
				return false;
			}
		}

		return true;
	}

	public function is_image(string $blank = null, string $params): bool
	{
		// Grab the file name off the top of the $params
		// after we split it.
		$params = explode(',', $params);
		$name   = array_shift($params);

		if (! ($files = $this->request->getFileMultiple($name)))
		{
			$files = [$this->request->getFile($name)];
		}

		foreach ($files as $file)
		{
			if (is_null($file))
			{
				return false;
			}

			if ($file->getError() === UPLOAD_ERR_NO_FILE)
			{
				return true;
			}

			// We know that our mimes list always has the first mime
			// start with `image` even when then are multiple accepted types.
			$type = \Config\Mimes::guessTypeFromExtension($file->getExtension());

			if (mb_strpos($type, 'image') !== 0)
			{
				return false;
			}
		}

		return true;
	}

	public function mime_in(string $blank = null, string $params): bool
	{
		// Grab the file name off the top of the $params
		// after we split it.
		$params = explode(',', $params);
		$name   = array_shift($params);

		if (! ($files = $this->request->getFileMultiple($name)))
		{
			$files = [$this->request->getFile($name)];
		}

		foreach ($files as $file)
		{
			if (is_null($file))
			{
				return false;
			}

			if ($file->getError() === UPLOAD_ERR_NO_FILE)
			{
				return true;
			}

			if (! in_array($file->getMimeType(), $params))
			{
				return false;
			}
		}

		return true;
	}

	public function ext_in(string $blank = null, string $params): bool
	{
		// Grab the file name off the top of the $params
		// after we split it.
		$params = explode(',', $params);
		$name   = array_shift($params);

		if (! ($files = $this->request->getFileMultiple($name)))
		{
			$files = [$this->request->getFile($name)];
		}

		foreach ($files as $file)
		{
			if (is_null($file))
			{
				return false;
			}

			if ($file->getError() === UPLOAD_ERR_NO_FILE)
			{
				return true;
			}

			if (! in_array($file->getExtension(), $params))
			{
				return false;
			}
		}

		return true;
	}

	public function max_dims(string $blank = null, string $params): bool
	{
		// Grab the file name off the top of the $params
		// after we split it.
		$params = explode(',', $params);
		$name   = array_shift($params);

		if (! ($files = $this->request->getFileMultiple($name)))
		{
			$files = [$this->request->getFile($name)];
		}

		foreach ($files as $file)
		{
			if (is_null($file))
			{
				return false;
			}

			if ($file->getError() === UPLOAD_ERR_NO_FILE)
			{
				return true;
			}

			// Get Parameter sizes
			$allowedWidth  = $params[0] ?? 0;
			$allowedHeight = $params[1] ?? 0;

			// Get uploaded image size
			$info       = getimagesize($file->getTempName());
			$fileWidth  = $info[0];
			$fileHeight = $info[1];

			if ($fileWidth > $allowedWidth || $fileHeight > $allowedHeight)
			{
				return false;
			}
		}

		return true;
	}
}