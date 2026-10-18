<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Team;
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
    /** @var array<string, string> */
    private array $options;
    /** @var array<string, string|int> */
    private array $translations;

    /**
     * @param Twig $twig
     * @param FlashMessageService $flash
     * @param ActivityService $activityService
     * @param CustomerService $customerService
     * @param ProjectService $projectService
     * @param TeamService $teamService
     * @param TimesheetService $timesheetService
     * @param UserService $userService
     * @param ControllerHelper $helper
     * @param array<string, string> $options
     * @param array<string, string|int> $translations
     */
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

    /**
     * List projects
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $customers = $this->getAllowedCustomers($currentUser);

        $teams = $this->getAllowedTeams($currentUser);

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

    /**
     * Create a project
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $allowedCustomerIds = $this->helper->extractIds($this->getAllowedCustomers($currentUser));
        $allowedTeamIds = $this->helper->extractIds($this->getAllowedTeams($currentUser));

        $data = (array) $request->getParsedBody();
        $errors = $this->projectService->createProject($data, $allowedCustomerIds, $allowedTeamIds);

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_create_project']);
        }
        else {
            $this->flash->add('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'projects');
    }

    /**
     * Show project details
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
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

    /**
     * Show the project edit form
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
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

        $customers = $this->getAllowedCustomers($currentUser);

        // Keep current customer selectable even if hidden or out of scope
        $currentCustomer = null;
        if (!isset($customers[$project->getCustomerId()])) {
            $currentCustomer = $this->customerService->findCustomer($project->getCustomerId());
        }

        $teams = $this->getAllowedTeams($currentUser);

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
            'currentCustomer'      => $currentCustomer,
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

    /**
     * Update a project
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
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

        $allowedCustomerIds = $this->helper->extractIds($this->getAllowedCustomers($currentUser));
        $allowedTeamIds = $this->helper->extractIds($this->getAllowedTeams($currentUser));

        $errors = $this->projectService->updateProject($project, $data, $allowedCustomerIds, $allowedTeamIds);

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_update']);
            return $this->helper->redirect($request, $response, 'projects');
        }

        $this->flash->add('error', $errors);
        return $this->helper->redirect($request, $response, 'projects_edit', ['projectId' => $projectId]);
    }

    // Helpers
    /**
     * Add edit and view links to each project row
     *
     * @param ServerRequestInterface $request
     * @param array<int, array<string, mixed>> $projects Project rows
     * @return array<int, array<string, mixed>> Project rows with 'editLink' and 'viewLink'
     */
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

    /**
     * Get customers the user is allowed to use
     * - admin: all visible customers
     * - teamlead: visible customers within their scope
     *
     * @param User $user
     * @return array<int, Customer> Customer entities indexed by id
     */
    private function getAllowedCustomers(User $user): array
    {
        return $this->helper->isAdmin($user)
            ? $this->customerService->findAll(1)
            : $this->customerService->findAllByTeamleaderId($user->getId(), 1);
    }

    /**
     * Get teams the user is allowed to use
     * - admin: all teams
     * - teamlead: teams they lead
     *
     * @param User $user
     * @return array<int, Team> Team entities indexed by id
     */
    private function getAllowedTeams(User $user): array
    {
        return $this->helper->isAdmin($user)
            ? $this->teamService->findAllTeams()
            : $this->teamService->findAllTeamsByTeamleaderId($user->getId());
    }

}
