<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Redis;

class UserActivityMiddleware implements Middleware
{
    protected $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);
        
        session_start();
        $userId = $_SESSION['userid'] ?? null;
        
        if ($userId) {
            $this->trackUserActivity($userId);
        }
        
        return $response;
    }

    private function trackUserActivity($userId)
    {
        $userKey = "user_active:{$userId}";
        $this->redis->setex($userKey, 300, 'active');
        $setKey = "active_users";
        $this->redis->sAdd($setKey, $userId);
        $this->redis->expire($setKey, 300);
    }
}