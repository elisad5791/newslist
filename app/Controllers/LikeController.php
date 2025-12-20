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
        $newsId = (int) $params['news'];

        $userId = $_SESSION['userid'] ?? '';
        if (empty($userId) || empty($newsId)) {
            return $response->withHeader('Location', "/news/$newsId")->withStatus(302);
        }

        $sql = "SELECT * FROM users_likes WHERE user_id = ? AND news_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $newsId]);
        $res = $stmt->fetch();

        if (!empty($res)) {
            return $response->withHeader('Location', "/news/$newsId")->withStatus(302);
        }

        $sql = "INSERT INTO users_likes(user_id, news_id) VALUES(?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $newsId]);

        $cacheKey = "likes:user_$userId";
        $this->redis->sAdd($cacheKey, $newsId);

        $cacheKey = 'likes:news';
        $this->redis->hIncrBy($cacheKey, $newsId, 1);

        $action = ['user_id' => $userId, 'news_id' => $newsId];
        $this->redis->lPush('queue_like', json_encode($action));

        return $response->withHeader('Location', "/news/$newsId")->withStatus(302);
    }

    public function removeLike(Request $request, Response $response)
    {
        $params = $request->getParsedBody();
        $newsId = (int) $params['news'];

        $userId = $_SESSION['userid'] ?? '';
        if (empty($userId) || empty($newsId)) {
            return $response->withHeader('Location', "/news/$newsId")->withStatus(302);
        }

        $sql = "SELECT * FROM users_likes WHERE user_id = ? AND news_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $newsId]);
        $res = $stmt->fetch();

        if (empty($res)) {
            return $response->withHeader('Location', "/news/$newsId")->withStatus(302);
        }

        $sql = "DELETE FROM users_likes WHERE user_id = ? AND news_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $newsId]);

        $cacheKey = "likes:user_$userId";
        $this->redis->sRem($cacheKey, $newsId); 

        $cacheKey = 'likes:news';
        $this->redis->hIncrBy($cacheKey, $newsId, -1);

        return $response->withHeader('Location', "/news/$newsId")->withStatus(302);
    }
}
