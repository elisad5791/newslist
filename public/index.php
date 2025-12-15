<?php
use Slim\Factory\AppFactory;
use App\Controllers\NewsController;
use App\Controllers\AuthController;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Middleware\CounterMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$settings = require __DIR__ . '/../config/settings.php';
$host = $settings['redis']['host'];
$port = $settings['redis']['port'];
$password = $settings['redis']['password'];

ini_set('session.save_handler', 'redis');
ini_set('session.save_path', "tcp://$host:$port?auth=$password");

$container = require __DIR__ . '/../bootstrap/dependencies.php';
AppFactory::setContainer($container);
$app = AppFactory::create();

$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));
$app->add(CounterMiddleware::class);

$app->get('/', [NewsController::class, 'index']);
$app->get('/news/{id}', [NewsController::class, 'showNews']);
$app->get('/category/{id}', [NewsController::class, 'showCategory']);
$app->get('/tag/{id}', [NewsController::class, 'showTag']);
$app->get('/auth', [AuthController::class, 'index']);
$app->post('/submit-name', [AuthController::class, 'submitName']);

$app->run();
