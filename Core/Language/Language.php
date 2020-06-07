<?php
namespace QTCS\Language;

use QTCS\Services\Services;

class Language
{
	protected $language = [];
	protected $locale;
	protected $intlSupport = false;
	protected $loadedFiles = [];

	public function __construct(string $locale)
	{
		$this->locale = $locale;

		if (class_exists('\MessageFormatter'))
		{
			$this->intlSupport = true;
		};
	}

	public function setLocale(string $locale = null)
	{
		if (! is_null($locale))
		{
			$this->locale = $locale;
		}

		return $this;
	}

	public function getLocale(): string
	{
		return $this->locale;
	}

	public function getLine(string $line, array $args = [])
	{
		// ignore requests with no file specified
		if (! strpos($line, '.'))
		{
			return $line;
		}

		// Parse out the file name and the actual alias.
		// Will load the language file and strings.
		[
			$file,
			$parsedLine,
		] = $this->parseLine($line, $this->locale);

		foreach (explode('.', $parsedLine) as $row)
		{
			if (! isset($current))
			{
				$current = $this->language[$this->locale][$file] ?? null;
			}

			$output = $current[$row] ?? null;
			if (is_array($output))
			{
				$current = $output;
			}
		}

		if ($output === null && strpos($this->locale, '-'))
		{
			[$locale] = explode('-', $this->locale, 2);

			[
				$file,
				$parsedLine,
			] = $this->parseLine($line, $locale);

			$output = $this->language[$locale][$file][$parsedLine] ?? null;
		}

		// if still not found, try English
		if (empty($output))
		{
			$this->parseLine($line, 'en');
			$output = $this->language['en'][$file][$parsedLine] ?? null;
		}

		$output = $output ?? $line;

		if (! empty($args))
		{
			$output = $this->formatMessage($output, $args);
		}

		return $output;
	}

	protected function parseLine(string $line, string $locale): array
	{
		$file = substr($line, 0, strpos($line, '.'));
		$line = substr($line, strlen($file) + 1);

		if (! isset($this->language[$locale][$file]) || ! array_key_exists($line, $this->language[$locale][$file]))
		{
			$this->load($file, $locale);
		}

		return [
			$file,
			$line,
		];
	}

	protected function formatMessage($message, array $args = [])
	{
		if (! $this->intlSupport || ! $args)
		{
			return $message;
		}

		if (is_array($message))
		{
			foreach ($message as $index => $value)
			{
				$message[$index] = $this->formatMessage($value, $args);
			}
			return $message;
		}

		return \MessageFormatter::formatMessage($this->locale, $message, $args);
	}

	protected function load(string $file, string $locale, bool $return = false)
	{
		if (! array_key_exists($locale, $this->loadedFiles))
		{
			$this->loadedFiles[$locale] = [];
		}

		if (in_array($file, $this->loadedFiles[$locale]))
		{
			// Don't load it more than once.
			return [];
		}

		if (! array_key_exists($locale, $this->language))
		{
			$this->language[$locale] = [];
		}

		if (! array_key_exists($file, $this->language[$locale]))
		{
			$this->language[$locale][$file] = [];
		}

		$path = "Language/{$locale}/{$file}.php";

		$lang = $this->requireFile($path);

		if ($return)
		{
			return $lang;
		}

		$this->loadedFiles[$locale][] = $file;

		// Merge our string
		$this->language[$locale][$file] = $lang;
	}

	protected function requireFile(string $path): array
	{
		$files   = Services::locator()->search($path);
		$strings = [];

		foreach ($files as $file)
		{
			if (is_file($file))
			{
				$strings[] = require $file;
			}
		}

		if (isset($strings[1]))
		{
			$strings = array_replace_recursive(...$strings);
		}
		elseif (isset($strings[0]))
		{
			$strings = $strings[0];
		}

		return $strings;
	}
}