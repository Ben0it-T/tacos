<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\ControllerHelper;
use App\Service\AuthService;
use App\Service\FlashMessageService;
use App\Security\AuthResult;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Views\Twig;

final class LoginController
{
    private Twig $twig;
    private FlashMessageService $flash;
    private AuthService $authService;
    private ControllerHelper $helper;
    private array $translations;

    public function __construct(Twig $twig, FlashMessageService $flash, AuthService $authService, ControllerHelper $helper, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->authService = $authService;
        $this->helper = $helper;
        $this->translations = $translations;
    }

    public function loginForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->authService->isAuthenticated()) {
            return $this->helper->redirect($request, $response, 'logout');
        }

        return $this->twig->render($response, 'login.html.twig', [
            'flashMsgError'   => $this->flash->getFirst('login-error'),
            'message'         => $this->flash->getFirst('change_password'),
            'password_forgot_url' => $this->helper->getUrlFor($request, 'forgot_password'),
        ]);
    }

    public function loginAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $identifier = $data['_login'] ?? '';
        $password   = $data['_password'] ?? '';
        $result = $this->authService->authenticate($identifier, $password);

        switch ($result) {
            case AuthResult::BLOCKED:
                return $this->helper->redirect($request, $response, 'too_many_attempts');

            case AuthResult::SUCCESS:
                return $this->helper->redirect($request, $response, 'timesheets');

            case AuthResult::INVALID_CREDENTIALS:
            default:
                $this->flash->add('login-error', $this->translations['form_error_credentials']);
                return $this->helper->redirect($request, $response, 'login');
        }
    }

    public function logoutAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->authService->logout();
        return $this->helper->redirect($request, $response, 'login');
    }

    public function tooManyAttempts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->twig->render($response->withStatus(429), 'too-many-attempts.html.twig', array());
    }
}
