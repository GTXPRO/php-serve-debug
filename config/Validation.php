<?php
namespace Config;

class Validation {
	public $ruleSets = [
		\QTCS\Validation\Rules::class,
		\QTCS\Validation\FormatRules::class,
		\QTCS\Validation\FileRules::class,
		\QTCS\Validation\CreditCardRules::class,
	];

	public $templates = [
		// 'list'   => 'QTCS\Validation\Views\list',
		// 'single' => 'QTCS\Validation\Views\single',
	];
}