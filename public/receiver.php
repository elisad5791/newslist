<?php
use App\Redis\UserRedisHelper;
use App\Repositories\UserRepository;

require_once __DIR__ . '/../vendor/autoload.php';

echo " [*] Waiting for messages. To exit press CTRL+C\n\n";

$container = require __DIR__ . '/../bootstrap/dependencies.php';
$userRedisHelper = $container->get(UserRedisHelper::class);
$userRepository = $container->get(UserRepository::class);

while (true) {
    $dataJson = $userRedisHelper->getQueueTask();

    $data = json_decode($dataJson[1], true); 
    $userId = $data['user_id'];
    $newsId = $data['news_id'];

    $userRepository->addLog($userId, $newsId);
}