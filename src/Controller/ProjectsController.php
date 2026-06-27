<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Helper\ControllerHelper;
use App\Service\ActivityService;
use App\Service\CustomerService;
use App\Service\FlashMessageService;
use App\Service\ProjectService;
use App\Service\TeamService;
use App\Service\TimesheetService;
use App\Service\UserService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Views\Twig;

final class ProjectsController
{
    private Twig $twig;
    private FlashMessageService $flash;
    private ActivityService $activityService;
    private CustomerService $customerService;
    private ProjectService $projectService;
    private TeamService $teamService;
    private TimesheetService $timesheetService;
    private UserService $userService;
    private ControllerHelper $helper;
    private array $options;
    private array $translations;

    public function __construct(Twig $twig, FlashMessageService $flash, ActivityService $activityService, CustomerService $customerService, ProjectService $projectService, TeamService $teamService, TimesheetService $timesheetService, UserService $userService, ControllerHelper $helper, array $options, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->activityService = $activityService;
        $this->customerService = $customerService;
        $this->projectService = $projectService;
        $this->teamService = $teamService;
        $this->timesheetService = $timesheetService;
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

        $customers = $this->helper->isAdmin($currentUser)
            ? $this->customerService->findAll(1)
            : $this->customerService->findAllByTeamleaderId($currentUser->getId(), 1);

        $teams = $this->helper->isAdmin($currentUser)
            ? $this->teamService->findAllTeams()
            : $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        $projects = $this->helper->isAdmin($currentUser)
            ? $this->projectService->findAllProjectsWithTeamsCountAndCustomer()
            : $this->projectService->findAllProjectsWithTeamsCountAndCustomerByTeamleaderId($currentUser->getId());

        $projects = $this->addProjectLinks($request, $projects);

        return $this->twig->render($response, 'projects.html.twig', [
            'userRole'        => $currentUser->getRole(),
            'colors'          => $colors,
            'customers'       => $this->helper->mapIdNameList($customers),
            'teams'           => $this->helper->mapIdNameList($teams),
            'projects'        => $projects,
            'flashMsgSuccess' => $this->flash->getFirst('success'),
            'flashMsgError'   => $this->flash->getFirst('error'),
        ]);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->projectService->createProject($data);

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_create_project']);
        }
        else {
            $this->flash->add('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'projects');
    }

    public function projectDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $projectId = (int)($args['projectId'] ?? 0);
        $project = $this->getAccessibleProject($currentUser, $projectId, strict: false);

        if (!$project) {
            return $this->helper->redirect($request, $response, 'projects');
        }

        $selectedCustomer = $this->customerService->findCustomer($project->getCustomerId());

        $selectedTeams = $this->teamService->findAllTeamsByProjectId($project->getId());

        $globalActivities = $this->activityService->findAllGlobalActivities();
        $projectActivities = $this->activityService->findAllProjectActivitiesByProjectId($project->getId());
        $allowedActivities = $this->activityService->findAllByProjectId($project->getId());
        $allowedActivitiesIds = array_map(static fn($a) => $a->getId(), $allowedActivities);

        $teams = $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());
        $teamsIds = array_map(static fn($t) => $t->getId(), $teams);
        $usersIds = [];
        if (!empty($teamsIds)) {
            $users = $this->userService->findAllUsersInTeams($teamsIds);
            $usersIds = array_map(static fn($u) => $u->getId(), $users);
        }

        // Get timesheets
        $criteria = array(
            'users' => $usersIds,
            'projects' => [$project->getId()],
        );
        list($timesheets, $duration) = $this->timesheetService->buildProjectTimesheetsSummary($criteria);

        return $this->twig->render($response, 'project-details.html.twig', [
            'project'           => $project,
            'selectedCustomer'  => $selectedCustomer,
            'selectedTeams'   => $selectedTeams,
            'globalActivities'  => $globalActivities,
            'projectActivities'  => $projectActivities,
            'allowedActivitiesIds'  => $allowedActivitiesIds,
            'timesheets'  => $timesheets,
            'duration'  => $duration > 0 ? $this->timesheetService->timeToString($duration) : "",
        ]);
    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $projectId = (int)($args['projectId'] ?? 0);
        $project = $this->getAccessibleProject($currentUser, $projectId, strict: true);

        if (!$project) {
            return $this->helper->redirect($request, $response, 'projects');
        }

        $customers = $this->helper->isAdmin($currentUser)
            ? $this->customerService->findAll(1)
            : $this->customerService->findAllByTeamleaderId($currentUser->getId(), 1);

        $teams = $this->helper->isAdmin($currentUser)
            ? $this->teamService->findAllTeams()
            : $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());

        $selectedTeams = $this->teamService->findAllTeamsByProjectId($project->getId());
        $selectedTeamsIds = array_map(static fn($t) => $t->getId(), $selectedTeams);

        $globalActivities = $this->activityService->findAllGlobalActivities();
        $projectActivities = $this->activityService->findAllProjectActivitiesByProjectId($project->getId());
        $allowedActivities = $this->activityService->findAllByProjectId($project->getId());
        $allowedActivitiesIds = array_map(static fn($a) => $a->getId(), $allowedActivities);

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        return $this->twig->render($response, 'project-edit.html.twig', [
            'project'              => $project,
            'colors'               => $colors,
            'customers'            => $this->helper->mapIdNameList($customers),
            'teams'                => $this->helper->mapIdNameList($teams),
            'selectedTeams'        => $selectedTeams,
            'selectedTeamsIds'     => $selectedTeamsIds,
            'globalActivities'     => $globalActivities,
            'projectActivities'    => $projectActivities,
            'projectActivitiesIds' => $allowedActivitiesIds,
            'flashMsgSuccess'      => $this->flash->getFirst('success'),
            'flashMsgError'        => $this->flash->getFirst('error'),
        ]);
    }

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $projectId = (int)($args['projectId'] ?? 0);
        $project = $this->getAccessibleProject($currentUser, $projectId, strict: true);

        if (!$project) {
            return $this->helper->redirect($request, $response, 'projects');
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->projectService->updateProject($project, $data);

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_update']);
            return $this->helper->redirect($request, $response, 'projects');
        }

        $this->flash->add('error', $errors);
        return $this->helper->redirect($request, $response, 'projects_edit', ['projectId' => $projectId]);
    }

    // Helpers
    private function addProjectLinks(ServerRequestInterface $request, array $projects): array
    {
        foreach ($projects as &$project) {
            if (!isset($project['id'])) {
                continue;
            }
            $id = (int) $project['id'];

            $project['editLink'] = $this->helper->getUrlFor($request, 'projects_edit', ['projectId' => $id]);
            $project['viewLink'] = $this->helper->getUrlFor($request, 'projects_details', ['projectId' => $id]);
        }
        unset($project);

        return $projects;
    }

    private function getAccessibleProject(User $user, int $projectId, bool $strict): Project|false
    {
        if ($projectId <= 0) {
            return false;
        }

        if ($this->helper->isAdmin($user)) {
            return $this->projectService->findProject($projectId);
        }

        return $strict
            ? $this->projectService->findOneByIdAndTeamleaderIdStrict($projectId, $user->getId())
            : $this->projectService->findOneByIdAndTeamleaderId($projectId, $user->getId());
    }
}
