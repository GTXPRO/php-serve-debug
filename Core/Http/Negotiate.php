<?php
namespace QTCS\Http;

class Negotiate {
	/**
	 * Request
	 * 
	 * @var \QTCS\HTTP\RequestInterface|\QTCS\HTTP\IncomingRequest
	 */
	protected $request;

	public function __construct(RequestInterface $request = null)
	{
		if (! is_null($request))
		{
			$this->request = $request;
		}
	}

	public function setRequest(RequestInterface $request)
	{
		$this->request = $request;

		return $this;
	}

	public function media(array $supported, bool $strictMatch = false): string
	{
		return $this->getBestMatch($supported, $this->request->getHeaderLine('accept'), true, $strictMatch);
	}

	public function charset(array $supported): string
	{
		$match = $this->getBestMatch($supported, $this->request->getHeaderLine('accept-charset'), false, true);

		// If no charset is shown as a match, ignore the directive
		// as allowed by the RFC, and tell it a default value.
		if (empty($match))
		{
			return 'utf-8';
		}

		return $match;
	}

	public function encoding(array $supported = []): string
	{
		array_push($supported, 'identity');

		return $this->getBestMatch($supported, $this->request->getHeaderLine('accept-encoding'));
	}

	public function language(array $supported): string
	{
		return $this->getBestMatch($supported, $this->request->getHeaderLine('accept-language'), false, false, true);
	}

	protected function getBestMatch(array $supported, string $header = null, bool $enforceTypes = false, bool $strictMatch = false, bool $matchLocales = false): string
	{
		if (empty($supported))
		{
			throw new \RuntimeException("You must provide an array of supported values to all Negotiations.");
		}

		if (empty($header))
		{
			return $strictMatch ? '' : $supported[0];
		}

		$acceptable = $this->parseHeader($header);

		foreach ($acceptable as $accept)
		{
			// if acceptable quality is zero, skip it.
			if ($accept['q'] === 0.0)
			{
				continue;
			}

			// if acceptable value is "anything", return the first available
			if ($accept['value'] === '*' || $accept['value'] === '*/*')
			{
				return $supported[0];
			}

			// If an acceptable value is supported, return it
			foreach ($supported as $available)
			{
				if ($this->match($accept, $available, $enforceTypes, $matchLocales))
				{
					return $available;
				}
			}
		}

		// No matches? Return the first supported element.
		return $strictMatch ? '' : $supported[0];
	}

	public function parseHeader(string $header): array
	{
		$results    = [];
		$acceptable = explode(',', $header);

		foreach ($acceptable as $value)
		{
			$pairs = explode(';', $value);

			$value = $pairs[0];

			unset($pairs[0]);

			$parameters = [];

			foreach ($pairs as $pair)
			{
				$param = [];
				preg_match(
						'/^(?P<name>.+?)=(?P<quoted>"|\')?(?P<value>.*?)(?:\k<quoted>)?$/', $pair, $param
				);
				$parameters[trim($param['name'])] = trim($param['value']);
			}

			$quality = 1.0;

			if (array_key_exists('q', $parameters))
			{
				$quality = $parameters['q'];
				unset($parameters['q']);
			}

			$results[] = [
				'value'  => trim($value),
				'q'      => (float) $quality,
				'params' => $parameters,
			];
		}

		// Sort to get the highest results first
		usort($results, function ($a, $b) {
			if ($a['q'] === $b['q'])
			{
				$a_ast = substr_count($a['value'], '*');
				$b_ast = substr_count($b['value'], '*');

				// '*/*' has lower precedence than 'text/*',
				// and 'text/*' has lower priority than 'text/plain'
				//
				// This seems backwards, but needs to be that way
				// due to the way PHP7 handles ordering or array
				// elements created by reference.
				if ($a_ast > $b_ast)
				{
					return 1;
				}

				// If the counts are the same, but one element
				// has more params than another, it has higher precedence.
				//
				// This seems backwards, but needs to be that way
				// due to the way PHP7 handles ordering or array
				// elements created by reference.
				if ($a_ast === $b_ast)
				{
					return count($b['params']) - count($a['params']);
				}

				return 0;
			}

			// Still here? Higher q values have precedence.
			return ($a['q'] > $b['q']) ? -1 : 1;
		});

		return $results;
	}

	protected function match(array $acceptable, string $supported, bool $enforceTypes = false, $matchLocales = false): bool
	{
		$supported = $this->parseHeader($supported);
		if (is_array($supported) && count($supported) === 1)
		{
			$supported = $supported[0];
		}

		// Is it an exact match?
		if ($acceptable['value'] === $supported['value'])
		{
			return $this->matchParameters($acceptable, $supported);
		}

		// Do we need to compare types/sub-types? Only used
		// by negotiateMedia().
		if ($enforceTypes)
		{
			return $this->matchTypes($acceptable, $supported);
		}

		// Do we need to match locales against broader locales?
		if ($matchLocales)
		{
			return $this->matchLocales($acceptable, $supported);
		}

		return false;
	}

	protected function matchParameters(array $acceptable, array $supported): bool
	{
		if (count($acceptable['params']) !== count($supported['params']))
		{
			return false;
		}

		foreach ($supported['params'] as $label => $value)
		{
			if (! isset($acceptable['params'][$label]) ||
					$acceptable['params'][$label] !== $value)
			{
				return false;
			}
		}

		return true;
	}

	public function matchTypes(array $acceptable, array $supported): bool
	{
		[
			$aType,
			$aSubType,
		] = explode('/', $acceptable['value']);
		[
			$sType,
			$sSubType,
		] = explode('/', $supported['value']);

		// If the types don't match, we're done.
		if ($aType !== $sType)
		{
			return false;
		}

		// If there's an asterisk, we're cool
		if ($aSubType === '*')
		{
			return true;
		}

		// Otherwise, subtypes must match also.
		return $aSubType === $sSubType;
	}

	public function matchLocales(array $acceptable, array $supported): bool
	{
		$aBroad = mb_strpos($acceptable['value'], '-') > 0
			? mb_substr($acceptable['value'], 0, mb_strpos($acceptable['value'], '-'))
			: $acceptable['value'];
		$sBroad = mb_strpos($supported['value'], '-') > 0
			? mb_substr($supported['value'], 0, mb_strpos($supported['value'], '-'))
			: $supported['value'];

		return strtolower($aBroad) === strtolower($sBroad);
	}
}