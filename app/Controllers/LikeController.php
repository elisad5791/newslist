<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;
use Redis;

class LikeController
{   
    protected $pdo;
    protected $redis;

    public function __construct(PDO $pdo, Redis $redis)
    {
        $this->pdo = $pdo;
        $this->redis = $redis;
        session_start();
    }
    

    public function addLike(Request $request, Response $response)
    {
        $params = $request->getParsedBody();
        $newsId = (int) $params['news_id'];

        $userId = $_SESSION['userid'] ?? '';
        if (empty($userId) || empty($newsId)) {
            $errorResponse = [
                'status' => 'error',
                'message' => 'user_id and news_id are required'
            ];
            $response->getBody()->write(json_encode($errorResponse));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $action = ['user_id' => $userId, 'news_id' => $newsId];
    
        $this->redis->lPush('queue_like', json_encode($action));

        $successResponse = [ 'status' => 'success'];
        $response->getBody()->write(json_encode($successResponse));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }
}