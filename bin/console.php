#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use App\Console\ViewsSyncCommand;

$container = require __DIR__ . '/../bootstrap/dependencies.php';

$consoleApp = new Application('Slim Console', '1.0.0');
$consoleApp->add($container->get(ViewsSyncCommand::class));
$consoleApp->run();