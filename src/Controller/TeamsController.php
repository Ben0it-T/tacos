<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\ControllerHelper;
use App\Service\CustomerService;
use App\Service\FlashMessageService;
use App\Service\ProjectService;
use App\Service\TeamService;
use App\Service\UserService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Views\Twig;

final class TeamsController
{
    private Twig $twig;
    private FlashMessageService $flash;
    private CustomerService $customerService;
    private ProjectService $projectService;
    private TeamService $teamService;
    private UserService $userService;
    private ControllerHelper $helper;
    private array $options;
    private array $translations;

    public function __construct(Twig $twig, FlashMessageService $flash, CustomerService $customerService, ProjectService $projectService, TeamService $teamService, UserService $userService, ControllerHelper $helper, array $options, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->customerService = $customerService;
        $this->projectService = $projectService;
        $this->teamService = $teamService;
        $this->userService = $userService;
        $this->helper = $helper;
        $this->options = $options;
        $this->translations = $translations;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $teams = $this->helper->isAdmin($currentUser)
            ? $this->teamService->findAllTeamsWithUserCountAndTeamleads()
            : $this->teamService->findAllTeamsWithUserCountAndTeamleadsByTeamleaderId($currentUser->getId());

        $teams = $this->addTeamLinks($request, $teams);

        $users = $this->userService->findAllUsers(1);

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        return $this->twig->render($response, 'teams.html.twig', [
            'canCreateTeam'   => $this->helper->isAdmin($currentUser),
            'colors'          => $colors,
            'teams'           => $teams,
            'users'           => $this->helper->mapIdNameList($users),
            'flashMsgSuccess' => $this->flash->getFirst('success'),
            'flashMsgError'   => $this->flash->getFirst('error'),
        ]);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->teamService->createTeam($data);

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_create_team']);
        }
        else {
            $this->flash->add('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'teams');
    }

    public function teamsDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $teamId = (int)($args['teamId'] ?? 0);
        $team = $this->helper->isAdmin($currentUser)
            ? $this->teamService->findTeam($teamId)
            : $this->teamService->findTeamByIdAndTeamleader($teamId, $currentUser->getId());

        if (!$team) {
            return $this->helper->redirect($request, $response, 'teams');
        }

        $teamMembers = $this->userService->findAllUsersByTeamId($team->getId());
        $teamleaders = $this->userService->findAllTeamleadersByTeamId($team->getId());
        $customers   = $this->customerService->findAllByTeamId($team->getId());
        $projects    = $this->projectService->findAllProjectsWithCustomerByTeamId($team->getId());

        return $this->twig->render($response, 'team-details.html.twig', [
            'team'        => $team,
            'teamMembers' => $teamMembers,
            'teamleaders' => $teamleaders,
            'customers'   => $customers,
            'projects'    => $projects,
        ]);
    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $teamId = (int)($args['teamId'] ?? 0);

        $team = $this->helper->isAdmin($currentUser)
            ? $this->teamService->findTeam($teamId)
            : $this->teamService->findTeamByIdAndTeamleader($teamId, $currentUser->getId());

        if (!$team) {
            return $this->helper->redirect($request, $response, 'teams');
        }

        $users = $this->userService->findAllUsers(1);

        $teamMembers = $this->userService->findAllUsersByTeamId($team->getId());
        $teamMembersIds = array_map(static fn($t) => $t['id'], $teamMembers);

        $teamleaders = $this->userService->findAllTeamleadersByTeamId($team->getId());

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        return $this->twig->render($response, 'team-edit.html.twig', [
            'team'             => $team,
            'colors'           => $colors,
            'users'            => $this->helper->mapIdNameList($users),
            'teamMembers'      => $teamMembers,
            'teamMembersIds'   => $teamMembersIds,
            'teamleaders'      => $teamleaders,
            'flashMsgSuccess'  => $this->flash->getFirst('success'),
            'flashMsgError'    => $this->flash->getFirst('error'),

        ]);
    }

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $teamId = (int)($args['teamId'] ?? 0);

        $team = $this->helper->isAdmin($currentUser)
            ? $this->teamService->findTeam($teamId)
            : $this->teamService->findTeamByIdAndTeamleader($teamId, $currentUser->getId());

        if (!$team) {
            return $this->helper->redirect($request, $response, 'teams');
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->teamService->updateTeam($team, $data);
        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_update']);
            return $this->helper->redirect($request, $response, 'teams');
        }

        $this->flash->add('error', $errors);
        return $this->helper->redirect($request, $response, 'teams_edit', ['teamId' => $teamId]);
    }

    // Helpers
    private function addTeamLinks(ServerRequestInterface $request, array $teams): array
    {
        foreach ($teams as &$team) {
            if (!isset($team['id'])) {
                continue;
            }
            $id = (int) $team['id'];

            $team['editLink'] = $this->helper->getUrlFor($request, 'teams_edit', ['teamId' => $id]);
            $team['viewLink'] = $this->helper->getUrlFor($request, 'teams_details', ['teamId' => $id]);
        }
        unset($team);

        return $teams;
    }
}
