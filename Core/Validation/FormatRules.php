<?php

namespace QTCS\Validation;

class FormatRules
{
	public function alpha(string $str = null): bool
	{
		return ctype_alpha($str);
	}

	public function alpha_space(string $value = null): bool
	{
		if ($value === null)
		{
			return true;
		}

		return (bool) preg_match('/^[A-Z ]+$/i', $value);
	}

	public function alpha_dash(string $str = null): bool
	{
			return (bool) preg_match('/^[a-z0-9_-]+$/i', $str);
	}

	public function alpha_numeric_punct($str)
	{
		return (bool) preg_match('/^[A-Z0-9 ~!#$%\&\*\-_+=|:.]+$/i', $str);
	}

	public function alpha_numeric(string $str = null): bool
	{
		return ctype_alnum($str);
	}

	public function alpha_numeric_space(string $str = null): bool
	{
		return (bool) preg_match('/^[A-Z0-9 ]+$/i', $str);
	}

	public function string($str = null): bool
	{
		return is_string($str);
	}

	public function decimal(string $str = null): bool
	{
		return (bool) preg_match('/^[-+]?[0-9]{0,}\.?[0-9]+$/', $str);
	}

	public function hex(string $str = null): bool
	{
		return ctype_xdigit($str);
	}

	public function integer(string $str = null): bool
	{
		return (bool) preg_match('/^[\-+]?[0-9]+$/', $str);
	}

	public function is_natural(string $str = null): bool
	{
		return ctype_digit($str);
	}

	public function is_natural_no_zero(string $str = null): bool
	{
		return ($str !== '0' && ctype_digit($str));
	}

	public function numeric(string $str = null): bool
	{
		return (bool) preg_match('/^[\-+]?[0-9]*\.?[0-9]+$/', $str);
	}

	public function regex_match(string $str = null, string $pattern): bool
	{
		if (strpos($pattern, '/') !== 0)
		{
			$pattern = "/{$pattern}/";
		}

		return (bool) preg_match($pattern, $str);
	}

	public function timezone(string $str = null): bool
	{
		return in_array($str, timezone_identifiers_list());
	}

	public function valid_base64(string $str = null): bool
	{
		return (base64_encode(base64_decode($str)) === $str);
	}

	public function valid_json(string $str = null): bool
	{
		json_decode($str);
		return json_last_error() === JSON_ERROR_NONE;
	}

	public function valid_email(string $str = null): bool
	{
		if (function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46') && preg_match('#\A([^@]+)@(.+)\z#', $str, $matches))
		{
			$str = $matches[1] . '@' . idn_to_ascii($matches[2], 0, INTL_IDNA_VARIANT_UTS46);
		}

		return (bool) filter_var($str, FILTER_VALIDATE_EMAIL);
	}

	public function valid_emails(string $str = null): bool
	{
		foreach (explode(',', $str) as $email)
		{
			$email = trim($email);
			if ($email === '')
			{
				return false;
			}

			if ($this->valid_email($email) === false)
			{
				return false;
			}
		}

		return true;
	}

	public function valid_ip(string $ip = null, string $which = null): bool
	{
		if (empty($ip))
		{
			return false;
		}
		switch (strtolower($which))
		{
			case 'ipv4':
				$which = FILTER_FLAG_IPV4;
				break;
			case 'ipv6':
				$which = FILTER_FLAG_IPV6;
				break;
			default:
				$which = null;
				break;
		}

		return (bool) filter_var($ip, FILTER_VALIDATE_IP, $which) || (! ctype_print($ip) && (bool) filter_var(inet_ntop($ip), FILTER_VALIDATE_IP, $which));
	}

	public function valid_url(string $str = null): bool
	{
		if (empty($str))
		{
			return false;
		}
		elseif (preg_match('/^(?:([^:]*)\:)?\/\/(.+)$/', $str, $matches))
		{
			if (! in_array($matches[1], ['http', 'https'], true))
			{
				return false;
			}

			$str = $matches[2];
		}

		$str = 'http://' . $str;

		return (filter_var($str, FILTER_VALIDATE_URL) !== false);
	}

	public function valid_date(string $str = null, string $format = null): bool
	{
		if (empty($format))
		{
			return (bool) strtotime($str);
		}

		$date = \DateTime::createFromFormat($format, $str);

		return (bool) $date && \DateTime::getLastErrors()['warning_count'] === 0 && \DateTime::getLastErrors()['error_count'] === 0;
	}
}
