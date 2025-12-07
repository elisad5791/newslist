<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Slim\Views\Twig;
use Redis;

class NewsController
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
        $queryParams = $request->getQueryParams();
        $page = (int) ($queryParams['page'] ?? 1);
        $offset = 10 * ($page - 1);

        $cacheKey = 'news:list:page_' . $page;
        $cachedNews = $this->redis->get($cacheKey);
        $message = '';
        if ($cachedNews) {
            $news =  json_decode($cachedNews);
            $message = 'from cache';
        } else {
            $sql = "SELECT * FROM news ORDER BY created_at DESC LIMIT 10 OFFSET $offset";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $news = $stmt->fetchAll();
            $this->redis->setex($cacheKey, 300, json_encode($news));
            $message = 'from db';
        }

        $cacheKey = 'news:list:count';
        $cachedCount = $this->redis->get($cacheKey);
        if ($cachedCount) {
            $count =  $cachedCount;
        } else {
            $sql = "SELECT count(*) AS count FROM news";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $res = $stmt->fetch();
            $count = $res['count'];
            $this->redis->setex($cacheKey, 300, $count);
        }
        $pages = ceil($count / 10);

        $cacheKey = 'news:views:page_' . $page;
        $viewsCount = $this->redis->incr($cacheKey);

        $username = $_SESSION['username'] ?? '';

        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'news.html.twig', [
            'news' => $news,
            'pages' => $pages,
            'current_page' => $page,
            'message' => $message,
            'views' => $viewsCount,
            'username' => $username,
        ]);
    }
}