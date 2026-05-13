<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Security\AuthResult;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class LoginController
{
    private $container;
    private $authService;

    public function __construct(ContainerInterface $container, AuthService $authService)
    {
        $this->container = $container;
        $this->authService = $authService;
    }

    public function loginForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $session = $request->getAttribute('session');
        $isLoggedIn = (isset($session['auth']) && $session['auth']['isLoggedIn'] === true) ? true : false;
        if ($isLoggedIn) {
            $url = $routeParser->urlFor('logout');
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        $twig = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $viewData = array();
        $viewData['flashMsgError'] = $flash->getFirstMessage('login-error');
        $viewData['message'] = $flash->getFirstMessage('change_password');
        $viewData['password_forgot_url'] = $routeParser->urlFor('forgot_password');

        return $twig->render($response, 'login.html.twig', $viewData);
    }

    public function loginAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $identifier = $request->getParsedBody()['_login'] ?? '';
        $password   = $request->getParsedBody()['_password'] ?? '';

        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $result = $this->authService->authenticate($identifier, $password);


        switch ($result) {
            case AuthResult::BLOCKED:
                $url = $routeParser->urlFor('too_many_attempts');
                return $response->withStatus(302)->withHeader('Location', $url);

            case AuthResult::SUCCESS:
                $url = $routeParser->urlFor('timesheets');
                return $response->withStatus(302)->withHeader('Location', $url);

            case AuthResult::INVALID_CREDENTIALS:
            default:
                $translations = $this->container->get('translations');
                $this->container->get('flash')->addMessage('login-error', $translations['form_error_credentials']);
                $url = $routeParser->urlFor('login');
                return $response->withStatus(302)->withHeader('Location', $url);
        }
    }

    public function logoutAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->authService->logout();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $url = $routeParser->urlFor('login');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function tooManyAttempts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $twig = $this->container->get(Twig::class);
        return $twig->render($response->withStatus(429), 'too-many-attempts.html.twig', array());
    }
}
