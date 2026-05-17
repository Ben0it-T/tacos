<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\ControllerHelper;
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
    private ControllerHelper $helper;
    private array $options;
    private array $translations;

    public function __construct(Twig $twig, Messages $flash, PasswordRequestService $passwordRequestService, ControllerHelper $helper, array $options, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->passwordRequestService = $passwordRequestService;
        $this->helper = $helper;
        $this->options = $options;
        $this->translations = $translations;
    }

    public function requestForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->twig->render($response, 'forgot-password.html.twig', [
            'message' => $this->flash->getFirstMessage('password-request'),
        ]);
    }

    public function requestAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $retryLifetime = intval($this->options['pwdRequestRetryLifetime']);
        $retryLifetimeStr = sprintf("%d", $retryLifetime/60);

        $username = $data['_username'] ?? '';
        if ($username !== '') {
            $resetLinkBase = $this->helper->fullUrlFor($request, 'change_password', array('key' => ''));
            $this->passwordRequestService->newPasswordRequest($username, $resetLinkBase);
        }

        $message = str_replace("%timelimit%", $retryLifetimeStr, $this->translations['form_message_password_request']);
        $this->flash->addMessage('password-request', $message);

        return $this->helper->redirect($request, $response, 'forgot_password');
    }

    public function changeForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = $args['key'] ?? '';
        if ($this->passwordRequestService->validateToken($token)) {
            return $this->twig->render($response, 'change-password.html.twig', [
                'message' => $this->flash->getFirstMessage('change_password'),
                'form'    => [
                    'minlength' => $this->options['pwdMinLength'],
                ],
            ]);
        }

        return $this->helper->redirect($request, $response, 'login');
    }

    public function changeAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $password1 = $data['_password1'] ?? '';
        $password2 = $data['_password2'] ?? '';
        $token = $args['key'] ?? '';

        $result = $this->passwordRequestService->updatePasswordFromToken($token, $password1, $password2);

        switch ($result) {
            case ResetPasswordResult::INVALID_PASSWORD:
                $message = str_replace("%minLength%", sprintf("%d", $this->options['pwdMinLength']), $this->translations['form_error_password_strength']);
                $this->flash->addMessage('change_password', $message);
                return $this->helper->redirect($request, $response, 'change_password', array('key' => $token));

            case ResetPasswordResult::PASSWORD_MISMATCH:
                $this->flash->addMessage('change_password', $this->translations['form_error_password_not_egal']);
                return $this->helper->redirect($request, $response, 'change_password', array('key' => $token));

            case ResetPasswordResult::SUCCESS:
                $this->flash->addMessage('change_password', $this->translations['form_message_password_changed']);
                return $this->helper->redirect($request, $response, 'login');

            default:
                // INVALID_TOKEN
                // ERROR
                return $this->helper->redirect($request, $response, 'login');
        }
    }
}
