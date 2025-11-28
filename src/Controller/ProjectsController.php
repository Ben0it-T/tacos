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

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class ProjectsController
{
    private $container;
    private $activityService;
    private $customerService;
    private $projectService;
    private $tagService;
    private $teamService;
    private $timesheetService;
    private $userService;

    public function __construct(ContainerInterface $container, ActivityService $activityService, CustomerService $customerService, ProjectService $projectService, TagService $tagService, TeamService $teamService, TimesheetService $timesheetService, UserService $userService)
    {
        $this->container = $container;
        $this->activityService = $activityService;
        $this->customerService = $customerService;
        $this->projectService = $projectService;
        $this->tagService = $tagService;
        $this->teamService = $teamService;
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

        // Get customers
        if ($currentUser->getRole() === 3) {
            $customers = $this->customerService->findAll(1);
        }
        else {
            $customers = $this->customerService->findAllByTeamleaderId($currentUser->getId(), 1);
        }
        $customersList = array();
        foreach ($customers as $entry) {
            $customersList[] = array(
                'id' => $entry->getId(),
                'name' => $entry->getName(),
            );
        }

        // Get teams
        $teams = ($currentUser->getRole() === 3) ? $this->teamService->findAllTeams() : $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());
        $teamsList = array();
        foreach ($teams as $team) {
            $teamsList[] = array(
                'id' => $team->getId(),
                'name' => $team->getName(),
            );
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
        if ($currentUser->getRole() === 3) {
            $projects = $this->projectService->findAllProjectsWithTeamsCountAndCustomer();
        }
        else {
            $projects = $this->projectService->findAllProjectsWithTeamsCountAndCustomerByTeamleaderId($currentUser->getId());
        }

        for ($i=0; $i < count($projects); $i++) {
            $projects[$i]['editLink'] = $routeParser->urlFor('projects_edit', array('projectId' => $projects[$i]['id']));
            $projects[$i]['viewLink'] = $routeParser->urlFor('projects_details', array('projectId' => $projects[$i]['id']));
        }

        $viewData = array();
        $viewData['userRole'] = $currentUser->getRole();
        $viewData['colors'] = $colorsList;
        $viewData['customers'] = $customersList;
        $viewData['teams'] = $teamsList;
        $viewData['projects'] = $projects;

        $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $flash->getFirstMessage('error');

        return $twig->render($response, 'projects.html.twig', $viewData);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $errors = $this->projectService->createProject($data);

        if (empty($errors)) {
            $flash->addMessage('success', $translations['form_success_create_project']);
        }
        else {
            $flash->addMessage('error', $errors);
        }

        // redirect
        $url = $routeParser->urlFor('projects');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function projectDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        if ($currentUser->getRole() === 3) {
            $project = $this->projectService->findProject(intval($args['projectId']));
        }
        else {
            $project = $this->projectService->findOneByIdAndTeamleaderId(intval($args['projectId']), intval($currentUser->getId()));
        }

        if ($project) {

            // Get selected Customer
            $selectedCustomer = $this->customerService->findCustomer($project->getCustomerId());

            // Get selected Teams
            $selectedTeams = $this->teamService->findAllTeamsByProjectId($project->getId());

            // Get activities
            $globalActivities = $this->activityService->findAllGlobalActivities();
            $projectActivities = $this->activityService->findAllProjectActivitiesByProjectId($project->getId());
            $allowedActivities = $this->activityService->findAllByProjectId($project->getId());
            $allowedActivitiesIds = array();
            foreach ($allowedActivities as $entry) {
                $allowedActivitiesIds[] = $entry->getId();
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
            $users = $this->userService->findAllUsersInTeams($teamsIds);
            $usersIds = array();
            if (count($users) > 0) {
                foreach ($users as $usr) {
                    $usersIds[] = $usr->getId();
                }
            }

            // Get timesheets
            $criteria = array(
                'users' => $usersIds,
                'projects' => [$project->getId()],
            );
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

            $viewData = array();
            $viewData['project'] = $project;
            $viewData['selectedCustomer'] = $selectedCustomer;
            $viewData['selectedTeams'] = $selectedTeams;
            $viewData['globalActivities'] = $globalActivities;
            $viewData['projectActivities'] = $projectActivities;
            $viewData['allowedActivitiesIds'] = $allowedActivitiesIds;
            $viewData['timesheets'] = $timesheets;
            $viewData['duration'] = $duration > 0 ? $this->timesheetService->timeToString($duration) : "";

            return $twig->render($response, 'project-details.html.twig', $viewData);
        }

        // redirect
        $url = $routeParser->urlFor('projects');
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

        if ($currentUser->getRole() === 3) {
            $project = $this->projectService->findProject(intval($args['projectId']));
        }
        else {
            $project = $this->projectService->findOneByIdAndTeamleaderIdStrict(intval($args['projectId']), intval($currentUser->getId()));
        }

        if ($project) {
            // Get customers
            if ($currentUser->getRole() === 3) {
                $customers = $this->customerService->findAll(1);
            }
            else {
                $customers = $this->customerService->findAllByTeamleaderId($currentUser->getId(), 1);
            }
            $customersList = array();
            foreach ($customers as $entry) {
                $customersList[] = array(
                    'id' => $entry->getId(),
                    'name' => $entry->getName(),
                );
            }

            // Get teams
            $teams = ($currentUser->getRole() === 3) ? $this->teamService->findAllTeams() : $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());
            $teamsList = array();
            foreach ($teams as $team) {
                $teamsList[] = array(
                    'id' => $team->getId(),
                    'name' => $team->getName(),
                );
            }

            $selectedTeams = $this->teamService->findAllTeamsByProjectId($project->getId());
            $selectedTeamsIds = array();
            foreach ($selectedTeams as $selectedTeam) {
                $selectedTeamsIds[] = $selectedTeam->getId();
            }

            // Get activities
            $globalActivities = $this->activityService->findAllGlobalActivities();
            $projectActivities = $this->activityService->findAllProjectActivitiesByProjectId($project->getId());
            $projectAuthorisedActivities = $this->activityService->findAllByProjectId($project->getId());
            $projectAuthorisedActivitiesList = array();
            foreach ($projectAuthorisedActivities as $entry) {
                $projectAuthorisedActivitiesList[] = $entry->getId();
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
            $viewData['project'] = $project;
            $viewData['colors'] = $colorsList;
            $viewData['customers'] = $customersList;
            $viewData['teams'] = $teamsList;
            $viewData['selectedTeams'] = $selectedTeams;
            $viewData['selectedTeamsIds'] = $selectedTeamsIds;

            $viewData['globalActivities'] = $globalActivities;
            $viewData['projectActivities'] = $projectActivities;
            $viewData['projectActivitiesIds'] = $projectAuthorisedActivitiesList;


            $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
            $viewData['flashMsgError'] = $flash->getFirstMessage('error');

            return $twig->render($response, 'project-edit.html.twig', $viewData);
        }

        // redirect
        $url = $routeParser->urlFor('projects');
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

        if ($currentUser->getRole() === 3) {
            $project = $this->projectService->findProject(intval($args['projectId']));
        }
        else {
            $project = $this->projectService->findOneByIdAndTeamleaderIdStrict(intval($args['projectId']), intval($currentUser->getId()));
        }

        if ($project) {
            $errors = $this->projectService->updateProject($project, $data);

            if (empty($errors)) {
                $flash->addMessage('success', $translations['form_success_update']);
            }
            else {
                $flash->addMessage('error', $errors);
                $url = $routeParser->urlFor('projects_edit', array('projectId' => $args['projectId']));
                return $response->withStatus(302)->withHeader('Location', $url);
            }
        }

        // redirect
        $url = $routeParser->urlFor('projects');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

}
