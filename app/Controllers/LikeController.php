<?php

namespace App\Controllers;

use App\Services\LikeService;
use App\Session\SessionHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LikeController
{   
    public function __construct(
        protected SessionHelper $sessionHelper,
        protected LikeService $likeService,
    ) {}
    
    public function addLike(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $newsId = (int) $params['news'];

        $userId = $this->sessionHelper->getUserId();
        if (empty($userId) || empty($newsId)) {
            return $response->withHeader('Location', "/news/$newsId")->withStatus(302);
        }

        $this->likeService->addLike($userId, $newsId);

        return $response->withHeader('Location', "/news/$newsId")->withStatus(302);
    }

    public function removeLike(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $newsId = (int) $params['news'];

        $userId = $this->sessionHelper->getUserId();
        if (empty($userId) || empty($newsId)) {
            return $response->withHeader('Location', "/news/$newsId")->withStatus(302);
        }

        $this->likeService->removeLike($userId, $newsId);

        return $response->withHeader('Location', "/news/$newsId")->withStatus(302);
    }
}
