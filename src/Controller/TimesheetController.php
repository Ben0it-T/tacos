<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ActivityService;
use App\Service\CustomerService;
use App\Service\ProjectService;
use App\Service\TagService;
use App\Service\TimesheetService;
use App\Service\UserService;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

use App\Repository\UserRepository;
use PDO;

final class TimesheetController
{
    private $container;
    private $activityService;
    private $customerService;
    private $projectService;
    private $tagService;
    private $timesheetService;
    private $userService;

    public function __construct(ContainerInterface $container, ActivityService $activityService, CustomerService $customerService, ProjectService $projectService, TagService $tagService, TimesheetService $timesheetService, UserService $userService)
    {
        $this->container = $container;
        $this->activityService = $activityService;
        $this->customerService = $customerService;
        $this->projectService = $projectService;
        $this->tagService = $tagService;
        $this->timesheetService = $timesheetService;
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

        // Date filter
        $data = $request->getQueryParams();
        if (isset($data['date']) &&  !empty($data['date'])) {
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
        else if (isset($session['timesheet']['dateStart'])) {
            $dateStart = $session['timesheet']['dateStart'];
            $dateEnd = $session['timesheet']['dateEnd'];
        }

        $startOfTheWeek = $translations['dateFormats_startOfTheWeek'];
        $day = (date('w')+(7-$startOfTheWeek))%7;

        $dateStart = isset($dateStart) ? $dateStart : date("Y-m-d", strtotime('-'.$day.' days'));
        $dateEnd = isset($dateEnd) ? $dateEnd : date("Y-m-d", strtotime('+'.(6-$day).' days'));
        $_SESSION['timesheet']['dateStart'] = $dateStart;
        $_SESSION['timesheet']['dateEnd'] = $dateEnd;

        // Get timesheets
        $timesheets = $this->timesheetService->findAllTimesheetByUserIdBetween($currentUser->getId(), $dateStart, $dateEnd);

        $timesheetsList = $arrayName = array();
        $duration = 0;
        foreach ($timesheets as $timesheet) {
            $timesheetsList[] = array(
                'start' => $timesheet->getStart(),
                'end' => $timesheet->getEnd(),
                'duration' => $this->timesheetService->timeToString($timesheet->getDuration()),
                'project' => $this->projectService->findProject($timesheet->getProjectId()),
                'activity' => $this->activityService->findActivity($timesheet->getActivityId()),
                'description' => $timesheet->getComment(),
                'tags' => $this->tagService->findAllTagsByTimesheetId($timesheet->getId()),
                'editLink' => $routeParser->urlFor('timesheet_edit', array('timesheetId' => $timesheet->getId())),
                'stopLink' => $routeParser->urlFor('timesheet_stop', array('timesheetId' => $timesheet->getId())),
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

        $viewData = array();
        $viewData['daterange']['start'] = $dateStart;
        $viewData['daterange']['end'] = $dateEnd;
        $viewData['colors'] = $colorsList;
        $viewData['timesheets'] = $timesheetsList;
        $viewData['duration'] = $duration > 0 ? $this->timesheetService->timeToString($duration) : "";

        $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $flash->getFirstMessage('error');

        return $twig->render($response, 'timesheet.html.twig', $viewData);
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

        $viewData = array();
        $viewData['colors'] = $colorsList;
        $viewData['customers'] = $customersList;
        $viewData['projects'] = $projectsList;
        $viewData['activities'] = $activitiesList;
        $viewData['tags'] = $tags;

        $viewData['startDate'] = date("Y-m-d H:i");
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
        $url = $routeParser->urlFor('timesheet');
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
            $url = $routeParser->urlFor('timesheet_edit', array('timesheetId' => $timesheet->getId()));
            return $response->withStatus(302)->withHeader('Location', $url);
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
        $url = $routeParser->urlFor('timesheet');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function exportTimesheets(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $dateStart = $session['timesheet']['dateStart'];
        $dateEnd = $session['timesheet']['dateEnd'];

        // Set
        $delimiter = ";";
        $enclosure = '"';
        $escape_char = "\\";
        $record_seperator = "\r\n";

        // Get timesheets
        $timesheets = $this->timesheetService->findAllTimesheetByUserIdBetween($currentUser->getId(), $dateStart, $dateEnd);

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
            $line[] = $enclosure . str_replace($enclosure, $escape_char . $enclosure, $entry['end']) . $enclosure;
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
}
