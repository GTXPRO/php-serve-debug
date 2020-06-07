<?php
namespace Config;

use QTCS\Config\View as BaseView;

class View extends BaseView {
	public $saveData = true;
	public $filters = [];
	public $plugins = [];
}