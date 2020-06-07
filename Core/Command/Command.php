<?php
namespace QTCS\Command;

use QTCS\CLI\CLI;
use QTCS\Controller;
use QTCS\Services\Services;

class Command extends Controller {
	protected $commands = [];
	protected $logger;

	public function _remap($method, ...$params)
	{
		// The first param is usually empty, so scrap it.
		if (empty($params[0]))
		{
			array_shift($params);
		}

		return $this->index($params);
	}

	public function index(array $params)
	{
		$command = array_shift($params);

		$this->createCommandList();

		if (is_null($command))
		{
			$command = 'list';
		}
		
		return $this->runCommand($command, $params);
	}

	protected function runCommand(string $command, array $params)
	{
		if (! isset($this->commands[$command]))
		{
			CLI::error("Command \"{$command}\" not found.");
			CLI::newLine();
			return;
		}

		$className = $this->commands[$command]['class'];
		$class     = new $className($this->logger, $this);

		return $class->run($params);
	}

	protected function createCommandList()
	{
		$files = Services::locator()->listFiles('Command/');

		if (empty($files))
		{
			return;
		}

		// Loop over each file checking to see if a command with that
		// alias exists in the class. If so, return it. Otherwise, try the next.
		foreach ($files as $file)
		{
			$className = Services::locator()->findQualifiedNameFromPath($file);
			if (empty($className) || ! class_exists($className))
			{
				continue;
			}

			$class = new \ReflectionClass($className);

			if (! $class->isInstantiable() || ! $class->isSubclassOf(BaseCommand::class))
			{
				continue;
			}

			$class = new $className($this->logger, $this);

			// Store it!
			if ($class->group !== null)
			{
				$this->commands[$class->name] = [
					'class'       => $className,
					'file'        => $file,
					'group'       => $class->group,
					'description' => $class->description,
				];
			}

			$class = null;
			unset($class);
		}

		asort($this->commands);
	}
}