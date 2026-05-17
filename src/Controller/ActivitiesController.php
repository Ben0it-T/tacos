<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\User;
use App\Helper\ControllerHelper;
use App\Service\ActivityService;
use App\Service\ProjectService;
use App\Service\TeamService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Flash\Messages;
use Slim\Views\Twig;

final class ActivitiesController
{
    private Twig $twig;
    private Messages $flash;
    private ActivityService $activityService;
    private ProjectService $projectService;
    private TeamService $teamService;
    private ControllerHelper $helper;
    private array $options;
    private array $translations;

    public function __construct(Twig $twig, Messages $flash, ActivityService $activityService, ProjectService $projectService, TeamService $teamService, ControllerHelper $helper, array $options, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->activityService = $activityService;
        $this->projectService = $projectService;
        $this->teamService = $teamService;
        $this->helper = $helper;
        $this->options = $options;
        $this->translations = $translations;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $projects = $this->helper->isAdmin($currentUser)
            ? $this->projectService->findAll(1)
            : $this->projectService->findAllByTeamleaderId($currentUser->getId(), 1);

        $teams = $this->helper->isAdmin($currentUser)
            ? $this->teamService->findAllTeams()
            : $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        $activities = $this->helper->isAdmin($currentUser)
            ? $this->activityService->findAllActivitiesWithTeamsCountAndProject()
            : $this->activityService->findAllActivitiesWithTeamsCountAndProjectByTeamleaderId($currentUser->getId());

        $activities = $this->addActivityLinks($request, $activities);

        return $this->twig->render($response, 'activities.html.twig', [
            'userRole'        => $currentUser->getRole(),
            'colors'          => $colors,
            'projects'        => $this->helper->mapIdNameList($projects),
            'teams'           => $this->helper->mapIdNameList($teams),
            'activities'      => $activities,
            'flashMsgSuccess' => $this->flash->getFirstMessage('success'),
            'flashMsgError'   => $this->flash->getFirstMessage('error'),
        ]);
    }

    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->activityService->createActivity($data);

        if ($errors === '') {
            $this->flash->addMessage('success', $this->translations['form_success_create_activity']);
        }
        else {
            $this->flash->addMessage('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'activities');
    }

    public function activityDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $activityId = (int)($args['activityId'] ?? 0);
        $activity = $this->getAccessibleActivity($currentUser, $activityId, strict: false);

        if (!$activity) {
            return $this->helper->redirect($request, $response, 'activities');
        }

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        $selectedProject = $activity->getProjectId() === null
            ? ''
            : $this->projectService->findProject($activity->getProjectId());

        $selectedTeams = $this->teamService->findAllTeamsByActivityId($activity->getId());

        return $this->twig->render($response, 'activity-details.html.twig', [
            'activity'        => $activity,
            'colors'          => $colors,
            'selectedProject' => $selectedProject,
            'selectedTeams'   => $selectedTeams,
        ]);
    }

    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $activityId = (int)($args['activityId'] ?? 0);
        $activity = $this->getAccessibleActivity($currentUser, $activityId, strict: true);

        if (!$activity) {
            return $this->helper->redirect($request, $response, 'activities');
        }

        $teams = $this->helper->isAdmin($currentUser)
            ? $this->teamService->findAllTeams()
            : $this->teamService->findAllTeamsByTeamleaderId($currentUser->getId());

        $colors = $this->helper->parseColorChoices((string)($this->options['colorChoices'] ?? ''));

        $selectedProject = $activity->getProjectId() === null
            ? ''
            : $this->projectService->findProject($activity->getProjectId());

        $selectedTeams = $this->teamService->findAllTeamsByActivityId($activity->getId());
        $selectedTeamsIds = array_map(static fn($t) => $t->getId(), $selectedTeams);

        return $this->twig->render($response, 'activity-edit.html.twig', [
            'activity'         => $activity,
            'colors'           => $colors,
            'selectedProject'  => $selectedProject,
            'teams'            => $this->helper->mapIdNameList($teams),
            'selectedTeams'    => $selectedTeams,
            'selectedTeamsIds' => $selectedTeamsIds,
            'flashMsgSuccess'  => $this->flash->getFirstMessage('success'),
            'flashMsgError'    => $this->flash->getFirstMessage('error'),
        ]);
    }

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $activityId = (int)($args['activityId'] ?? 0);
        $activity = $this->getAccessibleActivity($currentUser, $activityId, strict: true);

        if (!$activity) {
            return $this->helper->redirect($request, $response, 'activities');
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->activityService->updateActivity($activity, $data);
        if ($errors === '') {
            $this->flash->addMessage('success', $this->translations['form_success_update']);
            return $this->helper->redirect($request, $response, 'activities');
        }

        $this->flash->addMessage('error', $errors);
        return $this->helper->redirect($request, $response, 'activities_edit', ['activityId' => $activityId]);
    }

    // Helpers
    private function addActivityLinks(ServerRequestInterface $request, array $activities): array
    {
        foreach ($activities as &$activity) {
            if (!isset($activity['id'])) {
                continue;
            }
            $id = (int) $activity['id'];

            $activity['editLink'] = $this->helper->getUrlFor($request, 'activities_edit', ['activityId' => $id]);
            $activity['viewLink'] = $this->helper->getUrlFor($request, 'activities_details', ['activityId' => $id]);
        }
        unset($activity);

        return $activities;
    }

    private function getAccessibleActivity(User $user, int $activityId, bool $strict): Activity|false
    {
        if ($activityId <= 0) {
            return false;
        }

        if ($this->helper->isAdmin($user)) {
            return $this->activityService->findActivity($activityId);
        }

        return $strict
            ? $this->activityService->findOneByIdAndTeamleaderIdStrict($activityId, $user->getId())
            : $this->activityService->findOneByIdAndTeamleaderId($activityId, $user->getId());
    }
}
