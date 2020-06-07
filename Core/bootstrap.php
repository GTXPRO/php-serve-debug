<?php

require_once __DIR__. '/Config/BaseServices.php';
require_once __DIR__. '/Config/Services.php';
require_once __DIR__. '/Common.php';

$config = [];
$app = new \QTCS\Loader($config);

$app->initialize();

return $app;