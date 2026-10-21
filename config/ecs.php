<?php

declare(strict_types=1);

use FastForward\CodeStandards\Config\ECSConfig;

// ECS bootstraps its actual installation autoloader before loading this preset.
return ECSConfig::configure();
