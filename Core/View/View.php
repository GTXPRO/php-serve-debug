<?php
namespace QTCS\View;

use Psr\Log\LoggerInterface;
use QTCS\Services\Services;

class View implements RendererInterface {
	protected $data = [];
	protected $tempData = null;
	protected $viewPath;
	protected $renderVars = [];
	protected $loader;
	protected $logger;
	protected $debug = false;
	protected $performanceData = [];
	protected $config;
	protected $saveData;
	protected $viewsCount = 0;
	protected $layout;
	protected $sections = [];
	protected $currentSection;

	public function __construct($config, string $viewPath = null, $loader = null, bool $debug = null, LoggerInterface $logger = null)
	{
		$this->config   = $config;
		$this->viewPath = rtrim($viewPath, '/ ') . '/';
		$this->loader   = is_null($loader) ? Services::locator() : $loader;
		$this->logger   = is_null($logger) ? Services::logger() : $logger;
		$this->debug    = is_null($debug) ? DEBUG : $debug;
		$this->saveData = $config->saveData ?? null;
	}

	public function render(string $view, array $options = null, bool $saveData = null): string
	{
		$this->renderVars['start'] = microtime(true);

		// clean it unless we mean it to.
		if (is_null($saveData))
		{
			$saveData = $this->saveData;
		}
		$fileExt                     = pathinfo($view, PATHINFO_EXTENSION);
		$realPath                    = empty($fileExt) ? $view . '.php' : $view; // allow Views as .html, .tpl, etc (from CI3)
		$this->renderVars['view']    = $realPath;
		$this->renderVars['options'] = $options;

		// Was it cached?
		if (isset($this->renderVars['options']['cache']))
		{
			$this->renderVars['cacheName'] = $this->renderVars['options']['cache_name'] ?? str_replace('.php', '', $this->renderVars['view']);

			// if ($output = cache($this->renderVars['cacheName']))
			// {
			// 	$this->logPerformance($this->renderVars['start'], microtime(true), $this->renderVars['view']);
			// 	return $output;
			// }
		}
	
		$this->renderVars['file'] = $this->viewPath . $this->renderVars['view'];

		if (! is_file($this->renderVars['file']))
		{
			$this->renderVars['file'] = $this->loader->locateFile($this->renderVars['view'], 'Views', empty($fileExt) ? 'php' : $fileExt);
		}
		
		// locateFile will return an empty string if the file cannot be found.
		if (empty($this->renderVars['file']))
		{
			throw new \RuntimeException("Invalid file: {{$this->renderVars['view']}}");
		}

		// Make our view data available to the view.

		if (is_null($this->tempData))
		{
			$this->tempData = $this->data;
		}

		extract($this->tempData);

		if ($saveData)
		{
			$this->data = $this->tempData;
		}
		
		ob_start();
		include($this->renderVars['file']); // PHP will be processed
		$output = ob_get_contents();
		@ob_end_clean();

		if (! is_null($this->layout) && empty($this->currentSection))
		{
			$layoutView   = $this->layout;
			$this->layout = null;
			$output       = $this->render($layoutView, $options, $saveData);
		}

		$this->logPerformance($this->renderVars['start'], microtime(true), $this->renderVars['view']);

		if ($this->debug && (! isset($options['debug']) || $options['debug'] === true))
		{
			// $toolbarCollectors = config(\Config\Toolbar::class)->collectors;

			// if (in_array(\CodeIgniter\Debug\Toolbar\Collectors\Views::class, $toolbarCollectors))
			// {
			// 	// Clean up our path names to make them a little cleaner
			// 	foreach (['APPPATH', 'SYSTEMPATH', 'ROOTPATH'] as $path)
			// 	{
			// 		if (strpos($this->renderVars['file'], constant($path)) === 0)
			// 		{
			// 			$this->renderVars['file'] = str_replace(constant($path), $path . '/', $this->renderVars['file']);
			// 			break;
			// 		}
			// 	}
			// 	$this->renderVars['file'] = ++$this->viewsCount . ' ' . $this->renderVars['file'];
			// 	$output                   = '<!-- DEBUG-VIEW START ' . $this->renderVars['file'] . ' -->' . PHP_EOL
			// 		. $output . PHP_EOL
			// 		. '<!-- DEBUG-VIEW ENDED ' . $this->renderVars['file'] . ' -->' . PHP_EOL;
			// }
		}

		// Should we cache?
		if (isset($this->renderVars['options']['cache']))
		{
			echo "Vao";
			// cache()->save($this->renderVars['cacheName'], $output, (int) $this->renderVars['options']['cache']);
		}

		$this->tempData = null;
		
		return $output;
	}

	public function renderString(string $view, array $options = null, bool $saveData = null): string
	{
		$start = microtime(true);

		if (is_null($saveData))
		{
			$saveData = $this->saveData;
		}

		if (is_null($this->tempData))
		{
			$this->tempData = $this->data;
		}

		extract($this->tempData);

		if ($saveData)
		{
			$this->data = $this->tempData;
		}

		ob_start();
		$incoming = '?>' . $view;
		eval($incoming);
		$output = ob_get_contents();
		@ob_end_clean();

		$this->logPerformance($start, microtime(true), $this->excerpt($view));

		$this->tempData = null;

		return $output;
	}

	public function excerpt(string $string, int $length = 20): string
	{
		return (strlen($string) > $length) ? substr($string, 0, $length - 3) . '...' : $string;
	}

	public function setData(array $data = [], string $context = null): RendererInterface
	{
		if (! empty($context))
		{
			$data = \esc($data, $context);
		}

		$this->tempData = $this->tempData ?? $this->data;
		$this->tempData = array_merge($this->tempData, $data);

		return $this;
	}

	public function setVar(string $name, $value = null, string $context = null): RendererInterface
	{
		if (! empty($context))
		{
			$value = \esc($value, $context);
		}

		$this->tempData        = $this->tempData ?? $this->data;
		$this->tempData[$name] = $value;

		return $this;
	}

	public function resetData(): RendererInterface
	{
		$this->data = [];

		return $this;
	}

	public function getData(): array
	{
		return is_null($this->tempData) ? $this->data : $this->tempData;
	}

	public function extend(string $layout)
	{
		$this->layout = $layout;
	}

	public function section(string $name)
	{
		$this->currentSection = $name;

		ob_start();
	}

	public function endSection()
	{
		$contents = ob_get_clean();

		if (empty($this->currentSection))
		{
			throw new \RuntimeException('View themes, no current section.');
		}

		// Ensure an array exists so we can store multiple entries for this.
		if (! array_key_exists($this->currentSection, $this->sections))
		{
			$this->sections[$this->currentSection] = [];
		}
		$this->sections[$this->currentSection][] = $contents;

		$this->currentSection = null;
	}

	public function renderSection(string $sectionName)
	{
		if (! isset($this->sections[$sectionName]))
		{
			echo '';

			return;
		}

		foreach ($this->sections[$sectionName] as $key => $contents)
		{
			echo $contents;
			unset($this->sections[$sectionName][$key]);
		}
	}

	public function include(string $view, array $options = null, $saveData = true): string
	{
		return $this->render($view, $options, $saveData);
	}

	public function getPerformanceData(): array
	{
		return $this->performanceData;
	}

	protected function logPerformance(float $start, float $end, string $view)
	{
		if (! $this->debug)
		{
			return;
		}

		$this->performanceData[] = [
			'start' => $start,
			'end'   => $end,
			'view'  => $view,
		];
	}
}