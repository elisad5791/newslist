<?php
use App\Services\LikeService;

require_once __DIR__ . '/../vendor/autoload.php';

echo " [*] Waiting for messages. To exit press CTRL+C\n\n";

$container = require __DIR__ . '/../bootstrap/dependencies.php';
$worker = $container->get(LikeService::class);
$worker->runReceiver();