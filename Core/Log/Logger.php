<?php
namespace QTCS\Log;

use Psr\Log\LoggerInterface;

class Logger implements LoggerInterface {
	protected $logLevels = [
		'emergency' => 1,
		'alert'     => 2,
		'critical'  => 3,
		'error'     => 4,
		'warning'   => 5,
		'notice'    => 6,
		'info'      => 7,
		'debug'     => 8,
	];

	protected $loggableLevels = [];
	protected $filePermissions = 0644;
	protected $dateFormat = 'Y-m-d H:i:s';
	protected $fileExt;
	protected $handlers = [];
	protected $handlerConfig = [];
	public $logCache;
	protected $cacheLogs = false;

	public function __construct($config, bool $debug = DEBUG)
	{
		$this->loggableLevels = is_array($config->threshold) ? $config->threshold : range(1, (int) $config->threshold);

		// Now convert loggable levels to strings.
		// We only use numbers to make the threshold setting convenient for users.
		if ($this->loggableLevels)
		{
			$temp = [];
			foreach ($this->loggableLevels as $level)
			{
				$temp[] = array_search((int) $level, $this->logLevels);
			}

			$this->loggableLevels = $temp;
			unset($temp);
		}

		$this->dateFormat = $config->dateFormat ?? $this->dateFormat;

		if (! is_array($config->handlers) || empty($config->handlers))
		{
			throw new \RuntimeException("{LoggerConfig} must provide at least one Handler.");
		}

		// Save the handler configuration for later.
		// Instances will be created on demand.
		$this->handlerConfig = $config->handlers;

		$this->cacheLogs = $debug;
		if ($this->cacheLogs)
		{
			$this->logCache = [];
		}
	}

	public function emergency($message, array $context = []): bool
	{
		return $this->log('emergency', $message, $context);
	}

	public function alert($message, array $context = []): bool
	{
		return $this->log('alert', $message, $context);
	}

	public function critical($message, array $context = []): bool
	{
		return $this->log('critical', $message, $context);
	}

	public function error($message, array $context = []): bool
	{
		return $this->log('error', $message, $context);
	}

	public function warning($message, array $context = []): bool
	{
		return $this->log('warning', $message, $context);
	}

	public function notice($message, array $context = []): bool
	{
		return $this->log('notice', $message, $context);
	}

	public function info($message, array $context = []): bool
	{
		return $this->log('info', $message, $context);
	}

	public function debug($message, array $context = []): bool
	{
		return $this->log('debug', $message, $context);
	}

	public function log($level, $message, array $context = []): bool
	{
		if (is_numeric($level))
		{
			$level = array_search((int) $level, $this->logLevels);
		}

		// Is the level a valid level?
		if (! array_key_exists($level, $this->logLevels))
		{
			throw LogException::forInvalidLogLevel($level);
		}

		// Does the app want to log this right now?
		if (! in_array($level, $this->loggableLevels))
		{
			return false;
		}

		// Parse our placeholders
		$message = $this->interpolate($message, $context);

		if (! is_string($message))
		{
			$message = print_r($message, true);
		}

		if ($this->cacheLogs)
		{
			$this->logCache[] = [
				'level' => $level,
				'msg'   => $message,
			];
		}

		foreach ($this->handlerConfig as $className => $config)
		{
			if (! array_key_exists($className, $this->handlers))
			{
				$this->handlers[$className] = new $className($config);
			}

			$handler = $this->handlers[$className];

			if (! $handler->canHandle($level))
			{
				continue;
			}

			// If the handler returns false, then we
			// don't execute any other handlers.
			if (! $handler->setDateFormat($this->dateFormat)->handle($level, $message))
			{
				break;
			}
		}

		return true;
	}

	protected function interpolate($message, array $context = [])
	{
		if (! is_string($message))
		{
			return $message;
		}

		// build a replacement array with braces around the context keys
		$replace = [];

		foreach ($context as $key => $val)
		{
			// Verify that the 'exception' key is actually an exception
			// or error, both of which implement the 'Throwable' interface.
			if ($key === 'exception' && $val instanceof \Throwable)
			{
				$val = $val->getMessage() . ' ' . $this->cleanFileNames($val->getFile()) . ':' . $val->getLine();
			}

			// todo - sanitize input before writing to file?
			$replace['{' . $key . '}'] = $val;
		}

		// Add special placeholders
		$replace['{post_vars}'] = '$_POST: ' . print_r($_POST, true);
		$replace['{get_vars}']  = '$_GET: ' . print_r($_GET, true);
		$replace['{env}']       = ENVIRONMENT;

		// Allow us to log the file/line that we are logging from
		if (strpos($message, '{file}') !== false)
		{
			list($file, $line) = $this->determineFile();

			$replace['{file}'] = $file;
			$replace['{line}'] = $line;
		}

		// Match up environment variables in {env:foo} tags.
		if (strpos($message, 'env:') !== false)
		{
			preg_match('/env:[^}]+/', $message, $matches);

			if ($matches)
			{
				foreach ($matches as $str)
				{
					$key                 = str_replace('env:', '', $str);
					$replace["{{$str}}"] = $_ENV[$key] ?? 'n/a';
				}
			}
		}

		if (isset($_SESSION))
		{
			$replace['{session_vars}'] = '$_SESSION: ' . print_r($_SESSION, true);
		}

		// interpolate replacement values into the message and return
		return strtr($message, $replace);
	}

	public function determineFile(): array
	{
		$logFunctions = [
			'log_message',
			'log',
			'error',
			'debug',
			'info',
			'warning',
			'critical',
			'emergency',
			'alert',
			'notice',
		];

		// Generate Backtrace info
		$trace = \debug_backtrace(false);

		// So we search from the bottom (earliest) of the stack frames
		$stackFrames = \array_reverse($trace);

		// Find the first reference to a Logger class method
		foreach ($stackFrames as $frame)
		{
			if (\in_array($frame['function'], $logFunctions))
			{
				$file = isset($frame['file']) ? $this->cleanFileNames($frame['file']) : 'unknown';
				$line = $frame['line'] ?? 'unknown';
				return [
					$file,
					$line,
				];
			}
		}

		return [
			'unknown',
			'unknown',
		];
	}

	protected function cleanFileNames(string $file): string
	{
		$file = str_replace(APP_PATH, 'APP_PATH/', $file);
		$file = str_replace(CORE_PATH, 'CORE_PATH/', $file);

		return str_replace(ROOT_PATH, 'ROOT_PATH/', $file);
	}
}