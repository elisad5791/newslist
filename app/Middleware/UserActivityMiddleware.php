<?php
namespace App\Middleware;

use App\Services\UserService;
use App\Session\SessionHelper;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface as Middleware;

class UserActivityMiddleware implements Middleware
{
    public function __construct(
        protected UserService $userService,
        protected SessionHelper $sessionHelper
    ) {}

    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);
        
        $userId = $this->sessionHelper->getUserId();

        if ($userId) {
            $this->userService->trackUserActivity($userId);
        }
        
        return $response;
    }
}