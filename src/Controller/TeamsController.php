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


final class TeamsController
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

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $teams = ($currentUser->getRole() === 3) ? $this->teamService->findAllTeamsWithUserCountAndTeamleads() : $this->teamService->findAllTeamsWithUserCountAndTeamleadsByTeamleaderId($currentUser->getId());
        for ($i=0; $i < count($teams); $i++) {
            $teams[$i]['editLink'] = $routeParser->urlFor('teams_edit', array('teamId' => $teams[$i]['id']));
            $teams[$i]['viewLink'] = $routeParser->urlFor('teams_details', array('teamId' => $teams[$i]['id']));
        }

        $users = $this->userService->findAllUsersEnabled();
        $usersList = array();
        foreach ($users as $user) {
            $usersList[] = array(
                'id' => $user->getId(),
                'name' => $user->getName(),
            );
        }

        $colorChoices = $this->container->get('settings')['theme']['colorChoices'];
        $colorsList = array();
        foreach (explode(',',$colorChoices) as $key => $value) {
            list($colorName, $colorValue) = explode('|', $value);
            //$colorsList[$colorName] = $colorValue;
            $colorsList[$key] = array(
                'name' => $colorName,
                'value' => $colorValue,
            );
        }

        $viewData = array();
        $viewData['canCreateTeam'] = ($currentUser->getRole() === 3) ? true : false;
        $viewData['colors'] = $colorsList;
        $viewData['teams'] = $teams;
        $viewData['users'] = $usersList;

        $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $flash->getFirstMessage('error');

        return $twig->render($response, 'teams.html.twig', $viewData);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $errors = $this->teamService->createTeam($data);

        if (empty($errors)) {
            $flash->addMessage('success', $translations['form_success_create_team']);
        }
        else {
            $flash->addMessage('error', $errors);
        }

        // redirect
        $url = $routeParser->urlFor('teams');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function teamsDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $team = ($currentUser->getRole() === 3) ? $this->teamService->findTeam(intval($args['teamId'])) : $this->teamService->findTeamByIdAndTeamleader(intval($args['teamId']), $currentUser->getId());
        if ($team) {
            $teamMembers = $this->teamService->getTeamMembers($team->getId());
            $teamleaders = $this->teamService->getTeamTeamleaders($team->getId());
            $teamleadersList = array();
            foreach ($teamleaders as $teamleader) {
                $teamleadersList[] = $teamleader['name'];
            }

            $viewData = array();
            $viewData['team'] = $team;
            $viewData['teamMembers'] = $teamMembers;
            $viewData['teamleaders'] = $teamleadersList ? implode(", ", $teamleadersList) : "";

            return $twig->render($response, 'team-details.html.twig', $viewData);
        }

        // redirect
        $url = $routeParser->urlFor('teams');
        return $response->withStatus(302)->withHeader('Location', $url);

    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $team = ($currentUser->getRole() === 3) ? $this->teamService->findTeam(intval($args['teamId'])) : $this->teamService->findTeamByIdAndTeamleader(intval($args['teamId']), $currentUser->getId());
        if ($team) {
            $users = $this->userService->findAllUsersEnabled();
            $usersList = array();
            foreach ($users as $user) {
                $usersList[] = array(
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                );
            }

            $teamMembers = $this->teamService->getTeamMembers($team->getId());
            $teamMembersIds = array();
            foreach ($teamMembers as $teamMember) {
                $teamMembersIds[] = $teamMember['userId'];
            }

            $teamleaders = $this->teamService->getTeamTeamleaders($team->getId());
            $teamleadersList = array();
            foreach ($teamleaders as $teamleader) {
                $teamleadersList[] = $teamleader['name'];
            }

            $colorChoices = $this->container->get('settings')['theme']['colorChoices'];
            $colorsList = array();
            foreach (explode(',',$colorChoices) as $key => $value) {
                list($colorName, $colorValue) = explode('|', $value);
                //$colorsList[$colorName] = $colorValue;
                $colorsList[$key] = array(
                    'name' => $colorName,
                    'value' => $colorValue,
                );
            }

            $viewData = array();
            $viewData['team'] = $team;
            $viewData['colors'] = $colorsList;
            $viewData['users'] = $usersList;
            $viewData['teamMembers'] = $teamMembers;
            $viewData['teamMembersIds'] = $teamMembersIds;
            $viewData['teamleaders'] = $teamleadersList ? implode(", ", $teamleadersList) : "";

            $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
            $viewData['flashMsgError'] = $flash->getFirstMessage('error');

            return $twig->render($response, 'team-edit.html.twig', $viewData);
        }

        // redirect
        $url = $routeParser->urlFor('teams');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $team = ($currentUser->getRole() === 3) ? $this->teamService->findTeam(intval($args['teamId'])) : $this->teamService->findTeamByIdAndTeamleader(intval($args['teamId']), $currentUser->getId());
        if ($team) {
            $errors = $this->teamService->updateTeam($team, $data);

            if (empty($errors)) {
                $flash->addMessage('success', $translations['form_success_update']);
            }
            else {
                $flash->addMessage('error', $errors);
                $url = $routeParser->urlFor('teams_edit', array('teamId' => $args['teamId']));
                return $response->withStatus(302)->withHeader('Location', $url);
            }
        }

        // redirect
        $url = $routeParser->urlFor('teams');
        return $response->withStatus(302)->withHeader('Location', $url);
    }
}
