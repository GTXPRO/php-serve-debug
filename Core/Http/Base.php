<?php

namespace QTCS\Http;

class Base {
	protected $headers = [];
	protected $headerMap = [];
	protected $protocolVersion;
	protected $validProtocolVersions = [
		'1.0',
		'1.1',
		'2',
	];
	protected $body;

	public function getBody() {
		return $this->body;
	}

	public function setBody($data) {
		$this->body = $data;

		return $this;
	}

	public function appendBody($data) {
		$this->body .= (string) $data;

		return $this;
	}

	public function populateHeaders() {
		$contentType = $_SERVER['CONTENT_TYPE'] ?? getenv('CONTENT_TYPE');
		if (! empty($contentType))
		{
			$this->setHeader('Content-Type', $contentType);
		}
		unset($contentType);

		foreach ($_SERVER as $key => $val)
		{
			if (sscanf($key, 'HTTP_%s', $header) === 1)
			{
				// take SOME_HEADER and turn it into Some-Header
				$header = str_replace('_', ' ', strtolower($header));
				$header = str_replace(' ', '-', ucwords($header));

				$this->setHeader($header, $_SERVER[$key]);

				// Add us to the header map so we can find them case-insensitively
				$this->headerMap[strtolower($header)] = $header;
			}
		}
	}

	public function getHeaders(): array {
		if (empty($this->headers))
		{
			$this->populateHeaders();
		}

		return $this->headers;
	}

	public function getHeader(string $name) {
		$orig_name = $this->getHeaderName($name);

		if (! isset($this->headers[$orig_name]))
		{
			return null;
		}

		return $this->headers[$orig_name];
	}

	public function hasHeader(string $name): bool {
		$orig_name = $this->getHeaderName($name);

		return isset($this->headers[$orig_name]);
	}

	public function getHeaderLine(string $name): string {
		$orig_name = $this->getHeaderName($name);

		if (! array_key_exists($orig_name, $this->headers))
		{
			return '';
		}

		return $this->headers[$orig_name]->getValueLine();
	}

	public function setHeader(string $name, $value) {
		$origName = $this->getHeaderName($name);

		if (isset($this->headers[$origName]) && is_array($this->headers[$origName]))
		{
			$this->appendHeader($origName, $value);
		}
		else
		{
			$this->headers[$origName]               = new Header($origName, $value);
			$this->headerMap[strtolower($origName)] = $origName;
		}

		return $this;
	}

	public function removeHeader(string $name) {
		$orig_name = $this->getHeaderName($name);

		unset($this->headers[$orig_name]);
		unset($this->headerMap[strtolower($name)]);

		return $this;
	}

	public function appendHeader(string $name, string $value) {
		$orig_name = $this->getHeaderName($name);

		array_key_exists($orig_name, $this->headers)
			? $this->headers[$orig_name]->appendValue($value)
			: $this->setHeader($name, $value);

		return $this;
	}

	public function prependHeader(string $name, string $value) {
		$orig_name = $this->getHeaderName($name);

		$this->headers[$orig_name]->prependValue($value);

		return $this;
	}

	public function getProtocolVersion(): string {
		return $this->protocolVersion ?? '1.1';
	}

	public function setProtocolVersion(string $version) {
		if (! is_numeric($version))
		{
			$version = substr($version, strpos($version, '/') + 1);
		}

		if (! in_array($version, $this->validProtocolVersions))
		{
			$errorMessage = implode(', ', $this->validProtocolVersions);
			throw new \RuntimeException("Invalid HTTP Protocol Version. Must be one of: {$errorMessage}");
		}

		$this->protocolVersion = $version;

		return $this;
	}

	protected function getHeaderName(string $name): string {
		$lower_name = strtolower($name);

		return $this->headerMap[$lower_name] ?? $name;
	}
}
