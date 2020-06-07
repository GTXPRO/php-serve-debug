<?php
namespace QTCS\Http;

interface RequestInterface {
	public function getIPAddress(): string;
	public function isValidIP(string $ip, string $which = null): bool;
	public function getMethod(bool $upper = false): string;
	public function getServer($index = null, $filter = null);
}