<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Redis;

class AuthController
{   
    protected $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis; 
        session_start();
    } 

    public function index(Request $request, Response $response, $args) 
    {
        $view = Twig::fromRequest($request);
    
        return $view->render($response, 'auth.html.twig');
    }

    public function submitName(Request $request, Response $response, $args)
    {
        $params = (array) $request->getParsedBody();
        $name = htmlspecialchars($params['name']);
        $_SESSION['username'] = $name;
        return $response->withHeader('Location', '/')->withStatus(302);
    }
}