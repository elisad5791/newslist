<?php
use DI\Container;

$container = new Container ();

$settings = require __DIR__ . '/../config/settings.php';

$container->set(PDO::class, function () use ($settings) {
    $host = $settings['database']['host'];
    $port = $settings['database']['port'];
    $dbname = $settings['database']['database'];
    $username = $settings['database']['username'];
    $password = $settings['database']['password'];
     
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
        
    return $pdo;
});

$container->set(Redis::class, function () use ($settings) {
    $host = $settings['redis']['host'];
    $port = $settings['redis']['port'];
    $password = $settings['redis']['password'];
     
    $redis = new Redis();
    $redis->connect($host, $port);
    $redis->auth($password);

    return $redis;
});

return $container;
