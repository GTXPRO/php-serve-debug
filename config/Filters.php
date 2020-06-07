<?php
namespace Config;

class Filters {
	public $aliases = [
		'csrf'     => \QTCS\Filters\CSRF::class,
		// 'toolbar'  => \CodeIgniter\Filters\DebugToolbar::class,
		// 'honeypot' => \CodeIgniter\Filters\Honeypot::class,
	];

	public $globals = [
		'before' => [
			//'honeypot'
			// 'csrf',
		],
		'after'  => [
			// 'toolbar',
			//'honeypot'
		],
	];
	public $methods = [];
	public $filters = [];
}