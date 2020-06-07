<?php
namespace QTCS\Http;

class UserAgent {
	protected $agent;
	protected $isBrowser = false;
	protected $isRobot = false;
	protected $isMobile = false;
	protected $config;
	protected $platform = '';
	protected $browser = '';
	protected $version = '';
	protected $mobile = '';
	protected $robot = '';
	protected $referrer;
	public function __construct($config = null)
	{
		if (is_null($config))
		{
			$this->config = new UserAgents();
		}

		if (isset($_SERVER['HTTP_USER_AGENT']))
		{
			$this->agent = trim($_SERVER['HTTP_USER_AGENT']);
			$this->compileData();
		}
	}

	public function isBrowser(string $key = null): bool
	{
		if (! $this->isBrowser)
		{
			return false;
		}

		// No need to be specific, it's a browser
		if ($key === null)
		{
			return true;
		}

		// Check for a specific browser
		return (isset($this->config->browsers[$key]) && $this->browser === $this->config->browsers[$key]);
	}

	public function isRobot(string $key = null): bool
	{
		if (! $this->isRobot)
		{
			return false;
		}

		// No need to be specific, it's a robot
		if ($key === null)
		{
			return true;
		}

		// Check for a specific robot
		return (isset($this->config->robots[$key]) && $this->robot === $this->config->robots[$key]);
	}

	public function isMobile(string $key = null): bool
	{
		if (! $this->isMobile)
		{
			return false;
		}

		// No need to be specific, it's a mobile
		if ($key === null)
		{
			return true;
		}

		// Check for a specific robot
		return (isset($this->config->mobiles[$key]) && $this->mobile === $this->config->mobiles[$key]);
	}

	public function isReferral(): bool
	{
		if (! isset($this->referrer))
		{
			if (empty($_SERVER['HTTP_REFERER']))
			{
				$this->referrer = false;
			}
			else
			{
				$referer_host = @parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
				//$own_host     = parse_url(\base_url(), PHP_URL_HOST);

				//$this->referrer = ($referer_host && $referer_host !== $own_host);
			}
		}

		return $this->referrer;
	}

	public function getAgentString(): string
	{
		return $this->agent;
	}

	public function getPlatform(): string
	{
		return $this->platform;
	}

	public function getBrowser(): string
	{
		return $this->browser;
	}

	public function getVersion(): string
	{
		return $this->version;
	}

	public function getRobot(): string
	{
		return $this->robot;
	}

	public function getMobile(): string
	{
		return $this->mobile;
	}

	public function getReferrer(): string
	{
		return empty($_SERVER['HTTP_REFERER']) ? '' : trim($_SERVER['HTTP_REFERER']);
	}

	public function parse(string $string)
	{
		// Reset values
		$this->isBrowser = false;
		$this->isRobot   = false;
		$this->isMobile  = false;
		$this->browser   = '';
		$this->version   = '';
		$this->mobile    = '';
		$this->robot     = '';

		// Set the new user-agent string and parse it, unless empty
		$this->agent = $string;

		if (! empty($string))
		{
			$this->compileData();
		}
	}

	protected function compileData()
	{
		$this->setPlatform();

		foreach (['setRobot', 'setBrowser', 'setMobile'] as $function)
		{
			if ($this->$function() === true)
			{
				break;
			}
		}
	}

	protected function setPlatform(): bool
	{
		if (is_array($this->config->platforms) && $this->config->platforms)
		{
			foreach ($this->config->platforms as $key => $val)
			{
				if (preg_match('|' . preg_quote($key) . '|i', $this->agent))
				{
					$this->platform = $val;

					return true;
				}
			}
		}

		$this->platform = 'Unknown Platform';

		return false;
	}

	protected function setBrowser(): bool
	{
		if (is_array($this->config->browsers) && $this->config->browsers)
		{
			foreach ($this->config->browsers as $key => $val)
			{
				if (preg_match('|' . $key . '.*?([0-9\.]+)|i', $this->agent, $match))
				{
					$this->isBrowser = true;
					$this->version   = $match[1];
					$this->browser   = $val;
					$this->setMobile();

					return true;
				}
			}
		}

		return false;
	}

	protected function setRobot(): bool
	{
		if (is_array($this->config->robots) && $this->config->robots)
		{
			foreach ($this->config->robots as $key => $val)
			{
				if (preg_match('|' . preg_quote($key) . '|i', $this->agent))
				{
					$this->isRobot = true;
					$this->robot   = $val;
					$this->setMobile();

					return true;
				}
			}
		}

		return false;
	}

	protected function setMobile(): bool
	{
		if (is_array($this->config->mobiles) && $this->config->mobiles)
		{
			foreach ($this->config->mobiles as $key => $val)
			{
				if (false !== (stripos($this->agent, $key)))
				{
					$this->isMobile = true;
					$this->mobile   = $val;

					return true;
				}
			}
		}

		return false;
	}

	public function __toString(): string
	{
		return $this->getAgentString();
	}
}