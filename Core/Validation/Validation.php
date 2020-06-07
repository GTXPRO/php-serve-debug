<?php

namespace QTCS\Validation;

use QTCS\Http\RequestInterface;
use QTCS\View\RendererInterface;

class Validation implements ValidationInterface
{
	protected $ruleSetFiles;
	protected $ruleSetInstances = [];
	protected $rules = [];
	protected $data = [];
	protected $errors = [];
	protected $customErrors = [];
	protected $config;
	protected $view;

	public function __construct($config, RendererInterface $view)
	{
		$this->ruleSetFiles = $config->ruleSets;

		$this->config = $config;

		$this->view = $view;
	}

	public function run(array $data = null, string $group = null, string $db_group = null): bool
	{
		$data = $data ?? $this->data;

		// i.e. is_unique
		$data['DBGroup'] = $db_group;

		$this->loadRuleSets();

		$this->loadRuleGroup($group);

		if (empty($this->rules))
		{
			return false;
		}

		$this->rules = $this->fillPlaceholders($this->rules, $data);

		foreach ($this->rules as $rField => $rSetup)
		{
			// Blast $rSetup apart, unless it's already an array.
			$rules = $rSetup['rules'] ?? $rSetup;

			if (is_string($rules))
			{
				$rules = $this->splitRules($rules);
			}

			$value          = dot_array_search($rField, $data);
			$fieldNameToken = explode('.', $rField);

			if (is_array($value) && end($fieldNameToken) === '*')
			{
				foreach ($value as $val)
				{
					$this->processRules($rField, $rSetup['label'] ?? $rField, $val ?? null, $rules, $data);
				}
			}
			else
			{
				$this->processRules($rField, $rSetup['label'] ?? $rField, $value ?? null, $rules, $data);
			}
		}

		return ! empty($this->getErrors()) ? false : true;
	}

	public function check($value, string $rule, array $errors = []): bool
	{
		$this->reset();
		$this->setRule('check', null, $rule, $errors);

		return $this->run([
			'check' => $value,
		]);
	}

	protected function processRules(string $field, string $label = null, $value, $rules = null, array $data): bool
	{
		// If the if_exist rule is defined...
		if (in_array('if_exist', $rules))
		{
			// and the current field does not exists in the input data
			// we can return true. Ignoring all other rules to this field.
			if (! array_key_exists($field, $data))
			{
				return true;
			}
			// Otherwise remove the if_exist rule and continue the process
			$rules = array_diff($rules, ['if_exist']);
		}

		if (in_array('permit_empty', $rules))
		{
			if (! in_array('required', $rules) && (is_array($value) ? empty($value) : (trim($value) === '')))
			{
				return true;
			}

			$rules = array_diff($rules, ['permit_empty']);
		}

		foreach ($rules as $rule)
		{
			$callable = is_callable($rule);
			$passed   = false;

			// Rules can contain parameters: max_length[5]
			$param = false;
			if (! $callable && preg_match('/(.*?)\[(.*)\]/', $rule, $match))
			{
				$rule  = $match[1];
				$param = $match[2];
			}

			// Placeholder for custom errors from the rules.
			$error = null;

			// If it's a callable, call and and get out of here.
			if ($callable)
			{
				$passed = $param === false ? $rule($value) : $rule($value, $param, $data);
			}
			else
			{
				$found = false;

				// Check in our rulesets
				foreach ($this->ruleSetInstances as $set)
				{
					if (! method_exists($set, $rule))
					{
						continue;
					}

					$found = true;

					$passed = $param === false ? $set->$rule($value, $error) : $set->$rule($value, $param, $data, $error);
					break;
				}

				// If the rule wasn't found anywhere, we
				// should throw an exception so the developer can find it.
				if (! $found)
				{
					throw new \RuntimeException("{$rule} is not a valid rule.");
				}
			}

			// Set the error message if we didn't survive.
			if ($passed === false)
			{
				// if the $value is an array, convert it to as string representation
				if (is_array($value))
				{
					$value = '[' . implode(', ', $value) . ']';
				}

				$this->errors[$field] = is_null($error) ? $this->getErrorMessage($rule, $field, $label, $param, $value)
					: $error;

				return false;
			}
		}

		return true;
	}

	/**
	 * Takes a Request object and grabs the input data to use from its
	 * array values.
	 *
	 * @param \QTCS\HTTP\RequestInterface|\QTCS\HTTP\IncomingRequest $request
	 *
	 * @return \QTCS\Validation\ValidationInterface
	 */
	public function withRequest(RequestInterface $request): ValidationInterface
	{
		if (in_array($request->getMethod(), ['put', 'patch', 'delete']))
		{
			$this->data = $request->getRawInput();
		}
		else
		{
			$this->data = $request->getVar() ?? [];
		}

		return $this;
	}

	public function setRule(string $field, string $label = null, string $rules, array $errors = [])
	{
		$this->rules[$field] = [
			'label' => $label,
			'rules' => $rules,
		];
		$this->customErrors  = array_merge($this->customErrors, [
			$field => $errors,
		]);

		return $this;
	}

	public function setRules(array $rules, array $errors = []): ValidationInterface
	{
		$this->customErrors = $errors;

		foreach ($rules as $field => &$rule)
		{
			if (is_array($rule))
			{
				if (array_key_exists('errors', $rule))
				{
					$this->customErrors[$field] = $rule['errors'];
					unset($rule['errors']);
				}
			}
		}

		$this->rules = $rules;

		return $this;
	}

	public function getRules(): array
	{
		return $this->rules;
	}

	public function hasRule(string $field): bool
	{
		return array_key_exists($field, $this->rules);
	}

	public function getRuleGroup(string $group): array
	{
		$errorMessage = "{$group} is not a validation rules group.";
		if (! isset($this->config->$group))
		{
			throw new \RuntimeException($errorMessage);
		}

		if (! is_array($this->config->$group))
		{
			throw new \RuntimeException($errorMessage);
		}

		return $this->config->$group;
	}

	public function setRuleGroup(string $group)
	{
		$rules = $this->getRuleGroup($group);
		$this->setRules($rules);

		$errorName = $group . '_errors';
		if (isset($this->config->$errorName))
		{
			$this->customErrors = $this->config->$errorName;
		}
	}

	public function listErrors(string $template = 'list'): string
	{
		if (! array_key_exists($template, $this->config->templates))
		{
			throw new \RuntimeException("{$template} is not a valid Validation template.");
		}

		return $this->view->setVar('errors', $this->getErrors())
						->render($this->config->templates[$template]);
	}

	public function showError(string $field, string $template = 'single'): string
	{
		if (! array_key_exists($field, $this->getErrors()))
		{
			return '';
		}

		if (! array_key_exists($template, $this->config->templates))
		{
			throw new \RuntimeException("{$template} is not a valid Validation template.");
		}

		return $this->view->setVar('error', $this->getError($field))
						->render($this->config->templates[$template]);
	}

	protected function loadRuleSets()
	{
		if (empty($this->ruleSetFiles))
		{
			throw new \RuntimeException("No rulesets specified in Validation configuration.");
		}

		foreach ($this->ruleSetFiles as $file)
		{
			$this->ruleSetInstances[] = new $file();
		}
	}

	public function loadRuleGroup(string $group = null)
	{
		if (empty($group))
		{
			return null;
		}

		$errorMessage = "{$group} rule group must be an array.";
		if (! isset($this->config->$group))
		{
			throw new \RuntimeException($errorMessage);
		}

		if (! is_array($this->config->$group))
		{
			throw new \RuntimeException($errorMessage);
		}

		$this->rules = $this->config->$group;

		// If {group}_errors exists in the config file,
		// then override our custom errors with them.
		$errorName = $group . '_errors';

		if (isset($this->config->$errorName))
		{
			$this->customErrors = $this->config->$errorName;
		}

		return $this->rules;
	}

	protected function fillPlaceholders(array $rules, array $data): array
	{
		$replacements = [];

		foreach ($data as $key => $value)
		{
			$replacements["{{$key}}"] = $value;
		}

		if (! empty($replacements))
		{
			foreach ($rules as &$rule)
			{
				if (is_array($rule))
				{
					foreach ($rule as &$row)
					{
						// Should only be an `errors` array
						// which doesn't take placeholders.
						if (is_array($row))
						{
							continue;
						}

						$row = strtr($row, $replacements);
					}
					continue;
				}

				$rule = strtr($rule, $replacements);
			}
		}

		return $rules;
	}

	public function hasError(string $field): bool
	{
		return array_key_exists($field, $this->getErrors());
	}

	public function getError(string $field = null): string
	{
		if ($field === null && count($this->rules) === 1)
		{
			reset($this->rules);
			$field = key($this->rules);
		}

		return array_key_exists($field, $this->getErrors()) ? $this->errors[$field] : '';
	}

	public function getErrors(): array
	{
		// If we already have errors, we'll use those.
		// If we don't, check the session to see if any were
		// passed along from a redirect_with_input request.
		if (empty($this->errors) && ! is_cli())
		{
			if (isset($_SESSION, $_SESSION['_ci_validation_errors']))
			{
				$this->errors = unserialize($_SESSION['_ci_validation_errors']);
			}
		}

		return $this->errors ?? [];
	}

	public function setError(string $field, string $error): ValidationInterface
	{
		$this->errors[$field] = $error;

		return $this;
	}

	protected function getErrorMessage(string $rule, string $field, string $label = null, string $param = null, string $value = null): string
	{
		// Check if custom message has been defined by user
		if (isset($this->customErrors[$field][$rule]))
		{
			$message = lang($this->customErrors[$field][$rule]);
		}
		else
		{
			// Try to grab a localized version of the message...
			// lang() will return the rule name back if not found,
			// so there will always be a string being returned.
			$message = lang('Validation.' . $rule);
		}

		$message = str_replace('{field}', empty($label) ? $field : lang($label), $message);
		$message = str_replace('{param}', empty($this->rules[$param]['label']) ? $param : lang($this->rules[$param]['label']), $message);

		return str_replace('{value}', $value, $message);
	}

	protected function splitRules(string $rules): array
	{
		$non_escape_bracket  = '((?<!\\\\)(?:\\\\\\\\)*[\[\]])';
		$pipe_not_in_bracket = sprintf(
				'/\|(?=(?:[^\[\]]*%s[^\[\]]*%s)*(?![^\[\]]*%s))/',
				$non_escape_bracket,
				$non_escape_bracket,
				$non_escape_bracket
		);

		$_rules = preg_split(
				$pipe_not_in_bracket,
				$rules
		);

		return array_unique($_rules);
	}

	public function reset(): ValidationInterface
	{
		$this->data         = [];
		$this->rules        = [];
		$this->errors       = [];
		$this->customErrors = [];

		return $this;
	}
}
