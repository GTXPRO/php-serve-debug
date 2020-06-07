<?php
namespace Config;

class Modules {
	public $enabled = true;
	public $discoverInComposer = true;
	public $activeExplorers = [
		'events',
		'registrars',
		'routes',
		'services',
	];

	public function shouldDiscover(string $alias)
	{
		if (! $this->enabled)
		{
			return false;
		}

		$alias = strtolower($alias);

		return in_array($alias, $this->activeExplorers);
	}
}