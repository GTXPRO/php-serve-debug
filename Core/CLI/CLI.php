<?php
namespace QTCS\CLI;

use QTCS\CLI\CLIException;
use QTCS\Services\Services;

class CLI {
	public static $readline_support = false;
	public static $wait_msg = 'Press any key to continue...';
	protected static $initialized = false;
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
		static::$readline_support = extension_loaded('readline');
		static::$segments = [];
		static::$options  = [];

		static::parseCommandLine();

		static::$initialized = true;
	}

	public static function input(string $prefix = null): string
	{
		if (static::$readline_support)
		{
			return readline($prefix);
		}

		echo $prefix;

		return fgets(STDIN);
	}

	public static function prompt(string $field, $options = null, string $validation = null): string
	{
		$extra_output = '';
		$default      = '';

		if (is_string($options))
		{
			$extra_output = ' [' . static::color($options, 'white') . ']';
			$default      = $options;
		}

		if (is_array($options) && $options)
		{
			$opts                 = $options;
			$extra_output_default = static::color($opts[0], 'white');

			unset($opts[0]);

			if (empty($opts))
			{
				$extra_output = $extra_output_default;
			}
			else
			{
				$extra_output = ' [' . $extra_output_default . ', ' . implode(', ', $opts) . ']';
				$validation  .= '|in_list[' . implode(',', $options) . ']';
				$validation   = trim($validation, '|');
			}

			$default = $options[0];
		}

		fwrite(STDOUT, $field . $extra_output . ': ');

		// Read the input from keyboard.
		$input = trim(static::input()) ?: $default;

		if (isset($validation))
		{
			while (! static::validate($field, $input, $validation))
			{
				$input = static::prompt($field, $options, $validation);
			}
		}

		return empty($input) ? '' : $input;
	}

	protected static function validate(string $field, string $value, string $rules): bool
	{
		$validation = Services::validation(null, false);
		$validation->setRule($field, null, $rules);
		$validation->run([$field => $value]);

		if ($validation->hasError($field))
		{
			static::error($validation->getError($field));

			return false;
		}

		return true;
	}

	public static function print(string $text = '', string $foreground = null, string $background = null)
	{
		if ($foreground || $background)
		{
			$text = static::color($text, $foreground, $background);
		}

		static::$lastWrite = null;

		fwrite(STDOUT, $text);
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

	public static function error(string $text, string $foreground = 'light_red', string $background = null)
	{
		if ($foreground || $background)
		{
			$text = static::color($text, $foreground, $background);
		}

		fwrite(STDERR, $text . PHP_EOL);
	}

	public static function beep(int $num = 1)
	{
		echo str_repeat("\x07", $num);
	}

	public static function wait(int $seconds, bool $countdown = false)
	{
		if ($countdown === true)
		{
			$time = $seconds;

			while ($time > 0)
			{
				fwrite(STDOUT, $time . '... ');
				sleep(1);
				$time --;
			}
			static::write();
		}
		else
		{
			if ($seconds > 0)
			{
				sleep($seconds);
			}
			else
			{
				// this chunk cannot be tested because of keyboard input
				// @codeCoverageIgnoreStart
				static::write(static::$wait_msg);
				static::input();
				// @codeCoverageIgnoreEnd
			}
		}
	}

	public static function isWindows(): bool
	{
		return stripos(PHP_OS, 'WIN') === 0;
	}

	public static function newLine(int $num = 1)
	{
		// Do it once or more, write with empty string gives us a new line
		for ($i = 0; $i < $num; $i ++)
		{
			static::write();
		}
	}

	public static function clearScreen()
	{
		static::isWindows()

				// Windows is a bit crap at this, but their terminal is tiny so shove this in
						? static::newLine(40)

				// Anything with a flair of Unix will handle these magic characters
						: fwrite(STDOUT, chr(27) . '[H' . chr(27) . '[2J');
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

	public static function strlen(?string $string): int
	{
		if (is_null($string))
		{
			return 0;
		}
		foreach (static::$foreground_colors as $color)
		{
			$string = strtr($string, ["\033[" . $color . 'm' => '']);
		}

		foreach (static::$background_colors as $color)
		{
			$string = strtr($string, ["\033[" . $color . 'm' => '']);
		}

		$string = strtr($string, ["\033[4m" => '', "\033[0m" => '']);

		return mb_strlen($string);
	}

	public static function getWidth(int $default = 80): int
	{
		if (static::isWindows() || (int) shell_exec('tput cols') === 0)
		{
			// @codeCoverageIgnoreStart
			return $default;
			// @codeCoverageIgnoreEnd
		}

		return (int) shell_exec('tput cols');
	}

	public static function getHeight(int $default = 32): int
	{
		if (static::isWindows())
		{
			// @codeCoverageIgnoreStart
			return $default;
			// @codeCoverageIgnoreEnd
		}

		return (int) shell_exec('tput lines');
	}

	public static function showProgress($thisStep = 1, int $totalSteps = 10)
	{
		static $inProgress = false;

		// restore cursor position when progress is continuing.
		if ($inProgress !== false && $inProgress <= $thisStep)
		{
			fwrite(STDOUT, "\033[1A");
		}
		$inProgress = $thisStep;

		if ($thisStep !== false)
		{
			// Don't allow div by zero or negative numbers....
			$thisStep   = abs($thisStep);
			$totalSteps = $totalSteps < 1 ? 1 : $totalSteps;

			$percent = intval(($thisStep / $totalSteps) * 100);
			$step    = (int) round($percent / 10);

			// Write the progress bar
			fwrite(STDOUT, "[\033[32m" . str_repeat('#', $step) . str_repeat('.', 10 - $step) . "\033[0m]");
			// Textual representation...
			fwrite(STDOUT, sprintf(' %3d%% Complete', $percent) . PHP_EOL);
		}
		else
		{
			fwrite(STDOUT, "\007");
		}
	}

	public static function wrap(string $string = null, int $max = 0, int $pad_left = 0): string
	{
		if (empty($string))
		{
			return '';
		}

		if ($max === 0)
		{
			$max = CLI::getWidth();
		}

		if (CLI::getWidth() < $max)
		{
			$max = CLI::getWidth();
		}

		$max = $max - $pad_left;

		$lines = wordwrap($string, $max);

		if ($pad_left > 0)
		{
			$lines = explode(PHP_EOL, $lines);

			$first = true;

			array_walk($lines, function (&$line, $index) use ($pad_left, &$first) {
				if (! $first)
				{
					$line = str_repeat(' ', $pad_left) . $line;
				}
				else
				{
					$first = false;
				}
			});

			$lines = implode(PHP_EOL, $lines);
		}

		return $lines;
	}

	protected static function parseCommandLine()
	{
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
			static::$options[$arg] = $value;
		}
	}

	public static function getURI(): string
	{
		return implode('/', static::$segments);
	}

	public static function getSegment(int $index)
	{
		if (! isset(static::$segments[$index - 1]))
		{
			return null;
		}

		return static::$segments[$index - 1];
	}

	public static function getSegments(): array
	{
		return static::$segments;
	}

	public static function getOption(string $name)
	{
		if (! array_key_exists($name, static::$options))
		{
			return null;
		}

		// If the option didn't have a value, simply return TRUE
		// so they know it was set, otherwise return the actual value.
		$val = static::$options[$name] === null ? true : static::$options[$name];

		return $val;
	}

	public static function getOptions(): array
	{
		return static::$options;
	}

	public static function getOptionString(): string
	{
		if (empty(static::$options))
		{
			return '';
		}

		$out = '';

		foreach (static::$options as $name => $value)
		{
			// If there's a space, we need to group
			// so it will pass correctly.
			if (mb_strpos($value, ' ') !== false)
			{
				$value = '"' . $value . '"';
			}

			$out .= "-{$name} $value ";
		}

		return $out;
	}

	public static function table(array $tbody, array $thead = [])
	{
		// All the rows in the table will be here until the end
		$table_rows = [];

		// We need only indexes and not keys
		if (! empty($thead))
		{
			$table_rows[] = array_values($thead);
		}

		foreach ($tbody as $tr)
		{
			$table_rows[] = array_values($tr);
		}

		// Yes, it really is necessary to know this count
		$total_rows = count($table_rows);

		// Store all columns lengths
		// $all_cols_lengths[row][column] = length
		$all_cols_lengths = [];

		// Store maximum lengths by column
		// $max_cols_lengths[column] = length
		$max_cols_lengths = [];

		// Read row by row and define the longest columns
		for ($row = 0; $row < $total_rows; $row ++)
		{
			$column = 0; // Current column index
			foreach ($table_rows[$row] as $col)
			{
				// Sets the size of this column in the current row
				$all_cols_lengths[$row][$column] = static::strlen($col);

				// If the current column does not have a value among the larger ones
				// or the value of this is greater than the existing one
				// then, now, this assumes the maximum length
				if (! isset($max_cols_lengths[$column]) || $all_cols_lengths[$row][$column] > $max_cols_lengths[$column])
				{
					$max_cols_lengths[$column] = $all_cols_lengths[$row][$column];
				}

				// We can go check the size of the next column...
				$column ++;
			}
		}

		// Read row by row and add spaces at the end of the columns
		// to match the exact column length
		for ($row = 0; $row < $total_rows; $row ++)
		{
			$column = 0;
			foreach ($table_rows[$row] as $col)
			{
				$diff = $max_cols_lengths[$column] - static::strlen($col);
				if ($diff)
				{
					$table_rows[$row][$column] = $table_rows[$row][$column] . str_repeat(' ', $diff);
				}
				$column ++;
			}
		}

		$table = '';

		// Joins columns and append the well formatted rows to the table
		for ($row = 0; $row < $total_rows; $row ++)
		{
			// Set the table border-top
			if ($row === 0)
			{
				$cols = '+';
				foreach ($table_rows[$row] as $col)
				{
					$cols .= str_repeat('-', static::strlen($col) + 2) . '+';
				}
				$table .= $cols . PHP_EOL;
			}

			// Set the columns borders
			$table .= '| ' . implode(' | ', $table_rows[$row]) . ' |' . PHP_EOL;

			// Set the thead and table borders-bottom
			if ($row === 0 && ! empty($thead) || $row + 1 === $total_rows)
			{
				$table .= $cols . PHP_EOL;
			}
		}

		static::write($table);
	}
}

CLI::init();
