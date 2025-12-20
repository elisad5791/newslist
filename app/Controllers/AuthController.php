<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;
use Redis;

class AuthController
{   
    protected $pdo;
    protected $redis;

    public function __construct(PDO $pdo, Redis $redis)
    {
        $this->pdo = $pdo;
        $this->redis = $redis;
        session_start();
    }
    public function index(Request $request, Response $response, $args) 
    {
        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'auth.html.twig');
    }

    public function submitName(Request $request, Response $response, $args)
    {
        $params = (array) $request->getParsedBody();
        $name = htmlspecialchars($params['name']);

        $sql = "SELECT * FROM users WHERE first_name = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name]);
        $user = $stmt->fetch();
        $userId = $user['id'] ?? null;

        if (empty($user)) {
            $sql = "INSERT INTO users(first_name, last_name, email) VALUES(?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$name, $name, $name . '@mail.ru']);
            $userId = $this->pdo->lastInsertId();
            $likes = [];
        } else {
            $sql = "SELECT news_id FROM users_likes WHERE user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            $likes = $stmt->fetchAll();
            $likes = array_column($likes, 'news_id');
        }
        
        $_SESSION['username'] = $name;
        $_SESSION['userid'] = $userId;
        /*$cacheKey = 'news:likes:user_' . $userId;
        $this->redis->sAdd($cacheKey, ...$likes);*/
        
        return $response->withHeader('Location', '/')->withStatus(302);
    }
}