<?php
namespace QTCS\Format;

class XMLFormatter implements FormatterInterface {
	public function format($data)
	{
		if (! extension_loaded('simplexml'))
		{
			throw new \RuntimeException("The SimpleXML extension is required to format XML.");
		}

		$output = new \SimpleXMLElement('<?xml version="1.0"?><response></response>');

		$this->arrayToXML((array)$data, $output);

		return $output->asXML();
	}

	protected function arrayToXML(array $data, &$output)
	{
		foreach ($data as $key => $value)
		{
			if (is_array($value))
			{
				if (! is_numeric($key))
				{
					$subnode = $output->addChild("$key");
					$this->arrayToXML($value, $subnode);
				}
				else
				{
					$subnode = $output->addChild("item{$key}");
					$this->arrayToXML($value, $subnode);
				}
			}
			else
			{
				$output->addChild("$key", htmlspecialchars("$value"));
			}
		}
	}
}