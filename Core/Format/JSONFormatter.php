<?php
namespace QTCS\Format;

class JSONFormatter implements FormatterInterface {
	public function format($data)
	{
		$options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;

		$options = ENVIRONMENT === 'production' ? $options : $options | JSON_PRETTY_PRINT;

		$result = json_encode($data, $options, 512);

		if ( ! in_array(json_last_error(), [JSON_ERROR_NONE, JSON_ERROR_RECURSION]))
		{
			$errorMessage = json_last_error_msg();
			throw new \RuntimeException("Failed to parse json string, error: \"{$errorMessage}\".");
		}

		return $result;
	}
}