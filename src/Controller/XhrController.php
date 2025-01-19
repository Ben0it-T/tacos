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
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

use App\Repository\UserRepository;
use PDO;

final class XhrController
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

    public function xhrAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $session = $request->getAttribute('session');
            $currentUser = $this->userService->findUser($session['auth']['userId']);

            $action = isset($args['action']) ? $args['action'] : '';
            $key = isset($args['key']) ? $args['key'] : '';
            switch ($action) {
                case 'projects':
                    if ($key === "") {
                        // Get all
                        $projectsNotInTeam = $this->projectService->findAllVisibleProjectsNotInTeam();
                        if ($currentUser->getRole() === 3) {
                            $projectsInTeams = $this->projectService->findAllVisibleProjectsHaveTeams();
                        }
                        else {
                            $projectsInTeams = $this->projectService->findAllVisibleProjectsByUserId($currentUser->getId());
                        }
                        $results = array_merge($projectsNotInTeam, $projectsInTeams);
                    }
                    else {
                        // Get by customer id
                        $results = ($currentUser->getRole() === 3) ? $this->projectService->findAllVisibleProjectsByCustomerId(intval($key)) : $this->projectService->findAllVisibleProjectsByUserIdAndCustomerId($currentUser->getId(), intval($key));
                    }
                    break;

                case 'activities':
                    if ($key === "") {
                        // Get all
                        $activitiesNotInTeam = $this->activityService->findAllVisibleActivitiesNotInTeam();
                        if ($currentUser->getRole() === 3) {
                            $activitiesInTeams = $this->activityService->findAllVisibleActivitiesHaveTeams();
                        }
                        else {
                            $activitiesInTeams = $this->activityService->findAllVisibleActivitiesByUserId($currentUser->getId());
                        }
                        $results = array_merge($activitiesNotInTeam, $activitiesInTeams);

                    }
                    else {
                        // Get by project id
                        $project = $this->projectService->findProject(intval($key));
                        $globalActivities = ($project->getGlobalActivities() === 1) ? $this->activityService->findAllGlobalActivities() : array();
                        $projectActivities = $this->activityService->findAllActivitiesByProjectId(intval($key));
                        $allowedActivities = $this->activityService->findProjectAllowedActivities(intval($key));
                        $allowedActivitiesIds = array();
                        foreach ($allowedActivities as $entry) {
                            $allowedActivitiesIds[] = $entry->getId();
                        }
                        $results = array_merge($globalActivities, $projectActivities);

                        // Filter allowed activities
                        foreach ($results as $index => $activity) {
                            if (!in_array($activity->getId(), $allowedActivitiesIds)) {
                                unset($results[$index]);
                            }
                        }
                    }
                    break;

                default:
                    $results = array();
                    break;
            }

            $resultsList = array();
            foreach ($results as $entry) {
                $resultsList[] = $entry->getId();
            }
            sort($resultsList);

            $response->getBody()->write(json_encode($resultsList));
            return $response->withHeader('Content-Type', 'application/json');
        }
        else {
            $response = new Response();
            return $response->withStatus(403);
        }
    }


}
