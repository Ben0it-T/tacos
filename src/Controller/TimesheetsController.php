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

        // Get projects
        $projects = $this->projectService->findAllByUserId($currentUser->getId(), 1);
        $projectsIds = array();
        $projectsList = array();
        foreach ($projects as $entry) {
            $projectsIds[] = $entry->getId();
            $projectsList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }

        // Get activities
        $activities = $this->activityService->findAllByUserId($currentUser->getId(), 1);
        $activitiesIds = array();
        $activitiesList = array();
        foreach ($activities as $entry) {
            $activitiesIds[] = $entry->getId();
            $activitiesList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }

        // Get tags
        $tags = $this->tagService->findAllVisible();
        $tagsIds = array();
        foreach ($tags as $entry) {
            $tagsIds[] = $entry->getId();
        }

        // Get Query Params
        $session = $request->getAttribute('session');
        $queryParams = $this->timesheetService->getQueryParams($request->getQueryParams(), $session['timesheets'] ?? []);
        $queryParams['users'] = [$currentUser->getId()];

        // Check selected Projects
        for ($i=0; $i < count($queryParams['projects']) ; $i++) {
            if (!in_array($queryParams['projects'][$i], $projectsIds)) {
                unset($queryParams['projects'][$i]);
            }
        }

        // Check selected Activities
        for ($i=0; $i < count($queryParams['activities']) ; $i++) {
            if (!in_array($queryParams['activities'][$i], $activitiesIds)) {
                unset($queryParams['activities'][$i]);
            }
        }

        // Check selected Tags
        for ($i=0; $i < count($queryParams['tags']) ; $i++) {
            if (!in_array($queryParams['tags'][$i], $tagsIds)) {
                unset($queryParams['tags'][$i]);
            }
        }

        // Store filters
        $_SESSION['timesheets'] = array();
        $_SESSION['timesheets']['start'] = $queryParams['start'];
        $_SESSION['timesheets']['end'] = $queryParams['end'];
        $_SESSION['timesheets']['projects'] = $queryParams['projects'];
        $_SESSION['timesheets']['activities'] = $queryParams['activities'];
        $_SESSION['timesheets']['tags'] = $queryParams['tags'];

        // Get timesheets
        $allTags = $this->tagService->findAll();
        $timesheets = $this->timesheetService->findTimesheetsByCriteria($queryParams);
        $duration = 0;
        $timesheetRestart = $this->options['restart'];
        for ($i=0; $i < count($timesheets); $i++) {
            // Restart timesheet
            $canRestart = false;
            if ($timesheetRestart['active']) {
                $interval = date_diff(date_create("now"), date_create($timesheets[$i]['start']));
                if ($interval->format('%a') <= $timesheetRestart['interval']) {
                    $canRestart = true;
                }
            }
            // Duration
            $duration += $timesheets[$i]['duration'];
            $timesheets[$i]['duration'] = $this->timesheetService->timeToString($timesheets[$i]['duration']);
            // Tags
            $timesheets[$i]['tags'] = array();
            if (!is_null($timesheets[$i]['tagIds'])) {
                $tagsIds = explode(',', $timesheets[$i]['tagIds']);
                foreach ($tagsIds as $tagId) {
                    $timesheets[$i]['tags'][] = array(
                        'name' => $allTags[$tagId]->getName(),
                        'color' => $allTags[$tagId]->getColor()
                    );
                }
            }
            // Links
            $timesheets[$i]['deleteLink'] = $this->controllerHelper->getUrlFor($request, 'timesheets_delete', array('timesheetId' => $timesheets[$i]['id']));
            $timesheets[$i]['editLink'] = $this->controllerHelper->getUrlFor($request, 'timesheets_edit', array('timesheetId' => $timesheets[$i]['id']));
            $timesheets[$i]['restartLink'] = $canRestart ? $this->controllerHelper->getUrlFor($request, 'timesheets_restart', array('timesheetId' => $timesheets[$i]['id'])) : '';
            $timesheets[$i]['stopLink'] = $this->controllerHelper->getUrlFor($request, 'timesheets_stop', array('timesheetId' => $timesheets[$i]['id']));
        }

        // Render
        $viewData = array();
        // Form
        $viewData['daterange']['start'] = $queryParams['start'];
        $viewData['daterange']['end'] = $queryParams['end'];
        $viewData['selectedProjects'] = $queryParams['projects'];
        $viewData['selectedActivities'] = $queryParams['activities'];
        $viewData['selectedTags'] = $queryParams['tags'];
        $viewData['projects'] = $projectsList;
        $viewData['activities'] = $activitiesList;
        $viewData['tags'] = $tags;
        // timesheets
        $viewData['timesheets'] = $timesheets;
        $viewData['duration'] = $duration > 0 ? $this->timesheetService->timeToString($duration) : "";
        // flash
        $viewData['flashMsgSuccess'] = $this->flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $this->flash->getFirstMessage('error');

        return $this->twig->render($response, 'timesheets.html.twig', $viewData);
    }

    public function indexTeams(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->controllerHelper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->controllerHelper->redirect($request, $response, 'login');
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

        // Get projects.
        $projects = $this->projectService->findAllByTeamleaderId($currentUser->getId(), 1);
        $projectsIds = array();
        $projectsList = array();
        foreach ($projects as $entry) {
            $projectsIds[] = $entry->getId();
            $projectsList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }

        // Get activities
        $activities = $this->activityService->findAllByTeamleaderId($currentUser->getId(), 1);
        $activitiesIds = array();
        $activitiesList = array();
        foreach ($activities as $entry) {
            $activitiesIds[] = $entry->getId();
            $activitiesList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }

        // Get tags
        $tags = $this->tagService->findAllVisible();
        $tagsIds = array();
        foreach ($tags as $entry) {
            $tagsIds[] = $entry->getId();
        }

        // Get Query Params
        $session = $request->getAttribute('session');
        $queryParams = $this->timesheetService->getQueryParams($request->getQueryParams(), $session['teamsTimesheets'] ?? []);

        // Check Query Params
        // Check selected Users
        for ($i=0; $i < count($queryParams['users']) ; $i++) {
            if (!in_array($queryParams['users'][$i], $usersIds)) {
                unset($queryParams['users'][$i]);
            }
        }

        // Check selected Projects
        for ($i=0; $i < count($queryParams['projects']) ; $i++) {
            if (!in_array($queryParams['projects'][$i], $projectsIds)) {
                unset($queryParams['projects'][$i]);
            }
        }

        // Check selected Activities
        for ($i=0; $i < count($queryParams['activities']) ; $i++) {
            if (!in_array($queryParams['activities'][$i], $activitiesIds)) {
                unset($queryParams['activities'][$i]);
            }
        }

        // Check selected Tags
        for ($i=0; $i < count($queryParams['tags']) ; $i++) {
            if (!in_array($queryParams['tags'][$i], $tagsIds)) {
                unset($queryParams['tags'][$i]);
            }
        }

        // Store filters
        $_SESSION['teamsTimesheets'] = array();
        $_SESSION['teamsTimesheets']['start'] = $queryParams['start'];
        $_SESSION['teamsTimesheets']['end'] = $queryParams['end'];
        $_SESSION['teamsTimesheets']['users'] = $queryParams['users'];
        $_SESSION['teamsTimesheets']['projects'] = $queryParams['projects'];
        $_SESSION['teamsTimesheets']['activities'] = $queryParams['activities'];
        $_SESSION['teamsTimesheets']['tags'] = $queryParams['tags'];

        $criteria = $queryParams;
        $criteria['users'] = empty($queryParams['users']) ? $usersIds : $queryParams['users'];

        // Get timesheets
        $allTags = $this->tagService->findAll();
        $timesheets = $this->timesheetService->findTimesheetsByCriteria($criteria);
        $duration = 0;
        for ($i=0; $i < count($timesheets); $i++) {
            // Duration
            $duration += $timesheets[$i]['duration'];
            $timesheets[$i]['duration'] = $this->timesheetService->timeToString($timesheets[$i]['duration']);
            // Tags
            $timesheets[$i]['tags'] = array();
            if (!is_null($timesheets[$i]['tagIds'])) {
                $tagsIds = explode(',', $timesheets[$i]['tagIds']);
                foreach ($tagsIds as $tagId) {
                    $timesheets[$i]['tags'][] = array(
                        'name' => $allTags[$tagId]->getName(),
                        'color' => $allTags[$tagId]->getColor()
                    );
                }
            }
        }

        // Render
        $viewData = array();
        // Form
        $viewData['daterange']['start'] = $queryParams['start'];
        $viewData['daterange']['end'] = $queryParams['end'];
        $viewData['selectedUsers'] = $queryParams['users'];
        $viewData['selectedProjects'] = $queryParams['projects'];
        $viewData['selectedActivities'] = $queryParams['activities'];
        $viewData['selectedTags'] = $queryParams['tags'];
        $viewData['users'] = $usersList;
        $viewData['projects'] = $projectsList;
        $viewData['activities'] = $activitiesList;
        $viewData['tags'] = $tags;
        // timesheets
        $viewData['timesheets'] = $timesheets;
        $viewData['duration'] = $duration > 0 ? $this->timesheetService->timeToString($duration) : "";
        // flash
        $viewData['flashMsgSuccess'] = $this->flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $this->flash->getFirstMessage('error');

        return $this->twig->render($response, 'teams-timesheets.html.twig', $viewData);
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

        $session = $request->getAttribute('session');

        if (!isset($session['timesheets'])) {
            $url = $this->controllerHelper->getUrlFor($request, 'timesheets');
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        $criteria = array(
            'start' => $session['timesheets']['start'],
            'end' => $session['timesheets']['end'],
            'users' => [$currentUser->getId()],
            'projects' => $session['timesheets']['projects'],
            'activities' => $session['timesheets']['activities'],
            'tags' => $session['timesheets']['tags']
        );

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

        $session = $request->getAttribute('session');

        if (!isset($session['teamsTimesheets'])) {
            $url = $this->controllerHelper->getUrlFor($request, 'timesheets_teams');
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        $criteria = array(
            'start' => $session['teamsTimesheets']['start'],
            'end' => $session['teamsTimesheets']['end'],
            'users' => $session['teamsTimesheets']['users'],
            'projects' => $session['teamsTimesheets']['projects'],
            'activities' => $session['teamsTimesheets']['activities'],
            'tags' => $session['teamsTimesheets']['tags']
        );

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
}
