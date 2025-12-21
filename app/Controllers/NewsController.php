<?php

namespace App\Controllers;

use App\Services\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Slim\Views\Twig;
use Redis;

class NewsController
{   
    const NEWS_FOR_PAGE = 9;
    protected $pdo;
    protected $redis;
    protected $userService;

    public function __construct(PDO $pdo, Redis $redis, UserService $userService)
    {
        $this->pdo = $pdo;
        $this->redis = $redis; 
        $this->userService = $userService;
        session_start();
    } 

    public function index(Request $request, Response $response, $args) 
    {
        $queryParams = $request->getQueryParams();
        $page = (int) ($queryParams['page'] ?? 1);
        $offset = self::NEWS_FOR_PAGE * ($page - 1);

        $cacheKey = 'list:page_' . $page;
        $cachedNews = $this->redis->get($cacheKey);
        $message = '';
        if ($cachedNews) {
            $news =  json_decode($cachedNews, true);
            $message = 'кеш';
        } else {
            $limit = self::NEWS_FOR_PAGE;

            $sql = "SELECT 
                    n.id, 
                    n.title, 
                    n.created_at,
                    c.id AS category_id, 
                    c.title AS category_title, 
                    GROUP_CONCAT(t.title SEPARATOR ',') AS tag_titles,
                    GROUP_CONCAT(t.id SEPARATOR ',') AS tag_ids
                FROM news n
                LEFT JOIN categories c ON c.id = n.category_id
                LEFT JOIN news_tags nt ON nt.news_id = n.id
                LEFT JOIN tags t ON t.id = nt.tag_id
                GROUP BY n.id, c.title
                ORDER BY n.created_at DESC 
                LIMIT $limit 
                OFFSET $offset";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $newsData = $stmt->fetchAll();

            $news = [];
            foreach ($newsData as $item) {
                $titles = explode(',', $item['tag_titles']);
                $ids = explode(',', $item['tag_ids']);
                $tags = array_map(function($el1, $el2) {
                    return ['id' => $el1, 'title' => $el2];
                }, $ids, $titles);
                unset($item['tag_titles']);
                unset($item['tag_ids']);
                $item['tags'] = $tags;
                $news[] = $item;
            }

            $this->redis->set($cacheKey, json_encode($news));
            $message = 'бд';
        }

        $newsList = [];
        foreach ($news as $item) {
            $views = $this->redis->zscore('news:top', $item['id']);
            $item['views'] = $views ? $views : 0;
            $newsList[] = $item;
        }

        $cacheKey = 'list:count';
        $cachedCount = $this->redis->get($cacheKey);
        if ($cachedCount) {
            $count =  $cachedCount;
        } else {
            $sql = "SELECT count(*) AS count FROM news";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $res = $stmt->fetch();
            $count = $res['count'];
            $this->redis->set($cacheKey, $count);
        }
        $pages = ceil($count / self::NEWS_FOR_PAGE);

        $popularIds = $this->redis->zrevrange('news:top', 0, 4, ['WITHSCORES' => true]);
        $popular = [];
        foreach ($popularIds as $id => $score) {
            $cacheKey = "item:news_$id";
            $cachedItem = $this->redis->get($cacheKey);
            if ($cachedItem) {
                $item = json_decode($cachedItem, true);
                $item['score'] = round($score);
                $popular[] = $item;
            }
        }

        $username = $_SESSION['username'] ?? '';
        $userId = $_SESSION['userid'] ?? 0;
        
        $activeUsers = $this->userService->getActiveCount();

        $recently = [];
        if (!empty($userId)) {
            $cacheKey = "recent:user_{$userId}";
            $data = $this->redis->lRange($cacheKey, 0, -1);
            foreach ($data as $item) {
                $recently[] = json_decode($item, true);
            }
        }

        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'news.html.twig', [
            'news' => $newsList,
            'pages' => $pages,
            'current_page' => $page,
            'message' => $message,
            'username' => $username,
            'popular' => $popular,
            'active_users' => $activeUsers,
            'recently' => $recently,
        ]);
    }

    public function showNews(Request $request, Response $response, $args)
    {
        $newsId = (int) $args['id'];

        $cacheKey = "item:news_$newsId";
        $cachedItem = $this->redis->get($cacheKey);
        $message = '';
        if ($cachedItem) {
            $item =  json_decode($cachedItem, true);
            $message = 'кеш';
        } else {
            $sql = "SELECT 
                    n.id, 
                    n.title, 
                    n.content,
                    n.created_at,
                    c.id AS category_id, 
                    c.title AS category_title, 
                    GROUP_CONCAT(t.title SEPARATOR ',') AS tag_titles,
                    GROUP_CONCAT(t.id SEPARATOR ',') AS tag_ids
                FROM news n
                LEFT JOIN categories c ON c.id = n.category_id
                LEFT JOIN news_tags nt ON nt.news_id = n.id
                LEFT JOIN tags t ON t.id = nt.tag_id
                WHERE n.id = ?
                GROUP BY n.id, c.title";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$newsId]);
            $item = $stmt->fetch();

            $titles = explode(',', $item['tag_titles']);
            $ids = explode(',', $item['tag_ids']);
            $tags = array_map(function($el1, $el2) {
                return ['id' => $el1, 'title' => $el2];
            }, $ids, $titles);
            unset($item['tag_titles']);
            unset($item['tag_ids']);
            $item['tags'] = $tags;

            $this->redis->set($cacheKey, json_encode($item));
            $message = 'бд';
        }

        $viewsCount = $this->redis->zincrby('news:top', 1, $newsId);

        $username = $_SESSION['username'] ?? '';
        $userId = $_SESSION['userid'] ?? 0;
        $activeUsers = $this->userService->getActiveCount();

        $like = false;
        if (!empty($userId)) {
            $cacheKey = "likes:user_$userId";
            $likes = $this->redis->sMembers($cacheKey);
            $like = in_array($newsId, $likes);
        }

        $cacheKey = 'likes:news';
        $likeCount = $this->redis->hGet($cacheKey, $newsId);
        if (empty($likeCount)) {
            $likeCount = 0;
        }

        $cacheKey = "tag:similar:news_$newsId";
        $data = $this->redis->zRevRange($cacheKey, 0, -1);
        if (!empty($data)) {
            $tagSimilar = [];
            foreach ($data as $news) {
                $tagSimilar[] = json_decode($news, true);
            }
        } else {
            $sql = "SELECT nt2.news_id, COUNT(*) as common_tags
                FROM news_tags nt1
                JOIN news_tags nt2 ON nt2.tag_id = nt1.tag_id
                WHERE nt1.news_id = ? AND nt2.news_id != ?
                GROUP BY nt2.news_id
                HAVING common_tags > 0
                ORDER BY common_tags DESC
                LIMIT 5";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$newsId, $newsId]);
            $data = $stmt->fetchAll();
            $ids = array_column($data, 'news_id');
            $counts = array_column($data, 'common_tags', 'news_id');

            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $orderField = 'FIELD(id, ' . str_repeat('?,', count($ids) - 1) . '?)';
            $sql = "SELECT id, title, created_at 
                FROM news 
                WHERE id IN ($placeholders) 
                ORDER BY $orderField";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([...$ids, ...$ids]);
            $tagSimilar = $stmt->fetchAll();

            foreach ($tagSimilar as $news) {
                $val = json_encode($news);
                $this->redis->zAdd($cacheKey, $counts[$news['id']], $val);
            }
        }

        $cacheKey = "category:similar:news_$newsId";
        $data = $this->redis->sMembers($cacheKey);
        if (!empty($data)) {
            $categorySimilar = [];
            foreach ($data as $news) {
                $categorySimilar[] = json_decode($news, true);
            }
        } else {
            $sql = "SELECT n2.id, n2.title, n2.created_at, c.title AS category_title
                FROM news n1
                JOIN news n2 ON n2.category_id = n1.category_id AND n2.id != n1.id
                LEFT JOIN categories c ON c.id = n1.category_id
                WHERE n1.id = ?
                ORDER BY n2.created_at DESC
                LIMIT 5";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$newsId]);
            $categorySimilar = $stmt->fetchAll();

            foreach ($categorySimilar as $news) {
                $val = json_encode($news);
                $this->redis->zAdd($cacheKey, $counts[$news['id']], $val);
            }
        }

        if (!empty($userId)) {
            $key = "recent:user_{$userId}";
            $shortItem = [
                'id' => $item['id'],
                'title' => $item['title'],
                'created_at' => $item['created_at'],
            ];
            $val = json_encode($shortItem);
            $this->redis->lPush($key, $val);
            $this->redis->lTrim($key, 0, 4);
        }
          
        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'item.html.twig', [
            'item' => $item,
            'views' => $viewsCount,
            'username' => $username,
            'message' => $message,
            'active_users' => $activeUsers,
            'like' => $like,
            'like_count' => $likeCount,
            'tag_similar' => $tagSimilar,
            'category_similar' => $categorySimilar,
        ]);
    }

    public function showCategory(Request $request, Response $response, $args)
    {
        $categoryId = (int) $args['id'];

        $cacheKey = 'list:category_' . $categoryId;
        $cachedNews = $this->redis->get($cacheKey);
        $message = '';
        if ($cachedNews) {
            $news =  json_decode($cachedNews, true);
            $message = 'кеш';
        } else {
            $sql = "SELECT id, title, created_at FROM news WHERE category_id = ? ORDER BY created_at DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$categoryId]);
            $news = $stmt->fetchAll();

            $this->redis->set($cacheKey, json_encode($news));
            $message = 'бд';
        }

        $cacheKey = 'title:category_' . $categoryId;
        $cachedTitle = $this->redis->get($cacheKey);
        if ($cachedTitle) {
            $categoryTitle =  $cachedTitle;
        } else {
            $sql = "SELECT title FROM categories WHERE id = ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$categoryId]);
            $data = $stmt->fetch();
            $categoryTitle = $data['title'];

            $this->redis->set($cacheKey, $categoryTitle);
        }

        $username = $_SESSION['username'] ?? '';

        $activeUsers = $this->userService->getActiveCount();

        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'category.html.twig', [
            'news' => $news,
            'message' => $message,
            'username' => $username,
            'title' => $categoryTitle,
            'active_users' => $activeUsers,
        ]);
    }

    public function showTag(Request $request, Response $response, $args)
    {
        $tagId = (int) $args['id'];

        $cacheKey = 'list:tag_' . $tagId;
        $cachedNews = $this->redis->get($cacheKey);
        $message = '';
        if ($cachedNews) {
            $news =  json_decode($cachedNews, true);
            $message = 'кеш';
        } else {
            $sql = "SELECT n.id, n.title, n.created_at 
            FROM news n
            LEFT JOIN news_tags nt ON nt.news_id = n.id
            WHERE nt.tag_id = ? 
            ORDER BY n.created_at DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tagId]);
            $news = $stmt->fetchAll();

            $this->redis->set($cacheKey, json_encode($news));
            $message = 'бд';
        }

        $cacheKey = 'title:tag_' . $tagId;
        $cachedTitle = $this->redis->get($cacheKey);
        if ($cachedTitle) {
            $tagTitle =  $cachedTitle;
        } else {
            $sql = "SELECT title FROM tags WHERE id = ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tagId]);
            $data = $stmt->fetch();
            $tagTitle = $data['title'];

            $this->redis->set($cacheKey, $tagTitle);
        }

        $username = $_SESSION['username'] ?? '';
        $activeUsers = $this->userService->getActiveCount();

        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'tag.html.twig', [
            'news' => $news,
            'message' => $message,
            'username' => $username,
            'title' => $tagTitle,
            'active_users' => $activeUsers,
        ]);
    }
}