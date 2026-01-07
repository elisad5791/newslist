<?php

namespace App\Controllers;

use App\Services\NewsService;
use App\Services\UserService;
use App\Session\SessionHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class NewsController
{
    public function __construct(
        protected UserService $userService,
        protected NewsService $newsService,
        protected SessionHelper $sessionHelper
    ) {} 

    public function index(Request $request, Response $response, $args): Response
    {
        $queryParams = $request->getQueryParams();
        $page = (int) ($queryParams['page'] ?? 1);
        
        $username = $this->sessionHelper->getUsername();
        $userId = $this->sessionHelper->getUserId();
        $isAdmin = $this->sessionHelper->getIsAdmin();

        $newsData = $this->newsService->getNewsPage($page);
        $pages = $this->newsService->getPageCount();
        $popular = $this->newsService->getPopular();
        $cityPopular = $this->newsService->getCityPopular($userId);
        $activeUsers = $this->userService->getActiveCount();
        $recently = $this->newsService->getRecently($userId);
        $prefs = $this->newsService->getPrefs($userId);

        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'news.html.twig', [
            'news' => $newsData['news'],
            'pages' => $pages,
            'current_page' => $page,
            'message' => $newsData['message'],
            'username' => $username,
            'popular' => $popular,
            'popular_city' => $cityPopular,
            'active_users' => $activeUsers,
            'recently' => $recently,
            'prefs' => $prefs,
            'is_admin' => $isAdmin,
        ]);
    }

    public function showNews(Request $request, Response $response, $args): Response
    {
        $newsId = (int) $args['id'];

        $newsData = $this->newsService->getNews($newsId);
        $viewsCount = $this->newsService->getViewsCount($newsId);
        
        $username = $this->sessionHelper->getUsername();
        $userId = $this->sessionHelper->getUserId();
        $isAdmin = $this->sessionHelper->getIsAdmin();
        $activeUsers = $this->userService->getActiveCount();

        $like = $this->newsService->getCurrentLike($userId, $newsId);
        $likeCount = $this->newsService->getLikeCount($newsId);

        $tagSimilar = $this->newsService->getTagSimilar($newsId);
        $categorySimilar = $this->newsService->getCategorySimilar($newsId);
        
        $this->newsService->updateRecently($userId, $newsData['item']);
        $this->newsService->updatePrefs($userId, $newsData['item']['category_id']);
        $this->newsService->updatePopularViews($newsData['item']);

        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'item.html.twig', [
            'item' => $newsData['item'],
            'views' => $viewsCount,
            'username' => $username,
            'message' => $newsData['message'],
            'active_users' => $activeUsers,
            'like' => $like,
            'like_count' => $likeCount,
            'tag_similar' => $tagSimilar,
            'category_similar' => $categorySimilar,
            'is_admin' => $isAdmin,
        ]);
    }

    public function showCategory(Request $request, Response $response, $args): Response
    {
        $categoryId = (int) $args['id'];

        $data = $this->newsService->getCategoryData($categoryId);
        $username = $this->sessionHelper->getUsername();
        $isAdmin = $this->sessionHelper->getIsAdmin();
        $activeUsers = $this->userService->getActiveCount();

        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'category.html.twig', [
            'news' => $data['news'],
            'message' => $data['message'],
            'username' => $username,
            'title' => $data['category_title'],
            'active_users' => $activeUsers,
            'is_admin' => $isAdmin,
        ]);
    }

    public function showTag(Request $request, Response $response, $args): Response
    {
        $tagId = (int) $args['id'];

        $data = $this->newsService->getTagData($tagId);
        $username = $this->sessionHelper->getUsername();
        $isAdmin = $this->sessionHelper->getIsAdmin();
        $activeUsers = $this->userService->getActiveCount();

        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'tag.html.twig', [
            'news' => $data['news'],
            'message' => $data['message'],
            'username' => $username,
            'title' => $data['tag_title'],
            'active_users' => $activeUsers,
            'is_admin' => $isAdmin,
        ]);
    }
}