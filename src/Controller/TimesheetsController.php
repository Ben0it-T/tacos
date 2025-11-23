<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ActivityService;
use App\Service\CustomerService;
use App\Service\ProjectService;
use App\Service\TagService;
use App\Service\TeamService;
use App\Service\TimesheetService;
use App\Service\UserService;

use App\Helper\RoundingHelper;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class TimesheetsController
{
    private $container;
    private $activityService;
    private $customerService;
    private $projectService;
    private $tagService;
    private $teamService;
    private $timesheetService;
    private $userService;
    private $roundingHelper;

    public function __construct(ContainerInterface $container, ActivityService $activityService, CustomerService $customerService, ProjectService $projectService, TagService $tagService, TeamService $teamService, TimesheetService $timesheetService, UserService $userService, RoundingHelper $roundingHelper)
    {
        $this->container = $container;
        $this->activityService = $activityService;
        $this->customerService = $customerService;
        $this->projectService = $projectService;
        $this->tagService = $tagService;
        $this->teamService = $teamService;
        $this->timesheetService = $timesheetService;
        $this->userService = $userService;
        $this->roundingHelper = $roundingHelper;
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

        // Get projects
        $projects = $this->projectService->findAllByUserIdAndVisibility($currentUser->getId(), 1);
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
        $timesheetRestart = $this->container->get('settings')['timesheet']['restart'];
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
            $timesheets[$i]['deleteLink'] = $routeParser->urlFor('timesheets_delete', array('timesheetId' => $timesheets[$i]['id']));
            $timesheets[$i]['editLink'] = $routeParser->urlFor('timesheets_edit', array('timesheetId' => $timesheets[$i]['id']));
            $timesheets[$i]['restartLink'] = $canRestart ? $routeParser->urlFor('timesheets_restart', array('timesheetId' => $timesheets[$i]['id'])) : '';
            $timesheets[$i]['stopLink'] = $routeParser->urlFor('timesheets_stop', array('timesheetId' => $timesheets[$i]['id']));
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
        $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $flash->getFirstMessage('error');

        return $twig->render($response, 'timesheets.html.twig', $viewData);
    }

    public function indexTeams(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        // Get Teams
        $teams = $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());
        $teamsIds = array();
        if (count($teams) > 0) {
            foreach ($teams as $team) {
                $teamsIds[] = $team->getId();
            }
        }

        // Get users in teams
        $users = $this->userService->findAllEnabledUsersInTeams($teamsIds);
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
        $projects = $this->projectService->findAllByTeamleaderIdAndVisibility($currentUser->getId(), 1);
        $projectsList = array();
        foreach ($projects as $entry) {
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
        $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $flash->getFirstMessage('error');

        return $twig->render($response, 'teams-timesheets.html.twig', $viewData);
    }

    public function createForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        // Get customers
        $customersNotInTeam = $this->customerService->findAllVisibleCustomersNotInTeam();
        $customersInTeams = $this->customerService->findAllVisibleCustomersByUserId($currentUser->getId());
        $customers = array_merge($customersNotInTeam, $customersInTeams);
        $customersList = array();
        foreach ($customers as $entry) {
            $customersList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }
        usort($customersList, fn($a, $b) => $a['name'] <=> $b['name']);

        // Get projects
        $projects = $this->projectService->findAllByUserIdAndVisibility($currentUser->getId(), 1);
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
        $rounding = $this->container->get('settings')['timesheet']['rounding'];
        $start = new \DateTime("now");
        if ($rounding['active']) $start = $this->roundingHelper->roundDateTime($start, $rounding['start']['minutes'], $rounding['start']['mode']);

        $viewData = array();
        $viewData['customers'] = $customersList;
        $viewData['projects'] = $projectsList;
        $viewData['activities'] = $activitiesList;
        $viewData['tags'] = $tags;
        $viewData['startDate'] = date_format($start,"Y-m-d H:i");
        $viewData['endDate'] = date("Y-m-d H:i", mktime(23, 59, 59, intval(date("n")), intval(date("j")), intval(date("Y"))));

        $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $flash->getFirstMessage('error');

        return $twig->render($response, 'timesheet-create.html.twig', $viewData);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $data['userId'] = $currentUser->getId();
        $errors = $this->timesheetService->createTimesheet($data);

        if (empty($errors)) {
            $flash->addMessage('success', $translations['form_success_create_activity']);
        }
        else {
            $flash->addMessage('error', $errors);
        }

        // redirect
        $url = $routeParser->urlFor('timesheets');
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

        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId(intval($args['timesheetId']), $currentUser->getId());

        if ($timesheet) {

            // Get customers
            $customersNotInTeam = $this->customerService->findAllVisibleCustomersNotInTeam();
            $customersInTeams = $this->customerService->findAllVisibleCustomersByUserId($currentUser->getId());
            $customers = array_merge($customersNotInTeam, $customersInTeams);
            $customersList = array();
            foreach ($customers as $entry) {
                $customersList[] = array(
                    'id' => $entry->getId(),
                    'name' => $entry->getName(),
                );
            }
            usort($customersList, fn($a, $b) => $a['name'] <=> $b['name']);

            // Get projects
            $projects = $this->projectService->findAllByUserIdAndVisibility($currentUser->getId(), 1);
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

            // Get project activities
            $project = $this->projectService->findProject($timesheet->getProjectId());
            $globalActivities = ($project->getGlobalActivities() === 1) ? $this->activityService->findAllGlobalActivities() : array();
            $projectActivities = $this->activityService->findAllProjectActivitiesByProjectId($timesheet->getProjectId());
            $projectActivities = array_merge($globalActivities, $projectActivities);

            $allowedActivities = $this->activityService->findAllByProjectId($timesheet->getProjectId());
            $allowedActivitiesIds = array();
            foreach ($allowedActivities as $entry) {
                $allowedActivitiesIds[] = $entry->getId();
            }

            $projectActivitiesIds = array();
            foreach ($projectActivities as $projectActivity) {
                if (in_array($projectActivity->getId(), $allowedActivitiesIds)) {
                    $projectActivitiesIds[] = $projectActivity->getId();
                }
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

            $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
            $viewData['flashMsgError'] = $flash->getFirstMessage('error');

            return $twig->render($response, 'timesheet-edit.html.twig', $viewData);
        }


        // redirect
        $url = $routeParser->urlFor('timesheets');
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

        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId(intval($args['timesheetId']), $currentUser->getId());

        if ($timesheet) {
            $errors = $this->timesheetService->updateTimesheet($timesheet, $data);
            if (empty($errors)) {
                $flash->addMessage('success', $translations['form_success_update']);
            }
            else {
                $flash->addMessage('error', $errors);
            }

            // redirect
            $url = $routeParser->urlFor('timesheets_edit', array('timesheetId' => $timesheet->getId()));
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        // redirect
        $url = $routeParser->urlFor('timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function restartAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId(intval($args['timesheetId']), $currentUser->getId());
        if ($timesheet) {
            $this->timesheetService->restartTimesheet($timesheet);
            $flash->addMessage('success', $translations['form_success_create_activity']);
        }

        // redirect
        $url = $routeParser->urlFor('timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function deleteForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

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

            return $twig->render($response, 'timesheet-delete.html.twig', $viewData);
        }

        // redirect
        $url = $routeParser->urlFor('timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function deleteAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId(intval($args['timesheetId']), $currentUser->getId());

        if ($timesheet) {
            $errors = $this->timesheetService->deleteTimesheet($timesheet);
            if (empty($errors)) {
                $flash->addMessage('success', $translations['form_success_delete_record']);
            }
            else {
                $flash->addMessage('error', $errors);
            }

        }

        // redirect
        $url = $routeParser->urlFor('timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function stopAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $timesheet = $this->timesheetService->findTimesheetByIdAndUserId(intval($args['timesheetId']), $currentUser->getId());

        if ($timesheet) {
            $timesheet->setEnd(date("Y-m-d H:i"));
            $errors = $this->timesheetService->stopTimesheet($timesheet);
            if (empty($errors)) {
                $flash->addMessage('success', $translations['form_success_update']);
            }
            else {
                $flash->addMessage('error', $errors);
            }
        }

        // redirect
        $url = $routeParser->urlFor('timesheets');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function exportTimesheets(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $session = $request->getAttribute('session');

        if (!isset($session['timesheets'])) {
            $routeParser = RouteContext::fromRequest($request)->getRouteParser();
            $url = $routeParser->urlFor('timesheets');
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        $currentUser = $this->userService->findUser($session['auth']['userId']);

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
        $session = $request->getAttribute('session');

        if (!isset($session['teamsTimesheets'])) {
            $routeParser = RouteContext::fromRequest($request)->getRouteParser();
            $url = $routeParser->urlFor('timesheets_teams');
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        $currentUser = $this->userService->findUser($session['auth']['userId']);

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
        $users = $this->userService->findAllEnabledUsersInTeams($teamsIds);
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
