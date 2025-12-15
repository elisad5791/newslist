<?php
namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Services\UserService;

class CounterMiddleware implements Middleware
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $counter = $this->userService->checkRequestRate($ip);

        if ($counter > 10 || $counter == 0) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write('Request rejected');
            return $response->withStatus(429);
        }
        
        return $handler->handle($request);
    }
}
