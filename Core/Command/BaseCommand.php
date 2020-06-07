<?php
namespace QTCS\Command;

use Psr\Log\LoggerInterface;
use QTCS\CLI\CLI;

abstract class BaseCommand {
	protected $group;
	protected $name;
	protected $usage;
	protected $description;
	protected $options = [];
	protected $arguments = [];
	protected $logger;
	protected $commands;

	public function __construct(LoggerInterface $logger, Command $commands)
	{
		$this->logger   = $logger;
		$this->commands = $commands;
	}

	abstract public function run(array $params);

	protected function call(string $command, array $params = [])
	{
		// The CommandRunner will grab the first element
		// for the command name.
		array_unshift($params, $command);

		return $this->commands->index($params);
	}

	protected function showError(\Exception $e)
	{
		CLI::newLine();
		CLI::error($e->getMessage());
		CLI::write($e->getFile() . ' - ' . $e->getLine());
		CLI::newLine();
	}

	public function __get(string $key)
	{
		if (isset($this->$key))
		{
			return $this->$key;
		}

		return null;
	}

	public function __isset(string $key): bool
	{
		return isset($this->$key);
	}

	public function showHelp()
	{
		// 4 spaces instead of tab
		$tab = '   ';
		CLI::write(lang('CLI.helpDescription'), 'yellow');
		CLI::write($tab . $this->description);
		CLI::newLine();

		CLI::write(lang('CLI.helpUsage'), 'yellow');
		$usage = empty($this->usage) ? $this->name . ' [arguments]' : $this->usage;
		CLI::write($tab . $usage);
		CLI::newLine();

		$pad = max($this->getPad($this->options, 6), $this->getPad($this->arguments, 6));

		if (! empty($this->arguments))
		{
			CLI::write(lang('CLI.helpArguments'), 'yellow');
			foreach ($this->arguments as $argument => $description)
			{
				CLI::write($tab . CLI::color(str_pad($argument, $pad), 'green') . $description, 'yellow');
			}
			CLI::newLine();
		}

		if (! empty($this->options))
		{
			CLI::write(lang('CLI.helpOptions'), 'yellow');
			foreach ($this->options as $option => $description)
			{
				CLI::write($tab . CLI::color(str_pad($option, $pad), 'green') . $description, 'yellow');
			}
			CLI::newLine();
		}
	}

	public function getPad(array $array, int $pad): int
	{
		$max = 0;
		foreach ($array as $key => $value)
		{
			$max = max($max, strlen($key));
		}
		return $max + $pad;
	}
}