<?php
namespace QTCS\Log\Handlers;

abstract class BaseHandler implements HandlerInterface
{

	protected $handles;

	protected $dateFormat = 'Y-m-d H:i:s';

	public function __construct(array $config)
	{
		$this->handles = $config['handles'] ?? [];
	}

	public function canHandle(string $level): bool
	{
		return in_array($level, $this->handles);
	}

	abstract public function handle($level, $message): bool;

	public function setDateFormat(string $format): HandlerInterface
	{
		$this->dateFormat = $format;

		return $this;
	}
}
