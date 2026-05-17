<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\PasswordRequestService;
use App\Security\ResetPasswordResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class PasswordResetController
{
    private Twig $twig;
    private Messages $flash;
    private PasswordRequestService $passwordRequestService;
    private array $options;
    private array $translations;

    public function __construct(Twig $twig, Messages $flash, PasswordRequestService $passwordRequestService, array $options, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->passwordRequestService = $passwordRequestService;
        $this->options = $options;
        $this->translations = $translations;
    }

    public function requestForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $viewData = array();
        $viewData['message'] = $this->flash->getFirstMessage('password-request');

        return $this->twig->render($response, 'forgot-password.html.twig', $viewData);
    }

    public function requestAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();
        $retryLifetime = intval($this->options['pwdRequestRetryLifetime']);
        $retryLifetimeStr = sprintf("%d", $retryLifetime/60);

        $username = $data['_username'] ?? '';
        if ($username !== '') {
            $resetLinkBase = $this->fullUrlFor($request, 'change_password', array('key' => ''));
            $this->passwordRequestService->newPasswordRequest($username, $resetLinkBase);
        }

        $message = str_replace("%timelimit%", $retryLifetimeStr, $this->translations['form_message_password_request']);
        $this->flash->addMessage('password-request', $message);

        // redirect
        $url = $this->getUrlFor($request, 'forgot_password');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function changeForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = $args['key'] ?? '';
        if ($this->passwordRequestService->validateToken($token)) {
            $viewData = array();
            $viewData['form']['minlength'] = $this->options['pwdMinLength'];

            $viewData['message'] = $this->flash->getFirstMessage('change_password');
            return $this->twig->render($response, 'change-password.html.twig', $viewData);
        }

        // redirect
        $url = $this->getUrlFor($request, 'login');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function changeAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();
        $password1 = $data['_password1'] ?? '';
        $password2 = $data['_password2'] ?? '';
        $token = $args['key'] ?? '';

        $result = $this->passwordRequestService->updatePasswordFromToken($token, $password1, $password2);

        switch ($result) {
            case ResetPasswordResult::INVALID_PASSWORD:
                $message = str_replace("%minLength%", sprintf("%d", $this->options['pwdMinLength']), $this->translations['form_error_password_strength']);
                $this->flash->addMessage('change_password', $message);
                $url = $this->getUrlFor($request, 'change_password', array('key' => $token));
                return $response->withStatus(302)->withHeader('Location', $url);

            case ResetPasswordResult::PASSWORD_MISMATCH:
                $this->flash->addMessage('change_password', $this->translations['form_error_password_not_egal']);
                $url = $this->getUrlFor($request, 'change_password', array('key' => $token));
                return $response->withStatus(302)->withHeader('Location', $url);

            case ResetPasswordResult::SUCCESS:
                $this->flash->addMessage('change_password', $this->translations['form_message_password_changed']);
                $url = $this->getUrlFor($request, 'login');
                return $response->withStatus(302)->withHeader('Location', $url);

            default:
                // INVALID_TOKEN
                // ERROR
                $url = $this->getUrlFor($request, 'login');
                return $response->withStatus(302)->withHeader('Location', $url);
        }
    }

    private function getUrlFor(ServerRequestInterface $request, string $routeName, array $data = []): string
    {
        return RouteContext::fromRequest($request)->getRouteParser()->urlFor($routeName, $data);
    }

    private function fullUrlFor(ServerRequestInterface $request, string $routeName, array $data = []): string
    {
        return RouteContext::fromRequest($request)->getRouteParser()->fullUrlFor($request->getUri(), $routeName, $data);
    }
}
