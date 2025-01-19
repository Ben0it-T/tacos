<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\UserService;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;


final class UsersController
{
    private $container;
    private $userService;

    public function __construct(ContainerInterface $container, UserService $userService)
    {
        $this->container = $container;
        $this->userService = $userService;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $users = $this->userService->findAllUsers();
        $roles = $this->userService->findAllRoles();

        $usersList = array();
        foreach ($users as $user) {
            $usersList[] = array(
                'id' => $user->getId(),
                'name' => $user->getName(),
                'username' => $user->getUsername(),
                'role' => $translations[strtolower($roles[$user->getRole()]->getName())],
                'teams' => $this->userService->getNbOfTeamsForUser($user->getId()),
                'lastLogin' => $user->getLastLogin(),
                'enable' => $user->getEnabled() ? true : false,
                'editLink' => $routeParser->urlFor('users_edit', array('username' => $user->getUsername())),
            );
        }

        $viewData = array();
        $viewData['form'] = array(
            'loginMinLength' => $this->container->get('settings')['auth']['loginMinLength'],
            'pwdMinLength' => $this->container->get('settings')['auth']['pwdMinLength'],
        );
        $viewData['users'] = $usersList;

        $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $flash->getFirstMessage('error');

        return $twig->render($response, 'users.html.twig', $viewData);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $errors = $this->userService->createUser($data);

        if (empty($errors)) {
            $flash->addMessage('success', $translations['form_success_create_user']);
        }
        else {
            $flash->addMessage('error', $errors);
        }

        // redirect
        $url = $routeParser->urlFor('users');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $user = $this->userService->findUserByUsername($args['username']);
        if ($user) {
            $role = $this->userService->findRole($user->getRole());
            $roles = $this->userService->findAllRoles();
            $teams = $this->userService->getTeamsForUser($user->getId());

            $viewData = array();
            $viewData['form'] = array(
                'loginMinLength' => $this->container->get('settings')['auth']['loginMinLength'],
                'pwdMinLength' => $this->container->get('settings')['auth']['pwdMinLength'],
            );
            $viewData['user'] = array(
                'name' => $user->getName(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'role' => $translations[strtolower($role->getName())],
                'roleId' => $user->getRole(),
                'lastLogin' => $user->getLastLogin(),
                'registrationDate' => $user->getRegistrationDate(),
                'status' => $user->getEnabled(),
                'teams' => $teams,

            );

            $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
            $viewData['flashMsgError'] = $flash->getFirstMessage('error');

            return $twig->render($response, 'user-edit.html.twig', $viewData);
        }

        // redirect
        $url = $routeParser->urlFor('users');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $user = $this->userService->findUserByUsername($args['username']);
        if ($user) {
            $errors = $this->userService->updateUser($user, $data);

            if (empty($errors)) {
                $flash->addMessage('success', $translations['form_success_update']);
            }
            else {
                $flash->addMessage('error', $errors);
            }

            // redirect
            $url = $routeParser->urlFor('users_edit', array('username' => $args['username']));
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        // redirect
        $url = $routeParser->urlFor('users');
        return $response->withStatus(302)->withHeader('Location', $url);
    }
}
