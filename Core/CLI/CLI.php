<?php
namespace NGUYEN\CLI;

use NGUYEN\CLI\CLIException;

class CLI {
	protected static $segments = [];

	protected static $lastWrite;

	protected static $options = [];

	protected static $foreground_colors = [
		'black'        => '0;30',
		'dark_gray'    => '1;30',
		'blue'         => '0;34',
		'dark_blue'    => '1;34',
		'light_blue'   => '1;34',
		'green'        => '0;32',
		'light_green'  => '1;32',
		'cyan'         => '0;36',
		'light_cyan'   => '1;36',
		'red'          => '0;31',
		'light_red'    => '1;31',
		'purple'       => '0;35',
		'light_purple' => '1;35',
		'light_yellow' => '0;33',
		'yellow'       => '1;33',
		'light_gray'   => '0;37',
		'white'        => '1;37',
	];

	protected static $background_colors = [
		'black'      => '40',
		'red'        => '41',
		'green'      => '42',
		'yellow'     => '43',
		'blue'       => '44',
		'magenta'    => '45',
		'cyan'       => '46',
		'light_gray' => '47',
	];

	public static function init() {
		static::$segments = [];
		static::$options  = [];

		static::parseCommandLine();
	}

	protected static function parseCommandLine()
	{
		echo "Run parse Command Line \n";
		// start picking segments off from #1, ignoring the invoking program
		for ($i = 1; $i < $_SERVER['argc']; $i ++)
		{
			// If there's no '-' at the beginning of the argument
			// then add it to our segments.
			if (mb_strpos($_SERVER['argv'][$i], '-') === false)
			{
				static::$segments[] = $_SERVER['argv'][$i];
				continue;
			}

			$arg   = str_replace('-', '', $_SERVER['argv'][$i]);
			$value = null;

			// if there is a following segment, and it doesn't start with a dash, it's a value.
			if (isset($_SERVER['argv'][$i + 1]) && mb_strpos($_SERVER['argv'][$i + 1], '-') !== 0)
			{
				$value = $_SERVER['argv'][$i + 1];
				$i ++;
			}
			echo json_encode(static::$segments);
			//static::$options[$arg] = $value;
		}
	}

	public static function getURI(): string
	{
		return implode('/', static::$segments);
	}

	public static function write(string $text = '', string $foreground = null, string $background = null)
	{
		if ($foreground || $background)
		{
			$text = static::color($text, $foreground, $background);
		}

		if (static::$lastWrite !== 'write')
		{
			$text              = PHP_EOL . $text;
			static::$lastWrite = 'write';
		}

		fwrite(STDOUT, $text . PHP_EOL);
	}

	public static function newLine(int $num = 1)
	{
		// Do it once or more, write with empty string gives us a new line
		for ($i = 0; $i < $num; $i ++)
		{
			static::write();
		}
	}

	public static function color(string $text, string $foreground, string $background = null, string $format = null): string
	{
		if (static::isWindows() && ! isset($_SERVER['ANSICON']))
		{
			// @codeCoverageIgnoreStart
			return $text;
			// @codeCoverageIgnoreEnd
		}

		if (! array_key_exists($foreground, static::$foreground_colors))
		{
			throw CLIException::forInvalidColor('foreground', $foreground);
		}

		if ($background !== null && ! array_key_exists($background, static::$background_colors))
		{
			throw CLIException::forInvalidColor('background', $background);
		}

		$string = "\033[" . static::$foreground_colors[$foreground] . 'm';

		if ($background !== null)
		{
			$string .= "\033[" . static::$background_colors[$background] . 'm';
		}

		if ($format === 'underline')
		{
			$string .= "\033[4m";
		}

		return $string . ($text . "\033[0m");
	}

	public static function isWindows(): bool
	{
		return stripos(PHP_OS, 'WIN') === 0;
	}
}