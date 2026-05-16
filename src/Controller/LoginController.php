<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Security\AuthResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class LoginController
{
    private Twig $twig;
    private Messages $flash;
    private AuthService $authService;
    private array $translations;

    public function __construct(Twig $twig, Messages $flash, AuthService $authService, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->authService = $authService;
        $this->translations = $translations;
    }

    public function loginForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->authService->isAuthenticated()) {
            $url = $this->getUrlFor($request, 'logout');
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        $viewData = array();
        $viewData['flashMsgError'] = $this->flash->getFirstMessage('login-error');
        $viewData['message'] = $this->flash->getFirstMessage('change_password');
        $viewData['password_forgot_url'] = $this->getUrlFor($request, 'forgot_password');

        return $this->twig->render($response, 'login.html.twig', $viewData);
    }

    public function loginAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();
        $identifier = $data['_login'] ?? '';
        $password   = $data['_password'] ?? '';
        $result = $this->authService->authenticate($identifier, $password);

        switch ($result) {
            case AuthResult::BLOCKED:
                $url = $this->getUrlFor($request, 'too_many_attempts');
                return $response->withStatus(302)->withHeader('Location', $url);

            case AuthResult::SUCCESS:
                $url = $this->getUrlFor($request, 'timesheets');
                return $response->withStatus(302)->withHeader('Location', $url);

            case AuthResult::INVALID_CREDENTIALS:
            default:
                $this->flash->addMessage('login-error', $this->translations['form_error_credentials']);
                $url = $this->getUrlFor($request, 'login');
                return $response->withStatus(302)->withHeader('Location', $url);
        }
    }

    public function logoutAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->authService->logout();
        $url = $this->getUrlFor($request, 'login');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function tooManyAttempts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->twig->render($response->withStatus(429), 'too-many-attempts.html.twig', array());
    }

    private function getUrlFor(ServerRequestInterface $request, string $routeName): string
    {
        return RouteContext::fromRequest($request)->getRouteParser()->urlFor($routeName);
    }
}
