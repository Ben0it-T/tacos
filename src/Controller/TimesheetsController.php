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

        // Filters
        $data = $request->getQueryParams();
        // Date
        if (isset($data['date']) && !empty($data['date'])) {
            list($date1, $date2) = explode(" - ", $data['date']);

            $dateStart = date_create($date1);
            if ($dateStart instanceof \DateTime) {
                $dateStart = date_format($dateStart,"Y-m-d");
            }
            else {
                $dateStart = date("Y-m-d");
            }

            $dateEnd= date_create($date2);
            if ($dateEnd instanceof \DateTime) {
                $dateEnd = date_format($dateEnd,"Y-m-d");
            }
            else {
                $dateEnd = $dateStart;
            }
        }
        else if (isset($session['timesheets']['dateStart'])) {
            $dateStart = $session['timesheets']['dateStart'];
            $dateEnd = $session['timesheets']['dateEnd'];
        }
        // Projects
        $selectedProjects = array();
        if (isset($data['projects'])) {
            if (($key = array_search("", $data['projects'])) !== false) {
                unset($data['projects'][$key]);
            }
            $selectedProjects = $data['projects'];
        }
        else if (isset($session['timesheets']['projects'])) {
            $selectedProjects = $session['timesheets']['projects'];
        }
        // Activities
        $selectedActivities = array();
        if (isset($data['activities'])) {
            if (($key = array_search("", $data['activities'])) !== false) {
                unset($data['activities'][$key]);
            }
            $selectedActivities = $data['activities'];
        }
        else if (isset($session['timesheets']['activities'])) {
            $selectedActivities = $session['timesheets']['activities'];
        }
        // Tags
        $selectedTags = array();
        if (isset($data['tags'])) {
            if (($key = array_search("", $data['tags'])) !== false) {
                unset($data['tags'][$key]);
            }
            $selectedTags = $data['tags'];
        }
        else if (isset($session['timesheets']['tags'])) {
            $selectedTags = $session['timesheets']['tags'];
        }


        $startOfTheWeek = $translations['dateFormats_startOfTheWeek'];
        $day = (date('w')+(7-$startOfTheWeek))%7;
        $dateStart = isset($dateStart) ? $dateStart : date("Y-m-d", strtotime('-'.$day.' days'));
        $dateEnd = isset($dateEnd) ? $dateEnd : date("Y-m-d", strtotime('+'.(6-$day).' days'));
        $_SESSION['timesheets']['dateStart'] = $dateStart;
        $_SESSION['timesheets']['dateEnd'] = $dateEnd;
        $_SESSION['timesheets']['projects'] = $selectedProjects;
        $_SESSION['timesheets']['activities'] = $selectedActivities;
        $_SESSION['timesheets']['tags'] = $selectedTags;

        // Get timesheets
        $timesheets = $this->timesheetService->findAllTimesheetByUserIdAndFilters($currentUser->getId(), $dateStart, $dateEnd, $selectedProjects, $selectedActivities, $selectedTags);

        $timesheetsList = array();
        $duration = 0;
        $timesheetRestart = $this->container->get('settings')['timesheet']['restart'];
        foreach ($timesheets as $timesheet) {
            // Restart timesheet
            $canRestart = false;
            if ($timesheetRestart['active']) {
                $interval = date_diff(date_create("now"), date_create($timesheet->getStart()));
                if ($interval->format('%a') <= $timesheetRestart['interval']) {
                    $canRestart = true;
                }
            }

            $timesheetsList[] = array(
                'start' => $timesheet->getStart(),
                'end' => $timesheet->getEnd(),
                'duration' => $this->timesheetService->timeToString($timesheet->getDuration()),
                'project' => $this->projectService->findProject($timesheet->getProjectId()),
                'activity' => $this->activityService->findActivity($timesheet->getActivityId()),
                'description' => $timesheet->getComment(),
                'tags' => $this->tagService->findAllTagsByTimesheetId($timesheet->getId()),
                'deleteLink' => $routeParser->urlFor('timesheets_delete', array('timesheetId' => $timesheet->getId())),
                'editLink' => $routeParser->urlFor('timesheets_edit', array('timesheetId' => $timesheet->getId())),
                'restartLink' => $canRestart ? $routeParser->urlFor('timesheets_restart', array('timesheetId' => $timesheet->getId())) : '',
                'stopLink' => $routeParser->urlFor('timesheets_stop', array('timesheetId' => $timesheet->getId())),
            );
            $duration += $timesheet->getDuration();
        }

        // Get colors
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

        // Get projects
        $projectsNotInTeam = $this->projectService->findAllVisibleProjectsNotInTeam();
        if ($currentUser->getRole() === 3) {
            $projectsInTeams = $this->projectService->findAllVisibleProjectsHaveTeams();
        }
        else {
            $projectsInTeams = $this->projectService->findAllVisibleProjectsByUserId($currentUser->getId());
        }
        $projects = array_merge($projectsNotInTeam, $projectsInTeams);
        $projectsList = array();
        foreach ($projects as $entry) {
            $projectsList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }
        usort($projectsList, fn($a, $b) => $a['name'] <=> $b['name']);

        // Get activities
        $activitiesNotInTeam = $this->activityService->findAllVisibleActivitiesNotInTeam();
        if ($currentUser->getRole() === 3) {
            $activitiesInTeams = $this->activityService->findAllVisibleActivitiesHaveTeams();
        }
        else {
            $activitiesInTeams = $this->activityService->findAllVisibleActivitiesByUserId($currentUser->getId());
        }
        $activities = array_merge($activitiesNotInTeam, $activitiesInTeams);
        $activitiesList = array();
        foreach ($activities as $entry) {
            $activitiesList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }
        usort($activitiesList, fn($a, $b) => $a['name'] <=> $b['name']);

        // Get tags
        $tags = $this->tagService->findAllVisibleTags();



        $viewData = array();
        $viewData['daterange']['start'] = $dateStart;
        $viewData['daterange']['end'] = $dateEnd;
        $viewData['selectedProjects'] = $selectedProjects;
        $viewData['selectedActivities'] = $selectedActivities;
        $viewData['selectedTags'] = $selectedTags;

        $viewData['colors'] = $colorsList;
        $viewData['projects'] = $projectsList;
        $viewData['activities'] = $activitiesList;
        $viewData['tags'] = $tags;
        $viewData['timesheets'] = $timesheetsList;
        $viewData['duration'] = $duration > 0 ? $this->timesheetService->timeToString($duration) : "";

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

        // Get colors
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

        // Get projects
        $projectsNotInTeam = $this->projectService->findAllVisibleProjectsNotInTeam();
        if ($currentUser->getRole() === 3) {
            $projectsInTeams = $this->projectService->findAllVisibleProjectsHaveTeams();
        }
        else {
            $projectsInTeams = $this->projectService->findAllVisibleProjectsByUserId($currentUser->getId());
        }
        $projects = array_merge($projectsNotInTeam, $projectsInTeams);
        $projectsIds = array();
        $projectsList = array();
        foreach ($projects as $entry) {
            $projectsIds[] = $entry->getId();
            $projectsList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }
        usort($projectsList, fn($a, $b) => $a['name'] <=> $b['name']);

        // Get activities
        $activitiesNotInTeam = $this->activityService->findAllVisibleActivitiesNotInTeam();
        if ($currentUser->getRole() === 3) {
            $activitiesInTeams = $this->activityService->findAllVisibleActivitiesHaveTeams();
        }
        else {
            $activitiesInTeams = $this->activityService->findAllVisibleActivitiesByUserId($currentUser->getId());
        }
        $activities = array_merge($activitiesNotInTeam, $activitiesInTeams);
        $activitiesIds = array();
        $activitiesList = array();
        foreach ($activities as $entry) {
            $activitiesIds[] = $entry->getId();
            $activitiesList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }
        usort($activitiesList, fn($a, $b) => $a['name'] <=> $b['name']);

        // Get tags
        $tags = $this->tagService->findAllVisibleTags();
        $tagsIds = array();
        foreach ($tags as $entry) {
            $tagsIds[] = $entry->getId();
        }

        // Get filters
        $data = $request->getQueryParams();
        // Date
        if (isset($data['date']) && !empty($data['date'])) {
            list($date1, $date2) = explode(" - ", $data['date']);

            $dateStart = date_create($date1);
            if ($dateStart instanceof \DateTime) {
                $dateStart = date_format($dateStart,"Y-m-d");
            }
            else {
                $dateStart = date("Y-m-d");
            }

            $dateEnd= date_create($date2);
            if ($dateEnd instanceof \DateTime) {
                $dateEnd = date_format($dateEnd,"Y-m-d");
            }
            else {
                $dateEnd = $dateStart;
            }
        }
        else if (isset($session['teamsTimesheets']['dateStart'])) {
            $dateStart = $session['teamsTimesheets']['dateStart'];
            $dateEnd = $session['teamsTimesheets']['dateEnd'];
        }
        // Users
        $selectedUsers = array();
        if (isset($data['users'])) {
            if (($key = array_search("", $data['users'])) !== false) {
                unset($data['users'][$key]);
            }
            $selectedUsers = $data['users'];
        }
        else if (isset($session['teamsTimesheets']['users'])) {
            $selectedUsers = $session['teamsTimesheets']['users'];
        }
        // Projects
        $selectedProjects = array();
        if (isset($data['projects'])) {
            if (($key = array_search("", $data['projects'])) !== false) {
                unset($data['projects'][$key]);
            }
            $selectedProjects = $data['projects'];
        }
        else if (isset($session['teamsTimesheets']['projects'])) {
            $selectedProjects = $session['teamsTimesheets']['projects'];
        }
        // Activities
        $selectedActivities = array();
        if (isset($data['activities'])) {
            if (($key = array_search("", $data['activities'])) !== false) {
                unset($data['activities'][$key]);
            }
            $selectedActivities = $data['activities'];
        }
        else if (isset($session['teamsTimesheets']['activities'])) {
            $selectedActivities = $session['teamsTimesheets']['activities'];
        }
        // Tags
        $selectedTags = array();
        if (isset($data['tags'])) {
            if (($key = array_search("", $data['tags'])) !== false) {
                unset($data['tags'][$key]);
            }
            $selectedTags = $data['tags'];
        }
        else if (isset($session['teamsTimesheets']['tags'])) {
            $selectedTags = $session['teamsTimesheets']['tags'];
        }


        // Start date and end date
        $startOfTheWeek = $translations['dateFormats_startOfTheWeek'];
        $day = (date('w')+(7-$startOfTheWeek))%7;
        $dateStart = isset($dateStart) ? $dateStart : date("Y-m-d", strtotime('-'.$day.' days'));
        $dateEnd = isset($dateEnd) ? $dateEnd : date("Y-m-d", strtotime('+'.(6-$day).' days'));

        // Check selected Users
        for ($i=0; $i < count($selectedUsers) ; $i++) {
            if (!in_array($selectedUsers[$i], $usersIds)) {
                unset($selectedUsers[$i]);
            }
        }
        $selectedUsersIds = empty($selectedUsers) ? $usersIds : $selectedUsers;

        // Check selected Projects
        for ($i=0; $i < count($selectedProjects) ; $i++) {
            if (!in_array($selectedProjects[$i], $projectsIds)) {
                unset($selectedProjects[$i]);
            }
        }

        // Check selected Activities
        for ($i=0; $i < count($selectedActivities) ; $i++) {
            if (!in_array($selectedActivities[$i], $activitiesIds)) {
                unset($selectedActivities[$i]);
            }
        }

        // Check selected Tags
        for ($i=0; $i < count($selectedTags) ; $i++) {
            if (!in_array($selectedTags[$i], $tagsIds)) {
                unset($selectedTags[$i]);
            }
        }

        // Store filters
        $_SESSION['teamsTimesheets']['dateStart'] = $dateStart;
        $_SESSION['teamsTimesheets']['dateEnd'] = $dateEnd;
        $_SESSION['teamsTimesheets']['users'] = $selectedUsers;
        $_SESSION['teamsTimesheets']['projects'] = $selectedProjects;
        $_SESSION['teamsTimesheets']['activities'] = $selectedActivities;
        $_SESSION['teamsTimesheets']['tags'] = $selectedTags;

        // Get timesheets
        $timesheets = $this->timesheetService->findAllTimesheetsByUsersIdAndFilters($selectedUsersIds, $dateStart, $dateEnd, $selectedProjects, $selectedActivities, $selectedTags);
        $timesheetsList = array();
        $duration = 0;
        foreach ($timesheets as $timesheet) {
            $timesheetsList[] = array(
                'start' => $timesheet->getStart(),
                'end' => $timesheet->getEnd(),
                'duration' => $this->timesheetService->timeToString($timesheet->getDuration()),
                'user' => $this->userService->findUser($timesheet->getUserId()),
                'project' => $this->projectService->findProject($timesheet->getProjectId()),
                'activity' => $this->activityService->findActivity($timesheet->getActivityId()),
                'description' => $timesheet->getComment(),
                'tags' => $this->tagService->findAllTagsByTimesheetId($timesheet->getId()),
            );
            $duration += $timesheet->getDuration();
        }

        $viewData = array();
        $viewData['daterange']['start'] = $dateStart;
        $viewData['daterange']['end'] = $dateEnd;
        $viewData['selectedUsers'] = $selectedUsers;
        $viewData['selectedProjects'] = $selectedProjects;
        $viewData['selectedActivities'] = $selectedActivities;
        $viewData['selectedTags'] = $selectedTags;

        $viewData['colors'] = $colorsList;
        $viewData['users'] = $usersList;
        $viewData['projects'] = $projectsList;
        $viewData['activities'] = $activitiesList;
        $viewData['tags'] = $tags;
        $viewData['timesheets'] = $timesheetsList;
        $viewData['duration'] = $duration > 0 ? $this->timesheetService->timeToString($duration) : "";

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

        // Get colors
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

        // Get customers
        $customersNotInTeam = $this->customerService->findAllVisibleCustomersNotInTeam();
        if ($currentUser->getRole() === 3) {
            $customersInTeams = $this->customerService->findAllVisibleCustomersHaveTeams();
        }
        else {
            $customersInTeams = $this->customerService->findAllVisibleCustomersByUserId($currentUser->getId());
        }
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
        $projectsNotInTeam = $this->projectService->findAllVisibleProjectsNotInTeam();
        if ($currentUser->getRole() === 3) {
            $projectsInTeams = $this->projectService->findAllVisibleProjectsHaveTeams();
        }
        else {
            $projectsInTeams = $this->projectService->findAllVisibleProjectsByUserId($currentUser->getId());
        }
        $projects = array_merge($projectsNotInTeam, $projectsInTeams);
        $projectsList = array();
        foreach ($projects as $entry) {
            $projectsList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }
        usort($projectsList, fn($a, $b) => $a['name'] <=> $b['name']);

        // Get activities
        $activitiesNotInTeam = $this->activityService->findAllVisibleActivitiesNotInTeam();
        if ($currentUser->getRole() === 3) {
            $activitiesInTeams = $this->activityService->findAllVisibleActivitiesHaveTeams();
        }
        else {
            $activitiesInTeams = $this->activityService->findAllVisibleActivitiesByUserId($currentUser->getId());
        }
        $activities = array_merge($activitiesNotInTeam, $activitiesInTeams);
        $activitiesList = array();
        foreach ($activities as $entry) {
            $activitiesList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }
        usort($activitiesList, fn($a, $b) => $a['name'] <=> $b['name']);

        // Get tags
        $tags = $this->tagService->findAllVisibleTags();

        // Start date
        $rounding = $this->container->get('settings')['timesheet']['rounding'];
        $start = new \DateTime("now");
        if ($rounding['active']) $start = $this->roundingHelper->roundDateTime($start, $rounding['start']['minutes'], $rounding['start']['mode']);

        $viewData = array();
        $viewData['colors'] = $colorsList;
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
            // Get colors
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

            // Get customers
            $customersNotInTeam = $this->customerService->findAllVisibleCustomersNotInTeam();
            if ($currentUser->getRole() === 3) {
                $customersInTeams = $this->customerService->findAllVisibleCustomersHaveTeams();
            }
            else {
                $customersInTeams = $this->customerService->findAllVisibleCustomersByUserId($currentUser->getId());
            }
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
            $projectsNotInTeam = $this->projectService->findAllVisibleProjectsNotInTeam();
            if ($currentUser->getRole() === 3) {
                $projectsInTeams = $this->projectService->findAllVisibleProjectsHaveTeams();
            }
            else {
                $projectsInTeams = $this->projectService->findAllVisibleProjectsByUserId($currentUser->getId());
            }
            $projects = array_merge($projectsNotInTeam, $projectsInTeams);
            $projectsList = array();
            foreach ($projects as $entry) {
                $projectsList[] = array(
                    'id' => $entry->getId(),
                    'name' => $entry->getName(),
                );
            }
            usort($projectsList, fn($a, $b) => $a['name'] <=> $b['name']);

            // Get activities
            $activitiesNotInTeam = $this->activityService->findAllVisibleActivitiesNotInTeam();
            if ($currentUser->getRole() === 3) {
                $activitiesInTeams = $this->activityService->findAllVisibleActivitiesHaveTeams();
            }
            else {
                $activitiesInTeams = $this->activityService->findAllVisibleActivitiesByUserId($currentUser->getId());
            }
            $activities = array_merge($activitiesNotInTeam, $activitiesInTeams);
            $activitiesList = array();
            foreach ($activities as $entry) {
                $activitiesList[] = array(
                    'id' => $entry->getId(),
                    'name' => $entry->getName(),
                );
            }
            usort($activitiesList, fn($a, $b) => $a['name'] <=> $b['name']);

            // Get tags
            $tags = $this->tagService->findAllVisibleTags();

            // Get selected tags
            $selectedTags = $this->tagService->findAllTagsByTimesheetId($timesheet->getId());
            $selectedTagsIds = array();
            foreach ($selectedTags as $selectedTag) {
                $selectedTagsIds[] = $selectedTag->getId();
            }

            // Get project activities
            $project = $this->projectService->findProject($timesheet->getProjectId());
            $globalActivities = ($project->getGlobalActivities() === 1) ? $this->activityService->findAllGlobalActivities() : array();
            $projectActivities = $this->activityService->findAllActivitiesByProjectId($timesheet->getProjectId());
            $projectActivities = array_merge($globalActivities, $projectActivities);

            $allowedActivities = $this->activityService->findProjectAllowedActivities($timesheet->getProjectId());
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

            $viewData['colors'] = $colorsList;
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
                'tags' => $this->tagService->findAllTagsByTimesheetId($timesheet->getId()),
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
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $dateStart = $session['timesheets']['dateStart'];
        $dateEnd = $session['timesheets']['dateEnd'];
        $selectedProjects = $session['timesheets']['projects'];
        $selectedActivities = $session['timesheets']['activities'];
        $selectedTags = $session['timesheets']['tags'];

        // Set
        $delimiter = ";";
        $enclosure = '"';
        $escape_char = "\\";
        $record_seperator = "\r\n";

        // Get timesheets
        //$timesheets = $this->timesheetService->findAllTimesheetByUserIdBetween($currentUser->getId(), $dateStart, $dateEnd);
        $timesheets = $this->timesheetService->findAllTimesheetByUserIdAndFilters($currentUser->getId(), $dateStart, $dateEnd, $selectedProjects, $selectedActivities, $selectedTags);

        $timesheetsList = $arrayName = array();
        $duration = 0;
        foreach ($timesheets as $timesheet) {
            $timesheetsList[] = array(
                'start' => $timesheet->getStart(),
                'end' => $timesheet->getEnd(),
                'duration' => $timesheet->getDuration(),
                'project' => $this->projectService->findProject($timesheet->getProjectId()),
                'activity' => $this->activityService->findActivity($timesheet->getActivityId()),
                'description' => $timesheet->getComment(),
                'tags' => $this->tagService->findAllTagsByTimesheetId($timesheet->getId()),
            );
            $duration += $timesheet->getDuration();
        }

        $headers = ['Start', 'End', 'Duration', 'Project', 'Project Number', 'Activity', 'Activity Number', 'Description', 'Tags'];

        // Add BOM
        $response->getBody()->write($bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));

        // Create header
        $response->getBody()->write(implode($delimiter, $headers) . $record_seperator);

        foreach ($timesheetsList as $entry) {
            $line = array();
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['start']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, (is_null($entry['end']) ? "" : $entry['end'])) . $enclosure;
            $line[] = $entry['duration'];
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['project']->getName()) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['project']->getNumber()) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['activity']->getName()) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['activity']->getNumber()) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['description']) . $enclosure;

            $tags = array();
            if ($entry['tags']) {
                foreach ($entry['tags'] as $tag) {
                    $tags[] = $tag->getName();
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
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $dateStart = $session['teamsTimesheets']['dateStart'];
        $dateEnd = $session['teamsTimesheets']['dateEnd'];
        $selectedUsers = $session['teamsTimesheets']['users'];
        $selectedProjects = $session['teamsTimesheets']['projects'];
        $selectedActivities = $session['teamsTimesheets']['activities'];
        $selectedTags = $session['teamsTimesheets']['tags'];

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
        $selectedUsersIds = empty($selectedUsers) ? $usersIds : $selectedUsers;

        // Set
        $delimiter = ";";
        $enclosure = '"';
        $escape_char = "\\";
        $record_seperator = "\r\n";

        // Get timesheets
        $timesheets = $this->timesheetService->findAllTimesheetsByUsersIdAndFilters($selectedUsersIds, $dateStart, $dateEnd, $selectedProjects, $selectedActivities, $selectedTags);

        $timesheetsList = array();
        $duration = 0;
        foreach ($timesheets as $timesheet) {
            $timesheetsList[] = array(
                'start' => $timesheet->getStart(),
                'end' => $timesheet->getEnd(),
                'duration' => $this->timesheetService->timeToString($timesheet->getDuration()),
                'user' => $this->userService->findUser($timesheet->getUserId()),
                'project' => $this->projectService->findProject($timesheet->getProjectId()),
                'activity' => $this->activityService->findActivity($timesheet->getActivityId()),
                'description' => $timesheet->getComment(),
                'tags' => $this->tagService->findAllTagsByTimesheetId($timesheet->getId()),
            );
            $duration += $timesheet->getDuration();
        }

        $headers = ['Start', 'End', 'Duration', 'Project', 'Project Number', 'Activity', 'Activity Number', 'User', 'Description', 'Tags'];

        // Add BOM
        $response->getBody()->write($bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));

        // Create header
        $response->getBody()->write(implode($delimiter, $headers) . $record_seperator);

        foreach ($timesheetsList as $entry) {
            $line = array();
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['start']) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, (is_null($entry['end']) ? "" : $entry['end'])) . $enclosure;
            $line[] = $entry['duration'];
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['project']->getName()) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['project']->getNumber()) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['activity']->getName()) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['activity']->getNumber()) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['user']->getName()) . $enclosure;
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['description']) . $enclosure;

            $tags = array();
            if ($entry['tags']) {
                foreach ($entry['tags'] as $tag) {
                    $tags[] = $tag->getName();
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
