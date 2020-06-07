<?php
namespace QTCS\Command\Server;

use QTCS\CLI\CLI;
use QTCS\Command\BaseCommand;

class Serve extends BaseCommand {
	protected $minPHPVersion = '7.2';
	protected $group = 'QTCS';
	protected $name = 'serve';
	protected $description = 'Launches the QTCS PHP-Development Server.';
	protected $usage = 'serve';
	protected $arguments = [];
	protected $portOffset = 0;
	protected $tries = 10;
	protected $options = [
		'-php'  => 'The PHP Binary [default: "PHP_BINARY"]',
		'-host' => 'The HTTP Host [default: "localhost"]',
		'-port' => 'The HTTP Host Port [default: "8080"]',
	];

	public function run(array $params)
	{
		// Valid PHP Version?
		if (phpversion() < $this->minPHPVersion)
		{
			// @codeCoverageIgnoreStart
			die('Your PHP version must be ' . $this->minPHPVersion .
				' or higher to run CodeIgniter. Current version: ' . phpversion());
			// @codeCoverageIgnoreEnd
		}

		// Collect any user-supplied options and apply them.
		$php  = escapeshellarg(CLI::getOption('php') ?? PHP_BINARY);
		$host = CLI::getOption('host') ?? 'localhost';
		$port = (int) (CLI::getOption('port') ?? '9999') + $this->portOffset;

		// Get the party started.
		CLI::write('CodeIgniter development server started on http://' . $host . ':' . $port, 'green');
		CLI::write('Press Control-C to stop.');

		// Set the Front Controller path as Document Root.
		$docroot = escapeshellarg(PUBLIC_PATH);

		// Mimic Apache's mod_rewrite functionality with user settings.
		$rewrite = escapeshellarg(__DIR__ . '/rewrite.php');

		// Call PHP's built-in webserver, making sure to set our
		// base path to the public folder, and to use the rewrite file
		// to ensure our environment is set and it simulates basic mod_rewrite.
		passthru($php . ' -S ' . $host . ':' . $port . ' -t ' . $docroot . ' ' . $rewrite, $status);

		if ($status && $this->portOffset < $this->tries)
		{
			$this->portOffset += 1;

			$this->run($params);
		}
	}
}