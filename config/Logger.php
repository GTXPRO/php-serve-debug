<?php
namespace Config;

use QTCS\Config\BaseConfig;

class Logger extends BaseConfig {
	public $threshold = 3;
	public $dateFormat = 'Y-m-d H:i:s';

	public $handlers = [

		//--------------------------------------------------------------------
		// File Handler
		//--------------------------------------------------------------------

		'QTCS\Log\Handlers\FileHandler' => [

			/*
			 * The log levels that this handler will handle.
			 */
			'handles'         => [
				'critical',
				'alert',
				'emergency',
				'debug',
				'error',
				'info',
				'notice',
				'warning',
			],

			/*
			 * The default filename extension for log files.
			 * An extension of 'php' allows for protecting the log files via basic
			 * scripting, when they are to be stored under a publicly accessible directory.
			 *
			 * Note: Leaving it blank will default to 'log'.
			 */
			'fileExtension'   => '',

			/*
			 * The file system permissions to be applied on newly created log files.
			 *
			 * IMPORTANT: This MUST be an integer (no quotes) and you MUST use octal
			 * integer notation (i.e. 0700, 0644, etc.)
			 */
			'filePermissions' => 0644,

			/*
			 * Logging Directory Path
			 *
			 * By default, logs are written to WRITEPATH . 'logs/'
			 * Specify a different destination here, if desired.
			 */
			'path'            => '',
		],

		/**
		 * The ChromeLoggerHandler requires the use of the Chrome web browser
		 * and the ChromeLogger extension. Uncomment this block to use it.
		 */
		//      'CodeIgniter\Log\Handlers\ChromeLoggerHandler' => [
		//          /*
		//           * The log levels that this handler will handle.
		//           */
		//          'handles' => ['critical', 'alert', 'emergency', 'debug',
		//                        'error', 'info', 'notice', 'warning'],
		//      ]
	];
}