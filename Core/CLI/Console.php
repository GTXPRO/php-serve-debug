<?php
namespace QTCS\CLI;

use QTCS\Loader;

class Console {
	protected $app;

	public function __construct(Loader $app) {
		$this->app = $app;
	}

	public function run() {
		$path = CLI::getURI() ?: 'list';
		echo "Run PHP CLI: `{$path}` \n";

		return $this->app->run();
	}

	public function show()
	{
		CLI::newLine(1);
		CLI::write(CLI::color('QTCS CLI Tool', 'red')
				. ' - Version ' . Loader::VERSION
				. ' - Server-Time: ' . date('Y-m-d H:i:sa'));
		CLI::newLine(1);
	}
}