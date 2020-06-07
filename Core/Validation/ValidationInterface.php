<?php
namespace QTCS\Validation;

use QTCS\Http\RequestInterface;

interface ValidationInterface {
	public function run(array $data = null, string $group = null): bool;
	public function check($value, string $rule, array $errors = []): bool;
	public function withRequest(RequestInterface $request): ValidationInterface;
	public function setRules(array $rules, array $messages = []): ValidationInterface;
	public function hasRule(string $field): bool;
	public function getError(string $field): string;
	public function getErrors(): array;
	public function setError(string $alias, string $error): ValidationInterface;
	public function reset(): ValidationInterface;
}