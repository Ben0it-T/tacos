<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\TeamService;
use App\Service\UserService;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class ProfileController
{
    private $container;
    private $teamService;
    private $userService;


    public function __construct(ContainerInterface $container, TeamService $teamService, UserService $userService)
    {
        $this->container = $container;
        $this->teamService = $teamService;
        $this->userService = $userService;
    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $session = $request->getAttribute('session');

        $user = $this->userService->findUser($session['auth']['userId']);
        $role = $this->userService->findRole($user->getRole());
        $teams = $this->userService->getTeamsForUser($user->getId());

        $teams = $this->teamService->findAllTeamsWithTeamleadByUserId($user->getId());

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
            'teams' => $teams,
        );

        $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $flash->getFirstMessage('error');

        return $twig->render($response, 'profile.html.twig', $viewData);
    }

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $session = $request->getAttribute('session');

        $user = $this->userService->findUser($session['auth']['userId']);

        if ($user) {
            $errors = $this->userService->updateUserProfile($user, $data);
            if (empty($errors)) {
                $flash->addMessage('success', $translations['form_success_update']);
            }
            else {
                $flash->addMessage('error', $errors);
            }

            // redirect
            $url = $routeParser->urlFor('profile');
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        // redirect
        $url = $routeParser->urlFor('logout');
        return $response->withStatus(302)->withHeader('Location', $url);
    }
}
