<?php

declare(strict_types=1);

use Core\MakeMigrationCommand;
use Phinx\Console\PhinxApplication;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$application = new PhinxApplication();
$application->add(new MakeMigrationCommand());
$application->setDefaultCommand('create', true);
exit($application->run());
