<?php

namespace App\Controllers;

use App\Services\AdminService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Services\UserService;

class AdminController
{   
    public function __construct(
        protected UserService $userService,
        protected AdminService $adminService,
    ) {}

    public function index(Request $request, Response $response, $args): Response
    {
        $activeUsers = $this->userService->getActiveCount();
        $dayUsers = $this->adminService->getLastDayUsers();
        $hourUsers = $this->adminService->getLastHourUsers();

        $popularCategories = $this->adminService->getPopularCategories();
        $popularTags = $this->adminService->getPopularTags();
        $popularNews = $this->adminService->getPopularNews();

        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'admin.html.twig', [
            'active_users' => $activeUsers,
            'day_users' => $dayUsers,
            'hour_users' => $hourUsers,
            'popular_categories' => $popularCategories,
            'popular_tags' => $popularTags,
            'popular_news' => $popularNews,
        ]);
    }
}