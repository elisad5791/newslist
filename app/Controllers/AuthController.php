<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Services\AuthService;

class AuthController
{   
    public function __construct(
        protected AuthService $authService
    ) {}

    public function index(Request $request, Response $response, $args): Response 
    {
        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'auth.html.twig');
    }

    public function submitName(Request $request, Response $response, $args): Response
    {
        $params = (array) $request->getParsedBody();
        $name = htmlspecialchars($params['name']);

        
        $userId = $this->authService->setUser($name);
        $this->authService->setLikes($userId);
        
        return $response->withHeader('Location', '/')->withStatus(302);
    }
}