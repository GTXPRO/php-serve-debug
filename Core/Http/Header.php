<?php
namespace QTCS\Http;

class Header {
	protected $name;
	protected $value;
	public function __construct(string $name, $value = null) {
		$this->name  = $name;
		$this->value = $value;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getValue() {
		return $this->value;
	}

	public function setName(string $name) {
		$this->name = $name;

		return $this;
	}

	public function setValue($value = null) {
		$this->value = $value;

		return $this;
	}

	public function appendValue($value = null) {
		if (! is_array($this->value))
		{
			$this->value = [$this->value];
		}

		$this->value[] = $value;

		return $this;
	}

	public function prependValue($value = null) {
		if (! is_array($this->value))
		{
			$this->value = [$this->value];
		}

		array_unshift($this->value, $value);

		return $this;
	}

	public function getValueLine(): string {
		if (is_string($this->value))
		{
			return $this->value;
		}
		else if (! is_array($this->value))
		{
			return '';
		}

		$options = [];

		foreach ($this->value as $key => $value)
		{
			if (is_string($key) && ! is_array($value))
			{
				$options[] = $key . '=' . $value;
			}
			else if (is_array($value))
			{
				$key       = key($value);
				$options[] = $key . '=' . $value[$key];
			}
			else if (is_numeric($key))
			{
				$options[] = $value;
			}
		}

		return implode(', ', $options);
	}

	public function __toString(): string {
		return $this->name . ': ' . $this->getValueLine();
	}
}