<?php

declare(strict_types=1);

use FastForward\CodeStandards\Config\ECSConfig;

$autoload = is_file(__DIR__ . '/../vendor/autoload.php')
    ? __DIR__ . '/../vendor/autoload.php'
    : __DIR__ . '/../../../autoload.php';
require_once $autoload;

return ECSConfig::configure();
