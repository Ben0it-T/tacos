<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ActivityService;
use App\Service\ProjectService;
use App\Service\TeamService;
use App\Service\UserService;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class ActivitiesController
{
    private $container;
    private $activityService;
    private $projectService;
    private $teamService;
    private $userService;

    public function __construct(ContainerInterface $container, ActivityService $activityService, ProjectService $projectService, TeamService $teamService, UserService $userService)
    {
        $this->container = $container;
        $this->activityService = $activityService;
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

        // Get Activities
        if ($currentUser->getRole() === 3) {
            $activities = $this->activityService->findAllActivities();
        }
        else {
            $activitiesNotInTeam = $this->activityService->findAllActivitiesNotInTeam();
            $activitiesInUserTeams = $this->activityService->findAllActivitiesByUserId($currentUser->getId());
            $activities = array_merge($activitiesNotInTeam, $activitiesInUserTeams);
        }
        $activitiesList = array();
        foreach ($activities as $activity) {
            $activitiesList[] = array(
                'name' => $activity->getName(),
                'color' => $activity->getColor(),
                'number' => (is_null($activity->getNumber()) ? "" : $activity->getNumber()),
                'project' => (is_null($activity->getProjectId()) ? "" : $this->projectService->findProject($activity->getProjectId())),
                'description' => $activity->getComment(),
                'teams' => $this->activityService->getNbOfTeamsForActivity($activity->getId()),
                'visible' => $activity->getVisible(),
                'editLink' => $routeParser->urlFor('activities_edit', array('activityId' => $activity->getId())),
                'viewLink' => $routeParser->urlFor('activities_details', array('activityId' => $activity->getId())),
            );
        }
        usort($activitiesList, fn($a, $b) => $a['name'] <=> $b['name']);

        $viewData = array();
        $viewData['userRole'] = $currentUser->getRole();
        $viewData['colors'] = $colorsList;
        $viewData['projects'] = $projectsList;
        $viewData['teams'] = $teamsList;
        $viewData['activities'] = $activitiesList;

        $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
        $viewData['flashMsgError'] = $flash->getFirstMessage('error');

        return $twig->render($response, 'activities.html.twig', $viewData);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $flash = $this->container->get('flash');
        $translations = $this->container->get('translations');

        $data = $request->getParsedBody();
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $errors = $this->activityService->createActivity($data);

        if (empty($errors)) {
            $flash->addMessage('success', $translations['form_success_create_activity']);
        }
        else {
            $flash->addMessage('error', $errors);
        }

        // redirect
        $url = $routeParser->urlFor('activities');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function activityDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $twig  = $this->container->get(Twig::class);
        $translations = $this->container->get('translations');

        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        $activity = $this->activityService->findActivity(intval($args['activityId']));

        if ($currentUser->getRole() != 3) {
            $activitiesNotInTeam = $this->activityService->findAllActivitiesNotInTeam();
            $activitiesInUserTeams = $this->activityService->findAllActivitiesByUserId($currentUser->getId());
            $activities = array_merge($activitiesNotInTeam, $activitiesInUserTeams);

            $activitiesList = array();
            foreach ($activities as $entry) {
                $activitiesList[] = $entry->getId();
            }

            if (!in_array($activity->getId(), $activitiesList)) {
                $activity = false;
            }
        }

        if ($activity) {
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

            // Get selected Project
            $selectedProject = is_null($activity->getProjectId()) ? "" : $this->projectService->findProject($activity->getProjectId());

            // Get selected Teams
            $selectedTeams = $this->teamService->findAllTeamsByActivityId($activity->getId());

            $viewData = array();
            $viewData['activity'] = $activity;
            $viewData['colors'] = $colorsList;
            $viewData['selectedProject'] = $selectedProject;
            $viewData['selectedTeams'] = $selectedTeams;

            return $twig->render($response, 'activity-details.html.twig', $viewData);
        }

        // redirect
        $url = $routeParser->urlFor('activities');
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

        $activity = $this->activityService->findActivity(intval($args['activityId']));

        if ($currentUser->getRole() != 3) {
            $activities = $this->activityService->findAllActivitiesByUserId($currentUser->getId());

            $activitiesList = array();
            foreach ($activities as $entry) {
                $activitiesList[] = $entry->getId();
            }

            if (!in_array($activity->getId(), $activitiesList)) {
                $activity = false;
            }
        }

        if ($activity) {
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

            // Get selected Project
            $selectedProject = is_null($activity->getProjectId()) ? "" : $this->projectService->findProject($activity->getProjectId());

            // Get selected Teams
            $selectedTeams = $this->activityService->getTeamsForactivity($activity->getId());
            $selectedTeamsIds = array();
            foreach ($selectedTeams as $selectedTeam) {
                $selectedTeamsIds[] = $selectedTeam['teamId'];
            }

            $viewData = array();
            $viewData['activity'] = $activity;
            $viewData['colors'] = $colorsList;
            $viewData['selectedProject'] = $selectedProject;
            $viewData['teams'] = $teamsList;
            $viewData['selectedTeams'] = $selectedTeams;
            $viewData['selectedTeamsIds'] = $selectedTeamsIds;

            $viewData['flashMsgSuccess'] = $flash->getFirstMessage('success');
            $viewData['flashMsgError'] = $flash->getFirstMessage('error');

            return $twig->render($response, 'activity-edit.html.twig', $viewData);
        }

        // redirect
        $url = $routeParser->urlFor('activities');
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

        $activity = $this->activityService->findActivity(intval($args['activityId']));

        if ($currentUser->getRole() != 3) {
            $activities = $this->activityService->findAllActivitiesByUserId($currentUser->getId());

            $activitiesList = array();
            foreach ($activities as $entry) {
                $activitiesList[] = $entry->getId();
            }

            if (!in_array($activity->getId(), $activitiesList)) {
                $activity = false;
            }
        }

        if ($activity) {
            $errors = $this->activityService->updateActivity($activity, $data);

            if (empty($errors)) {
                $flash->addMessage('success', $translations['form_success_update']);
            }
            else {
                $flash->addMessage('error', $errors);
            }

            // redirect
            $url = $routeParser->urlFor('activities_edit', array('activityId' => $args['activityId']));
            return $response->withStatus(302)->withHeader('Location', $url);
        }

        // redirect
        $url = $routeParser->urlFor('activities');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

}
