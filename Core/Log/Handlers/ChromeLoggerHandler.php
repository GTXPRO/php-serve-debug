<?php

namespace QTCS\Log\Handlers;

use QTCS\HTTP\ResponseInterface;
use QTCS\Services\Services;

class ChromeLoggerHandler extends BaseHandler implements HandlerInterface
{
	const VERSION = 1.0;
	protected $backtraceLevel = 0;

	protected $json = [
		'version' => self::VERSION,
		'columns' => [
			'log',
			'backtrace',
			'type',
		],
		'rows'    => [],
	];

	protected $header = 'X-ChromeLogger-Data';

	protected $levels = [
		'emergency' => 'error',
		'alert'     => 'error',
		'critical'  => 'error',
		'error'     => 'error',
		'warning'   => 'warn',
		'notice'    => 'warn',
		'info'      => 'info',
		'debug'     => 'info',
	];

	public function __construct(array $config = [])
	{
		parent::__construct($config);

		$request = Services::request(null, true);

		$this->json['request_uri'] = (string) $request->uri;

	}

	public function handle($level, $message): bool
	{
		// Format our message
		$message = $this->format($message);

		// Generate Backtrace info
		$backtrace = debug_backtrace(false, $this->backtraceLevel);
		$backtrace = end($backtrace);

		$backtraceMessage = 'unknown';
		if (isset($backtrace['file']) && isset($backtrace['line']))
		{
			$backtraceMessage = $backtrace['file'] . ':' . $backtrace['line'];
		}

		// Default to 'log' type.
		$type = '';

		if (array_key_exists($level, $this->levels))
		{
			$type = $this->levels[$level];
		}

		$this->json['rows'][] = [
			[$message],
			$backtraceMessage,
			$type,
		];

		$this->sendLogs();

		return true;
	}

	protected function format($object)
	{
		if (! is_object($object))
		{
			return $object;
		}

		// @todo Modify formatting of objects once we can view them in browser.
		$objectArray = (array) $object;

		$objectArray['___class_name'] = get_class($object);

		return $objectArray;
	}

	public function sendLogs(ResponseInterface &$response = null)
	{
		if (is_null($response))
		{
			$response = Services::response(null, true);
		}

		$data = base64_encode(utf8_encode(json_encode($this->json)));

		$response->setHeader($this->header, $data);
	}

}
