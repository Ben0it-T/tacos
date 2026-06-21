<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\ControllerHelper;
use App\Helper\RoundingHelper;
use App\Service\ActivityService;
use App\Service\CustomerService;
use App\Service\FlashMessageService;
use App\Service\ProjectService;
use App\Service\TagService;
use App\Service\TeamService;
use App\Service\TimesheetService;
use App\Service\UserService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Views\Twig;

final class TimesheetsController
{
    private Twig $twig;
    private FlashMessageService $flash;
    private ActivityService $activityService;
    private CustomerService $customerService;
    private ProjectService $projectService;
    private TagService $tagService;
    private TeamService $teamService;
    private TimesheetService $timesheetService;
    private UserService $userService;
    private RoundingHelper $roundingHelper;
    private ControllerHelper $helper;
    private array $options;
    private array $translations;

    public function __construct(Twig $twig, FlashMessageService $flash, ActivityService $activityService, CustomerService $customerService, ProjectService $projectService, TagService $tagService, TeamService $teamService, TimesheetService $timesheetService, UserService $userService, RoundingHelper $roundingHelper, ControllerHelper $helper, array $options, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->activityService = $activityService;
        $this->customerService = $customerService;
        $this->projectService = $projectService;
        $this->tagService = $tagService;
        $this->teamService = $teamService;
        $this->timesheetService = $timesheetService;
        $this->userService = $userService;
        $this->roundingHelper = $roundingHelper;
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

        // Query Params
        $queryParams = $this->getTimesheetFilters($request, 'timesheets');
        $queryParams['users'] = [$currentUser->getId()];

        // Projects
        $projects = $this->projectService->findAllByUserId($currentUser->getId(), 1);
        $projectsIds = array_map(static fn($p) => $p->getId(), $projects);
        $queryParams['projects'] = $this->cleanQueryParamIds($queryParams['projects'], $projectsIds);

        // Activities
        $activities = $this->activityService->findAllByUserId($currentUser->getId(), 1);
        $activitiesIds = array_map(static fn($a) => $a->getId(), $activities);
        $queryParams['activities'] = $this->cleanQueryParamIds($queryParams['activities'], $activitiesIds);

        // Tags
        $tags = $this->tagService->findAllVisible();
        $tagsIds = array_map(static fn($t) => $t->getId(), $tags);
        $queryParams['tags'] = $this->cleanQueryParamIds($queryParams['tags'], $tagsIds);

        // Store params
        $this->helper->setSessionValue('timesheets', [
            'start'      => $queryParams['start'],
            'end'        => $queryParams['end'],
            'projects'   => $queryParams['projects'],
            'activities' => $queryParams['activities'],
            'tags'       => $queryParams['tags'],
        ]);

        // Get timesheets
        $timesheets = $this->timesheetService->findTimesheetsByCriteria($queryParams);
        $allTags = $this->tagService->findAll();
        $duration = 0;
        $restartCfg = $this->options['restart'];
        foreach ($timesheets as &$ts) {
            // Restart
            $canRestart = $this->canRestartTimesheet($ts, $restartCfg);

            // Duration
            $duration += (int)$ts['duration'];
            $ts['duration'] = $this->timesheetService->timeToString((int)$ts['duration']);

            // Tags
            $ts['tags'] = $this->mapTimesheetTags($ts, $allTags);

            // Links
            $ts = $this->addTimesheetLinks($request, $ts, $canRestart);
        }
        unset($ts);

        return $this->twig->render($response, 'timesheets.html.twig', [
            'daterange' => [
                'start' => $queryParams['start'],
                'end'   => $queryParams['end'],
            ],
            'selectedProjects'   => $queryParams['projects'],
            'selectedActivities' => $queryParams['activities'],
            'selectedTags'       => $queryParams['tags'],
            'projects'           => $this->helper->mapIdNameList($projects),
            'activities'         => $this->helper->mapIdNameList($activities),
            'tags'               => $tags,
            'timesheets'         => $timesheets,
            'duration'           => $duration > 0 ? $this->timesheetService->timeToString($duration) : "",
            'flashMsgSuccess'    => $this->flash->getFirst('success'),
            'flashMsgError'      => $this->flash->getFirst('error'),
        ]);
    }

    public function indexTeams(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        // Query Params
        $queryParams = $this->getTimesheetFilters($request, 'teamsTimesheets');

        // Teams
        $teams = $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());
        $teamsIds = array_map(static fn($t) => $t->getId(), $teams);

        // Users
        $users = $this->userService->findAllUsersInTeams($teamsIds, 1);
        $usersIds = array_map(static fn($u) => $u->getId(), $users);
        $queryParams['users'] = $this->cleanQueryParamIds($queryParams['users'], $usersIds);

        // Projects
        $projects = $this->projectService->findAllByTeamleaderId($currentUser->getId(), 1);
        $projectsIds = array_map(static fn($p) => $p->getId(), $projects);
        $queryParams['projects'] = $this->cleanQueryParamIds($queryParams['projects'], $projectsIds);

        // Activities
        $activities = $this->activityService->findAllByTeamleaderId($currentUser->getId(), 1);
        $activitiesIds = array_map(static fn($a) => $a->getId(), $activities);
        $queryParams['activities'] = $this->cleanQueryParamIds($queryParams['activities'], $activitiesIds);

        // Tags
        $tags = $this->tagService->findAllVisible();
        $tagsIds = array_map(static fn($t) => $t->getId(), $tags);
        $queryParams['tags'] = $this->cleanQueryParamIds($queryParams['tags'], $tagsIds);

        // Store filters
        $this->helper->setSessionValue('teamsTimesheets', [
            'start'      => $queryParams['start'],
            'end'        => $queryParams['end'],
            'users'      => $queryParams['users'],
            'projects'   => $queryParams['projects'],
            'activities' => $queryParams['activities'],
            'tags'       => $queryParams['tags'],
        ]);

        $criteria = $queryParams;
        $criteria['users'] = empty($queryParams['users']) ? $usersIds : $queryParams['users'];

        // Get timesheets
        $timesheets = $this->timesheetService->findTimesheetsByCriteria($criteria);
        $allTags = $this->tagService->findAll();
        $duration = 0;
        foreach ($timesheets as &$ts) {
            // Duration
            $duration += (int)$ts['duration'];
            $ts['duration'] = $this->timesheetService->timeToString((int)$ts['duration']);

            // Tags
            $ts['tags'] = $this->mapTimesheetTags($ts, $allTags);
        }
        unset($ts);

        return $this->twig->render($response, 'teams-timesheets.html.twig', [
            'daterange' => [
                'start' => $queryParams['start'],
                'end'   => $queryParams['end'],
            ],
            'selectedUsers'      => $queryParams['users'],
            'selectedProjects'   => $queryParams['projects'],
            'selectedActivities' => $queryParams['activities'],
            'selectedTags'       => $queryParams['tags'],
            'users'              => $this->helper->mapIdNameList($users),
            'projects'           => $this->helper->mapIdNameList($projects),
            'activities'         => $this->helper->mapIdNameList($activities),
            'tags'               => $tags,
            'timesheets'         => $timesheets,
            'duration'           => $duration > 0 ? $this->timesheetService->timeToString($duration) : "",
            'flashMsgSuccess'    => $this->flash->getFirst('success'),
            'flashMsgError'      => $this->flash->getFirst('error'),
        ]);
    }

    public function createForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $customers = $this->customerService->findAllByUserId($currentUser->getId(), 1);
        $projects = $this->projectService->findAllByUserId($currentUser->getId(), 1);
        $activities = $this->activityService->findAllByUserId($currentUser->getId(), 1);
        $tags = $this->tagService->findAllVisible();

        $rounding = $this->options['rounding'];
        $start = new \DateTime("now");
        if ($rounding['active']) $start = $this->roundingHelper->roundDateTime($start, $rounding['start']['minutes'], $rounding['start']['mode']);

        return $this->twig->render($response, 'timesheet-create.html.twig', [
            'customers'       => $this->helper->mapIdNameList($customers),
            'projects'        => $this->helper->mapIdNameList($projects),
            'activities'      => $this->helper->mapIdNameList($activities),
            'tags'            => $tags,
            'startDate'       => date_format($start,"Y-m-d H:i"),
            'endDate'         => date("Y-m-d H:i", mktime(23, 59, 59, intval(date("n")), intval(date("j")), intval(date("Y")))),
            'flashMsgSuccess' => $this->flash->getFirst('success'),
            'flashMsgError'   => $this->flash->getFirst('error'),
        ]);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $data = (array) $request->getParsedBody();
        $data['userId'] = $currentUser->getId();
        $errors = $this->timesheetService->createTimesheet($data);

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_create_activity']);
        }
        else {
            $this->flash->add('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'timesheets');
    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $timesheetId = (int)($args['timesheetId'] ?? 0);
        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId($timesheetId, $currentUser->getId());

        if (!$timesheet) {
            return $this->helper->redirect($request, $response, 'timesheets');
        }

        $customers = $this->customerService->findAllByUserId($currentUser->getId(), 1);
        $projects = $this->projectService->findAllByUserId($currentUser->getId(), 1);
        $activities = $this->activityService->findAllByUserId($currentUser->getId(), 1);
        $tags = $this->tagService->findAllVisible();

        $selectedTags = $this->tagService->findAllByTimesheetId($timesheet->getId());
        $selectedTagsIds = array_map(static fn($t) => $t->getId(), $selectedTags);

        $projectActivities = $this->activityService->findAllByProjectId($timesheet->getProjectId());
        $projectActivitiesIds = array_map(static fn($pa) => $pa->getId(), $projectActivities);

        return $this->twig->render($response, 'timesheet-edit.html.twig', [
            'timesheet'            => $timesheet,
            'selectedTags'         => $selectedTags,
            'projectActivitiesIds' => $projectActivitiesIds,
            'selectedTagsIds'      => $selectedTagsIds,
            'durationTmp'          => $this->timesheetService->timeToString($timesheet->getDuration()),
            'customers'            => $this->helper->mapIdNameList($customers),
            'projects'             => $this->helper->mapIdNameList($projects),
            'activities'           => $this->helper->mapIdNameList($activities),
            'tags'                 => $tags,
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

        $data = (array) $request->getParsedBody();

        $timesheetId = (int)($args['timesheetId'] ?? 0);
        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId($timesheetId, $currentUser->getId());

        if (!$timesheet) {
            return $this->helper->redirect($request, $response, 'timesheets');
        }

        $errors = $this->timesheetService->updateTimesheet($timesheet, $data);
        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_update']);
            return $this->helper->redirect($request, $response, 'timesheets_edit', ['timesheetId' => $timesheetId]);
        }

        $this->flash->add('error', $errors);
        return $this->helper->redirect($request, $response, 'timesheets_edit', ['timesheetId' => $timesheetId]);
    }

    public function restartAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $timesheetId = (int)($args['timesheetId'] ?? 0);
        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId($timesheetId, $currentUser->getId());

        if (!$timesheet) {
            return $this->helper->redirect($request, $response, 'timesheets');
        }

        $errors = $this->timesheetService->restartTimesheet($timesheet);

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_create_activity']);
        }
        else {
            $this->flash->add('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'timesheets');
    }

    public function deleteForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $timesheetId = (int)($args['timesheetId'] ?? 0);
        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId($timesheetId, $currentUser->getId());

        if (!$timesheet) {
            return $this->helper->redirect($request, $response, 'timesheets');
        }

        return $this->twig->render($response, 'timesheet-delete.html.twig', [
            'timesheet' => [
                'id'          => $timesheet->getId(),
                'start'       => $timesheet->getStart(),
                'end'         => $timesheet->getEnd(),
                'duration'    => $this->timesheetService->timeToString($timesheet->getDuration()),
                'project'     => $this->projectService->findProject($timesheet->getProjectId()),
                'activity'    => $this->activityService->findActivity($timesheet->getActivityId()),
                'description' => $timesheet->getComment(),
                'tags'        => $this->tagService->findAllByTimesheetId($timesheet->getId()),
            ],
        ]);
    }

    public function deleteAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $timesheetId = (int)($args['timesheetId'] ?? 0);
        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId($timesheetId, $currentUser->getId());

        if (!$timesheet) {
            return $this->helper->redirect($request, $response, 'timesheets');
        }

        $errors = $this->timesheetService->deleteTimesheet($timesheet);
        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_delete_record']);
        }
        else {
            $this->flash->add('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'timesheets');
    }

    public function stopAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $timesheetId = (int)($args['timesheetId'] ?? 0);
        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId($timesheetId, $currentUser->getId());

        if (!$timesheet) {
            return $this->helper->redirect($request, $response, 'timesheets');
        }

        $timesheet->setEnd(date("Y-m-d H:i"));
        $errors = $this->timesheetService->stopTimesheet($timesheet);
        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_update']);
        }
        else {
            $this->flash->add('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'timesheets');
    }

    public function exportTimesheets(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $criteria = $this->buildTimesheetCriteriaFromSession($request, $currentUser);

        if ($criteria === null) {
            return $this->helper->redirect($request, $response, 'timesheets');
        }

        $delimiter        = ";";
        $enclosure        = '"';
        $escapeChar       = "\\";
        $recordSeparator  = "\r\n";

        $allTags = $this->tagService->findAll();
        $timesheets = $this->timesheetService->findTimesheetsByCriteria($criteria);
        foreach ($timesheets as &$ts) {
            $ts['tags'] = $this->mapTimesheetTags($ts, $allTags);
        }
        unset($ts);

        // BOM
        $response->getBody()->write(chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header
        $response->getBody()->write(implode($delimiter, $this->buildCsvHeader()) . $recordSeparator);

        // Lines
        foreach ($timesheets as $entry) {
            $line = $this->buildCsvLine($entry, $delimiter, $enclosure, $escapeChar);
            $response->getBody()->write($line . $recordSeparator);
        }

        // Output
        $fileName = "tacos-".date("Y-m-d").".csv";
        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', "attachment; filename=$fileName")
            ->withAddedHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');
    }

    public function exportTeamsTimesheets(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $criteria = $this->buildTeamsTimesheetCriteriaFromSession($request);

        if ($criteria === null) {
            return $this->helper->redirect($request, $response, 'timesheets_teams');
        }

        $teams = $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());
        $teamsIds = array_map(static fn($t) => $t->getId(), $teams);

        $users = $this->userService->findAllUsersInTeams($teamsIds, 1);
        $usersIds = array_map(static fn($u) => $u->getId(), $users);

        $criteria['users'] = empty($criteria['users']) ? $usersIds : $criteria['users'];

        $delimiter        = ";";
        $enclosure        = '"';
        $escapeChar       = "\\";
        $recordSeparator  = "\r\n";

        $allTags = $this->tagService->findAll();
        $timesheets = $this->timesheetService->findTimesheetsByCriteria($criteria);

        foreach ($timesheets as &$ts) {
            $ts['tags'] = $this->mapTimesheetTags($ts, $allTags);
        }
        unset($ts);

        // BOM
        $response->getBody()->write(chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header
        $response->getBody()->write(implode($delimiter, $this->buildCsvHeader()) . $recordSeparator);

        // Lines
        foreach ($timesheets as $entry) {
            $line = $this->buildCsvLine($entry, $delimiter, $enclosure, $escapeChar);
            $response->getBody()->write($line . $recordSeparator);
        }

        // Output
        $fileName = "tacos-".date("Y-m-d").".csv";
        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', "attachment; filename=$fileName")
            ->withAddedHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');
    }

    // Helpers
    private function addTimesheetLinks(ServerRequestInterface $request, array $timesheet, bool $canRestart): array
    {
        $id = (int) $timesheet['id'];
        $timesheet['deleteLink']  = $this->helper->getUrlFor($request, 'timesheets_delete',  ['timesheetId' => $id]);
        $timesheet['editLink']    = $this->helper->getUrlFor($request, 'timesheets_edit',    ['timesheetId' => $id]);
        $timesheet['stopLink']    = $this->helper->getUrlFor($request, 'timesheets_stop',    ['timesheetId' => $id]);
        $timesheet['restartLink'] = $canRestart
            ? $this->helper->getUrlFor($request, 'timesheets_restart', ['timesheetId' => $id])
            : '';

        return $timesheet;
    }

    private function buildCsvHeader(): array
    {
        return ['Start', 'End', 'Duration', 'Project', 'Project Number', 'Activity', 'Activity Number', 'User', 'Description', 'Tags'];
    }

    /**
     * Build one CSV line from a timesheet entry.
     *
     * Escapes string values and concatenates tags using '|'.
     *
     * @param array $entry      Timesheet data with joined fields
     * @param string $delimiter CSV delimiter
     * @param string $enclosure CSV enclosure character
     * @param string $escape    Escape character
     * @return string
     */
    private function buildCsvLine(array $entry, string $delimiter, string $enclosure, string $escape): string
    {
        $escapeValue = fn($value) => $enclosure . str_replace($enclosure, $escape . $enclosure, (string)$value) . $enclosure;

        $tags = [];
        foreach ($entry['tags'] as $tag) {
            $tags[] = $tag['name'];
        }

        return implode($delimiter, [
            $escapeValue($entry['start']),
            $escapeValue($entry['end'] ?? ''),
            $entry['duration'],
            $escapeValue($entry['projectName']),
            $escapeValue($entry['projectNumber']),
            $escapeValue($entry['activityName']),
            $escapeValue($entry['activityNumber']),
            $escapeValue($entry['userName']),
            $escapeValue($entry['comment']),
            $escapeValue(implode('|', $tags)),
        ]);
    }

    /**
     * Build timesheet criteria from session filters.
     *
     * Returns null if session data is invalid.
     */
    private function buildTimesheetCriteriaFromSession(ServerRequestInterface $request, $currentUser): ?array
    {
        $session = $this->helper->getSessionValue('timesheets', []);

        $schema = [
            'start'      => ['type' => 'string', 'required' => true],
            'end'        => ['type' => 'string', 'required' => true],
            'projects'   => ['type' => 'array',  'required' => false],
            'activities' => ['type' => 'array',  'required' => false],
            'tags'       => ['type' => 'array',  'required' => false],
        ];

        if (!$this->validateTimesheetSession($session, $schema)) {
            return null;
        }

        return [
            'start'      => $session['start'],
            'end'        => $session['end'],
            'users'      => [$currentUser->getId()],
            'projects'   => $session['projects'] ?? [],
            'activities' => $session['activities'] ?? [],
            'tags'       => $session['tags'] ?? [],
        ];
    }

    private function buildTeamsTimesheetCriteriaFromSession(ServerRequestInterface $request): ?array
    {
        $session = $this->helper->getSessionValue('teamsTimesheets', []);

        $schema = [
            'start'      => ['type' => 'string', 'required' => true],
            'end'        => ['type' => 'string', 'required' => true],
            'users'      => ['type' => 'array',  'required' => true],
            'projects'   => ['type' => 'array',  'required' => false],
            'activities' => ['type' => 'array',  'required' => false],
            'tags'       => ['type' => 'array',  'required' => false],
        ];

        if (!$this->validateTimesheetSession($session, $schema)) {
            return null;
        }

        return [
            'start'      => $session['start'],
            'end'        => $session['end'],
            'users'      => $session['users'],
            'projects'   => $session['projects'] ?? [],
            'activities' => $session['activities'] ?? [],
            'tags'       => $session['tags'] ?? [],
        ];
    }

    private function canRestartTimesheet(array $timesheet, array $restartCfg): bool
    {
        $startStr = $timesheet['start'] ?? '';
        if ($startStr === '') {
            return false;
        }

        $now = new \DateTimeImmutable();
        $start = new \DateTimeImmutable($startStr);

        $days = $start->diff($now)->days;

        return $restartCfg['active']
            && $days !== false
            && $days <= (int)($restartCfg['interval'] ?? 0);
    }

    /**
     * Filter selected IDs against allowed IDs
     *
     * @param array<int, mixed> $selected
     * @param array<int, int> $allowedIds
     * @return array<int, int>
     */
    private function cleanQueryParamIds(array $selected, array $allowedIds): array
    {
        $allowed = array_flip(array_map('intval', $allowedIds));

        $clean = [];
        foreach ($selected as $id) {
            $id = (int) $id;
            if (isset($allowed[$id])) {
                $clean[] = $id;
            }
        }

        return $clean;
    }

    private function getTimesheetFilters(ServerRequestInterface $request, string $key): array
    {
        $filters = $this->helper->getSessionValue($key, []);
        $filters = is_array($filters) ? $filters : [];
        $queryParams = $this->timesheetService->getQueryParams($request->getQueryParams(), $filters);
        return is_array($queryParams) ? $queryParams : [];
    }

    /**
     * Map tagIds from a timesheet into tag name/color structures
     *
     * @param array<string, mixed> $timesheet
     * @param array<int, object> $tags
     * @return array<int, array{name:string, color:string}>
     */
    private function mapTimesheetTags(array $timesheet, array $tags): array
    {
        $res = [];

        if (empty($timesheet['tagIds'])) {
            return $res;
        }

        foreach (explode(',', $timesheet['tagIds']) as $tagId) {
            $tagId = (int) $tagId;
            $tag = $tags[$tagId] ?? null;
            if ($tag === null) {
                continue;
            }

            $res[] = [
                'name'  => $tag->getName(),
                'color' => $tag->getColor(),
            ];
        }

        return $res;
    }

    private function validateTimesheetSession(array $session, array $schema): bool
    {
        if (empty($session)) {
            return false;
        }

        foreach ($schema as $key => $rule) {
            $required = (bool)($rule['required'] ?? false);
            $type     = (string)($rule['type'] ?? '');

            if (!array_key_exists($key, $session)) {
                if ($required) {
                    return false;
                }
                continue;
            }

            $value = $session[$key];

            switch ($type) {
                case 'array':
                    if (!is_array($value)) {
                        return false;
                    }
                    break;

                case 'string':
                    if (!is_string($value) || trim($value) === '') {
                        return false;
                    }
                    break;

                default:
                    return false;
            }
        }

        return true;
    }
}
