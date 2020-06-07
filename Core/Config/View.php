<?php
namespace QTCS\Config;

class View extends BaseConfig {
	protected $coreFilters = [
		'abs'            => '\abs',
		'capitalize'     => '\QTCS\View\Filters::capitalize',
		'date'           => '\QTCS\View\Filters::date',
		'date_modify'    => '\QTCS\View\Filters::date_modify',
		'default'        => '\QTCS\View\Filters::default',
		'esc'            => '\QTCS\View\Filters::esc',
		'excerpt'        => '\QTCS\View\Filters::excerpt',
		'highlight'      => '\QTCS\View\Filters::highlight',
		'highlight_code' => '\QTCS\View\Filters::highlight_code',
		'limit_words'    => '\QTCS\View\Filters::limit_words',
		'limit_chars'    => '\QTCS\View\Filters::limit_chars',
		'local_currency' => '\QTCS\View\Filters::local_currency',
		'local_number'   => '\QTCS\View\Filters::local_number',
		'lower'          => '\strtolower',
		'nl2br'          => '\QTCS\View\Filters::nl2br',
		'number_format'  => '\number_format',
		'prose'          => '\QTCS\View\Filters::prose',
		'round'          => '\QTCS\View\Filters::round',
		'strip_tags'     => '\strip_tags',
		'title'          => '\QTCS\View\Filters::title',
		'upper'          => '\strtoupper',
	];

	protected $corePlugins = [
		'current_url'       => '\QTCS\View\Plugins::currentURL',
		'previous_url'      => '\QTCS\View\Plugins::previousURL',
		'mailto'            => '\QTCS\View\Plugins::mailto',
		'safe_mailto'       => '\QTCS\View\Plugins::safeMailto',
		'lang'              => '\QTCS\View\Plugins::lang',
		'validation_errors' => '\QTCS\View\Plugins::validationErrors',
		'route'             => '\QTCS\View\Plugins::route',
		'siteURL'           => '\QTCS\View\Plugins::siteURL',
	];

	public function __construct()
	{
		$this->filters = array_merge($this->coreFilters, $this->filters);
		$this->plugins = array_merge($this->corePlugins, $this->plugins);

		parent::__construct();
	}
}