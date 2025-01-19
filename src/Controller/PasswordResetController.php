<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\PasswordRequestService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class PasswordResetController
{
    private $container;
    private $passwordRequest;

    public function __construct(ContainerInterface $container, PasswordRequestService $passwordRequest)
    {
        $this->container = $container;
        $this->passwordRequest = $passwordRequest;
    }

    public function requestForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig = $this->container->get(Twig::class);
        $viewData = array();
        $flash = $this->container->get('flash');
        $viewData['message'] = $flash->getFirstMessage('password-request');

        return $twig->render($response, 'forgot-password.html.twig', $viewData);
    }

    public function requestAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $translations = $this->container->get('translations');
        $retryLifetime = intval($this->container->get('settings')['auth']['pwdRequestRetryLifetime']);
        $retryLifetimeStr = sprintf("%d", $retryLifetime/60);

        if (!empty($data['_username'])) {
            $this->passwordRequest->newPasswordRequest($data['_username'], $request);
        }

        $message = str_replace("%timelimit%", $retryLifetimeStr, $translations['form_message_password_request']);
        $this->container->get('flash')->addMessage('password-request', $message);

        // redirect
        $url = $routeParser->urlFor('forgot_password');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function changeForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->passwordRequest->validateToken($args['key'])) {
            $twig = $this->container->get(Twig::class);
            $viewData = array();
            $viewData['form']['minlength'] = $this->container->get('settings')['auth']['pwdMinLength'];

            $flash = $this->container->get('flash');
            $viewData['message'] = $flash->getFirstMessage('change_password');
            return $twig->render($response, 'change-password.html.twig', $viewData);
        }

        // redirect
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $url = $routeParser->urlFor('login');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function changeAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        if ($this->passwordRequest->validateToken($args['key'])) {
            $translations = $this->container->get('translations');
            $flash = $this->container->get('flash');
            $validation = true;
            $minLength = $this->container->get('settings')['auth']['pwdMinLength'];
            if (!$this->passwordRequest->validatePasswordStrength($data['_password1'])) {
                $validation = false;
                $message = str_replace("%minLength%", sprintf("%d", $minLength), $translations['form_error_password_strength']);
                $flash->addMessage('change_password', $message);
            }
            else if (strcmp($data['_password1'], $data['_password2']) !== 0) {
                $validation = false;
                $flash->addMessage('change_password', $translations['form_error_password_not_egal']);
            }

            if ($validation) {
                if ($this->passwordRequest->setUserPassword($args['key'], $data['_password1'])) {
                    $flash->addMessage('change_password', $translations['form_message_password_changed']);
                    $url = $routeParser->urlFor('login');
                    return $response->withStatus(302)->withHeader('Location', $url);
                }
            }

            // redirect
            $url = $routeParser->urlFor('change_password', array('key' => $args['key']));
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        // redirect
        $url = $routeParser->urlFor('login');
        return $response->withStatus(302)->withHeader('Location', $url);
    }
}
