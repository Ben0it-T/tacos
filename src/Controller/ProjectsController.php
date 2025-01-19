<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ActivityService;
use App\Service\CustomerService;
use App\Service\ProjectService;
use App\Service\TeamService;
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
    private $teamService;
    private $userService;

    public function __construct(ContainerInterface $container, ActivityService $activityService, CustomerService $customerService, ProjectService $projectService, TeamService $teamService, UserService $userService)
    {
        $this->container = $container;
        $this->activityService = $activityService;
        $this->customerService = $customerService;
        $this->projectService = $projectService;
        $this->teamService = $teamService;
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
            $projects = $this->projectService->findAllProjects();
        }
        else {
            $projectsNotInTeam = $this->projectService->findAllProjectsNotInTeam();
            $projectsInUserTeams = $this->projectService->findAllProjectsByUserId($currentUser->getId());
            $projects = array_merge($projectsNotInTeam, $projectsInUserTeams);
        }
        $projectsList = array();
        foreach ($projects as $project) {
            $projectsList[] = array(
                'name' => $project->getName(),
                'color' => $project->getColor(),
                'customer' => $this->customerService->findCustomer($project->getCustomerId())->getName(),
                'description' => $project->getComment(),
                'teams' => $this->projectService->getNbOfTeamsForProject($project->getId()),
                'visible' => $project->getVisible(),
                'editLink' => $routeParser->urlFor('projects_edit', array('projectId' => $project->getId())),
                'viewLink' => $routeParser->urlFor('projects_details', array('projectId' => $project->getId())),
            );
        }
        usort($projectsList, fn($a, $b) => strtoupper($a['name']) <=> strtoupper($b['name']));

        $viewData = array();
        $viewData['userRole'] = $currentUser->getRole();
        $viewData['colors'] = $colorsList;
        $viewData['customers'] = $customersList;
        $viewData['projects'] = $projectsList;
        $viewData['teams'] = $teamsList;

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

        $project = $this->projectService->findProject(intval($args['projectId']));

        if ($currentUser->getRole() != 3) {
            $projectsNotInTeam = $this->projectService->findAllProjectsNotInTeam();
            $projectsInUserTeams = $this->projectService->findAllProjectsByUserId($currentUser->getId());
            $projects = array_merge($projectsNotInTeam, $projectsInUserTeams);

            $projectsList = array();
            foreach ($projects as $entry) {
                $projectsList[] = $entry->getId();
            }

            if (!in_array($project->getId(), $projectsList)) {
                $project = false;
            }
        }

        if ($project) {
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

            // Get selected Customer
            $selectedCustomer = $this->customerService->findCustomer($project->getCustomerId());

            // Get selected Teams
            $selectedTeams = $this->teamService->findAllTeamsByProjectId($project->getId());

            // Get activities
            $globalActivities = $this->activityService->findAllGlobalActivities();
            $projectActivities = $this->activityService->findAllActivitiesByProjectId($project->getId());
            $allowedActivities = $this->activityService->findProjectAllowedActivities($project->getId());
            $allowedActivitiesIds = array();
            foreach ($allowedActivities as $entry) {
                $allowedActivitiesIds[] = $entry->getId();
            }

            $viewData = array();
            $viewData['colors'] = $colorsList;
            $viewData['project'] = $project;
            $viewData['selectedCustomer'] = $selectedCustomer;
            $viewData['selectedTeams'] = $selectedTeams;
            $viewData['globalActivities'] = $globalActivities;
            $viewData['projectActivities'] = $projectActivities;
            $viewData['allowedActivitiesIds'] = $allowedActivitiesIds;

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

        $project = $this->projectService->findProject(intval($args['projectId']));

        if ($currentUser->getRole() != 3) {
            $projects = $this->projectService->findAllProjectsByUserId($currentUser->getId());

            $projectsList = array();
            foreach ($projects as $entry) {
                $projectsList[] = $entry->getId();
            }

            if (!in_array($project->getId(), $projectsList)) {
                $project = false;
            }
        }


        if ($project) {
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

            // Get teams
            $teams = ($currentUser->getRole() === 3) ? $this->teamService->findAllTeams() : $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());
            $teamsList = array();
            foreach ($teams as $team) {
                $teamsList[] = array(
                    'id' => $team->getId(),
                    'name' => $team->getName(),
                );
            }

            $selectedTeams = $this->projectService->getTeamsForProject($project->getId());
            $selectedTeamsIds = array();
            foreach ($selectedTeams as $selectedTeam) {
                $selectedTeamsIds[] = $selectedTeam['teamId'];
            }

            // Get activities
            $globalActivities = $this->activityService->findAllGlobalActivities();
            $projectActivities = $this->activityService->findAllActivitiesByProjectId($project->getId());
            $projectAuthorisedActivities = $this->activityService->findProjectAllowedActivities($project->getId());
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

        $project = $this->projectService->findProject(intval($args['projectId']));

        if ($currentUser->getRole() != 3) {
            $projects = $this->projectService->findAllProjectsByUserId($currentUser->getId());

            $projectsList = array();
            foreach ($projects as $entry) {
                $projectsList[] = $entry->getId();
            }

            if (!in_array($project->getId(), $projectsList)) {
                $project = false;
            }
        }

        if ($project) {
            $errors = $this->projectService->updateProject($project, $data);

            if (empty($errors)) {
                $flash->addMessage('success', $translations['form_success_update']);
            }
            else {
                $flash->addMessage('error', $errors);
            }

            // redirect
            $url = $routeParser->urlFor('projects_edit', array('projectId' => $args['projectId']));
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        // redirect
        $url = $routeParser->urlFor('projects');
        return $response->withStatus(302)->withHeader('Location', $url);
    }


}
