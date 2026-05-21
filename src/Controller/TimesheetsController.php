<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\ControllerHelper;
use App\Helper\RoundingHelper;
use App\Service\ActivityService;
use App\Service\CustomerService;
use App\Service\ProjectService;
use App\Service\TagService;
use App\Service\TeamService;
use App\Service\TimesheetService;
use App\Service\UserService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Flash\Messages;
use Slim\Views\Twig;

final class TimesheetsController
{
    private Twig $twig;
    private Messages $flash;
    private ActivityService $activityService;
    private CustomerService $customerService;
    private ProjectService $projectService;
    private TagService $tagService;
    private TeamService $teamService;
    private TimesheetService $timesheetService;
    private UserService $userService;
    private RoundingHelper $roundingHelper;
    private ControllerHelper $controllerHelper;
    private array $options;
    private array $translations;

    public function __construct(Twig $twig, Messages $flash, ActivityService $activityService, CustomerService $customerService, ProjectService $projectService, TagService $tagService, TeamService $teamService, TimesheetService $timesheetService, UserService $userService, RoundingHelper $roundingHelper, ControllerHelper $controllerHelper, array $options, array $translations)
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
        $this->controllerHelper = $controllerHelper;
        $this->options = $options;
        $this->translations = $translations;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
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
        $this->controllerHelper->setSessionValue('timesheets', [
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
            'projects'           => $this->controllerHelper->mapIdNameList($projects),
            'activities'         => $this->controllerHelper->mapIdNameList($activities),
            'tags'               => $tags,
            'timesheets'         => $timesheets,
            'duration'           => $duration > 0 ? $this->timesheetService->timeToString($duration) : "",
            'flashMsgSuccess'    => $this->flash->getFirstMessage('success'),
            'flashMsgError'      => $this->flash->getFirstMessage('error'),
        ]);
    }

    public function indexTeams(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
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
        $this->controllerHelper->setSessionValue('teamsTimesheets', [
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
            'users'              => $this->controllerHelper->mapIdNameList($users),
            'projects'           => $this->controllerHelper->mapIdNameList($projects),
            'activities'         => $this->controllerHelper->mapIdNameList($activities),
            'tags'               => $tags,
            'timesheets'         => $timesheets,
            'duration'           => $duration > 0 ? $this->timesheetService->timeToString($duration) : "",
            'flashMsgSuccess'    => $this->flash->getFirstMessage('success'),
            'flashMsgError'      => $this->flash->getFirstMessage('error'),
        ]);
    }

    public function createForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
        }

        // Get customers
        $customers = $this->customerService->findAllByUserId($currentUser->getId(), 1);
        $customersList = array();
        foreach ($customers as $entry) {
            $customersList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }

        // Get projects
        $projects = $this->projectService->findAllByUserId($currentUser->getId(), 1);
        $projectsList = array();
        foreach ($projects as $entry) {
            $projectsList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }

        // Get activities
        $activities = $this->activityService->findAllByUserId($currentUser->getId(), 1);
        $activitiesList = array();
        foreach ($activities as $entry) {
            $activitiesList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }

        // Get tags
        $tags = $this->tagService->findAllVisible();

        // Start date
        $rounding = $this->options['rounding'];
        $start = new \DateTime("now");
        if ($rounding['active']) $start = $this->roundingHelper->roundDateTime($start, $rounding['start']['minutes'], $rounding['start']['mode']);

        $viewData = array();
        $viewData['customers'] = $customersList;
        $viewData['projects'] = $projectsList;
        $viewData['activities'] = $activitiesList;
        $viewData['tags'] = $tags;
        $viewData['startDate'] = date_format($start,"Y-m-d H:i");
        $viewData['endDate'] = date("Y-m-d H:i", mktime(23, 59, 59, intval(date("n")), intval(date("j")), intval(date("Y"))));

        $viewData['flashMsgSuccess'] = $this->flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $this->flash->getFirstMessage('error');

        return $this->twig->render($response, 'timesheet-create.html.twig', $viewData);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
        }

        $data = $request->getParsedBody();

        $data['userId'] = $currentUser->getId();
        $errors = $this->timesheetService->createTimesheet($data);

        if (empty($errors)) {
            $this->flash->addMessage('success', $this->translations['form_success_create_activity']);
        }
        else {
            $this->flash->addMessage('error', $errors);
        }

        // redirect
        $url = $this->controllerHelper->getUrlFor($request, 'timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
        }

        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId(intval($args['timesheetId']), $currentUser->getId());

        if ($timesheet) {

            // Get customers
            $customers = $this->customerService->findAllByUserId($currentUser->getId(), 1);
            $customersList = array();
            foreach ($customers as $entry) {
                $customersList[] = array(
                    'id' => $entry->getId(),
                    'name' => $entry->getName(),
                );
            }

            // Get projects
            $projects = $this->projectService->findAllByUserId($currentUser->getId(), 1);
            $projectsList = array();
            foreach ($projects as $entry) {
                $projectsList[] = array(
                    'id' => $entry->getId(),
                    'name' => $entry->getName(),
                );
            }

            // Get activities
            $activities = $this->activityService->findAllByUserId($currentUser->getId(), 1);
            $activitiesList = array();
            foreach ($activities as $entry) {
                $activitiesList[] = array(
                    'id' => $entry->getId(),
                    'name' => $entry->getName(),
                );
            }

            // Get tags
            $tags = $this->tagService->findAllVisible();

            // Get selected tags
            $selectedTags = $this->tagService->findAllByTimesheetId($timesheet->getId());
            $selectedTagsIds = array();
            foreach ($selectedTags as $selectedTag) {
                $selectedTagsIds[] = $selectedTag->getId();
            }

            // Get current project activities
            $projectActivities = $this->activityService->findAllByProjectId($timesheet->getProjectId());
            $projectActivitiesIds = array();
            foreach ($projectActivities as $projectActivity) {
                $projectActivitiesIds[] = $projectActivity->getId();
            }

            $viewData = array();
            $viewData['timesheet'] = $timesheet;
            $viewData['selectedTags'] = $selectedTags;
            $viewData['projectActivitiesIds'] = $projectActivitiesIds;
            $viewData['selectedTagsIds'] = $selectedTagsIds;
            $viewData['durationTmp'] = $this->timesheetService->timeToString($timesheet->getDuration());

            $viewData['customers'] = $customersList;
            $viewData['projects'] = $projectsList;
            $viewData['activities'] = $activitiesList;
            $viewData['tags'] = $tags;

            $viewData['flashMsgSuccess'] = $this->flash->getFirstMessage('success');
            $viewData['flashMsgError'] = $this->flash->getFirstMessage('error');

            return $this->twig->render($response, 'timesheet-edit.html.twig', $viewData);
        }


        // redirect
        $url = $this->controllerHelper->getUrlFor($request, 'timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
        }

        $data = $request->getParsedBody();

        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId(intval($args['timesheetId']), $currentUser->getId());

        if ($timesheet) {
            $errors = $this->timesheetService->updateTimesheet($timesheet, $data);
            if (empty($errors)) {
                $this->flash->addMessage('success', $this->translations['form_success_update']);
            }
            else {
                $this->flash->addMessage('error', $errors);
            }

            // redirect
            $url = $this->controllerHelper->getUrlFor($request, 'timesheets_edit', array('timesheetId' => $timesheet->getId()));
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        // redirect
        $url = $this->controllerHelper->getUrlFor($request, 'timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function restartAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
        }

        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId(intval($args['timesheetId']), $currentUser->getId());
        if ($timesheet) {
            $this->timesheetService->restartTimesheet($timesheet);
            $this->flash->addMessage('success', $this->translations['form_success_create_activity']);
        }

        // redirect
        $url = $this->controllerHelper->getUrlFor($request, 'timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function deleteForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
        }

        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId(intval($args['timesheetId']), $currentUser->getId());

        if ($timesheet) {
            $viewData = array();
            $viewData['timesheet'] = array(
                'id' => $timesheet->getId(),
                'start' => $timesheet->getStart(),
                'end' => $timesheet->getEnd(),
                'duration' => $this->timesheetService->timeToString($timesheet->getDuration()),
                'project' => $this->projectService->findProject($timesheet->getProjectId()),
                'activity' => $this->activityService->findActivity($timesheet->getActivityId()),
                'description' => $timesheet->getComment(),
                'tags' => $this->tagService->findAllByTimesheetId($timesheet->getId()),
            );

            return $this->twig->render($response, 'timesheet-delete.html.twig', $viewData);
        }

        // redirect
        $url = $this->controllerHelper->getUrlFor($request, 'timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function deleteAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
        }

        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId(intval($args['timesheetId']), $currentUser->getId());

        if ($timesheet) {
            $errors = $this->timesheetService->deleteTimesheet($timesheet);
            if (empty($errors)) {
                $this->flash->addMessage('success', $this->translations['form_success_delete_record']);
            }
            else {
                $this->flash->addMessage('error', $errors);
            }

        }

        // redirect
        $url = $this->controllerHelper->getUrlFor($request, 'timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function stopAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
        }

        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId(intval($args['timesheetId']), $currentUser->getId());

        if ($timesheet) {
            $timesheet->setEnd(date("Y-m-d H:i"));
            $errors = $this->timesheetService->stopTimesheet($timesheet);
            if (empty($errors)) {
                $this->flash->addMessage('success', $this->translations['form_success_update']);
            }
            else {
                $this->flash->addMessage('error', $errors);
            }
        }

        // redirect
        $url = $this->controllerHelper->getUrlFor($request, 'timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function exportTimesheets(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
        }

        $criteria = $this->buildTimesheetCriteriaFromSession($request, $currentUser);

        if ($criteria === null) {
            return $this->controllerHelper->redirect($request, $response, 'timesheets');
        }

        // Set
        $delimiter = ";";
        $enclosure = '"';
        $escape_char = "\\";
        $record_seperator = "\r\n";

        // Get timesheets
        $tags = $this->tagService->findAll();
        $timesheets = $this->timesheetService->findTimesheetsByCriteria($criteria);
        for ($i=0; $i < count($timesheets); $i++) {
            // Tags
            $timesheets[$i]['tags'] = array();
            if (!is_null($timesheets[$i]['tagIds'])) {
                $tagsIds = explode(',', $timesheets[$i]['tagIds']);
                foreach ($tagsIds as $tagId) {
                    $timesheets[$i]['tags'][] = array(
                        'name' => $tags[$tagId]->getName()
                    );
                }
            }
        }

        $headers = ['Start', 'End', 'Duration', 'Project', 'Project Number', 'Activity', 'Activity Number', 'Description', 'Tags'];

        // Add BOM
        $response->getBody()->write($bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));

        // Create header
        $response->getBody()->write(implode($delimiter, $headers) . $record_seperator);

        foreach ($timesheets as $entry) {
            $line = array();
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['start']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, (is_null($entry['end']) ? "" : $entry['end'])) . $enclosure;
            $line[] = $entry['duration'];
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['projectName']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['projectNumber']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['activityName']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['activityNumber']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['comment']) . $enclosure;

            $tags = array();
            if ($entry['tags']) {
                foreach ($entry['tags'] as $tag) {
                    $tags[] = $tag['name'];
                }
            }
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, implode("|", $tags)) . $enclosure;

            $response->getBody()->write(implode($delimiter, $line) . $record_seperator);
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
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
        }

        $criteria = $this->buildTeamsTimesheetCriteriaFromSession($request);

        if ($criteria === null) {
            return $this->controllerHelper->redirect($request, $response, 'timesheets_teams');
        }

        // Get Teams
        $teams = $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());
        $teamsIds = array();
        if (count($teams) > 0) {
            foreach ($teams as $team) {
                $teamsIds[] = $team->getId();
            }
        }

        // Get users in teams
        $users = $this->userService->findAllUsersInTeams($teamsIds, 1);
        $usersIds = array();
        $usersList = array();
        if (count($users) > 0) {
            foreach ($users as $usr) {
                $usersIds[] = $usr->getId();
                $usersList[] = array(
                    'id' => $usr->getId(),
                    'name' => $usr->getName(),
                );
            }
        }
        $criteria['users'] = empty($criteria['users']) ? $usersIds : $criteria['users'];

        // Set
        $delimiter = ";";
        $enclosure = '"';
        $escape_char = "\\";
        $record_seperator = "\r\n";

        // Get timesheets
        $allTags = $this->tagService->findAll();
        $timesheets = $this->timesheetService->findTimesheetsByCriteria($criteria);
        for ($i=0; $i < count($timesheets); $i++) {
            // Tags
            $timesheets[$i]['tags'] = array();
            if (!is_null($timesheets[$i]['tagIds'])) {
                $tagsIds = explode(',', $timesheets[$i]['tagIds']);
                foreach ($tagsIds as $tagId) {
                    $timesheets[$i]['tags'][] = array(
                        'name' => $allTags[$tagId]->getName()
                    );
                }
            }
        }

        $headers = ['Start', 'End', 'Duration', 'Project', 'Project Number', 'Activity', 'Activity Number', 'User', 'Description', 'Tags'];

        // Add BOM
        $response->getBody()->write($bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));

        // Create header
        $response->getBody()->write(implode($delimiter, $headers) . $record_seperator);

        foreach ($timesheets as $entry) {
            $line = array();
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['start']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, (is_null($entry['end']) ? "" : $entry['end'])) . $enclosure;
            $line[] = $entry['duration'];
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['projectName']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['projectNumber']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['activityName']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['activityNumber']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['userName']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['comment']) . $enclosure;

            $tags = array();
            if ($entry['tags']) {
                foreach ($entry['tags'] as $tag) {
                    $tags[] = $tag['name'];
                }
            }
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, implode("|", $tags)) . $enclosure;

            $response->getBody()->write(implode($delimiter, $line) . $record_seperator);
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
        $timesheet['deleteLink']  = $this->controllerHelper->getUrlFor($request, 'timesheets_delete',  ['timesheetId' => $id]);
        $timesheet['editLink']    = $this->controllerHelper->getUrlFor($request, 'timesheets_edit',    ['timesheetId' => $id]);
        $timesheet['stopLink']    = $this->controllerHelper->getUrlFor($request, 'timesheets_stop',    ['timesheetId' => $id]);
        $timesheet['restartLink'] = $canRestart
            ? $this->controllerHelper->getUrlFor($request, 'timesheets_restart', ['timesheetId' => $id])
            : '';

        return $timesheet;
    }

    private function buildTimesheetCriteriaFromSession(ServerRequestInterface $request, $currentUser): ?array
    {
        $session = $this->controllerHelper->getSessionValue($request, 'timesheets', []);

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
        $session = $this->controllerHelper->getSessionValue($request, 'teamsTimesheets', []);

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

    private function getTimesheetFilters(ServerRequestInterface $request, string $key): array
    {
        $filters = $this->controllerHelper->getSessionValue($request, $key, []);
        $filters = is_array($filters) ? $filters : [];
        $queryParams = $this->timesheetService->getQueryParams($request->getQueryParams(), $filters);
        return is_array($queryParams) ? $queryParams : [];
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

    /**
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

    /**
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
}
