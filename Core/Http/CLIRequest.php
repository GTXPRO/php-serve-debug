<?php
namespace QTCS\Http;

use Config\App;

class CLIRequest extends Request {
	protected $segments = [];
	protected $options = [];
	protected $method = 'cli';

	public function __construct(App $config)
	{
		parent::__construct($config);

		// Don't terminate the script when the cli's tty goes away
		ignore_user_abort(true);

		$this->parseCommand();
	}

	public function getPath(): string
	{
		$path = implode('/', $this->segments);

		return empty($path) ? '' : $path;
	}

	public function getOptions(): array
	{
		return $this->options;
	}

	public function getSegments(): array
	{
		return $this->segments;
	}

	public function getOption(string $key)
	{
		return $this->options[$key] ?? null;
	}

	public function getOptionString(): string
	{
		if (empty($this->options))
		{
			return '';
		}

		$out = '';

		foreach ($this->options as $name => $value)
		{
			// If there's a space, we need to group
			// so it will pass correctly.
			if (strpos($value, ' ') !== false)
			{
				$value = '"' . $value . '"';
			}

			$out .= "-{$name} $value ";
		}

		return trim($out);
	}

	protected function parseCommand()
	{
		$options_found = false;

		$argc = $this->getServer('argc', FILTER_SANITIZE_NUMBER_INT);
		$argv = $this->getServer('argv');

		for ($i = 1; $i < $argc; $i ++)
		{
			if (! $options_found && strpos($argv[$i], '-') === false)
			{
				$this->segments[] = filter_var($argv[$i], FILTER_SANITIZE_STRING);
				continue;
			}

			$options_found = true;

			if (strpos($argv[$i], '-') !== 0)
			{
				continue;
			}

			$arg   = filter_var(str_replace('-', '', $argv[$i]), FILTER_SANITIZE_STRING);
			$value = null;

			// If the next item starts with a dash it's a value
			if (isset($argv[$i + 1]) && strpos($argv[$i + 1], '-') !== 0)
			{
				$value = filter_var($argv[$i + 1], FILTER_SANITIZE_STRING);
				$i ++;
			}

			$this->options[$arg] = $value;
		}
	}
}