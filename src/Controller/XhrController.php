<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\ControllerHelper;
use App\Service\ActivityService;
use App\Service\ProjectService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class XhrController
{
    private ActivityService $activityService;
    private ProjectService $projectService;
    private ControllerHelper $helper;

    public function __construct(ActivityService $activityService, ProjectService $projectService, ControllerHelper $helper)
    {
        $this->activityService = $activityService;
        $this->projectService = $projectService;
        $this->helper = $helper;
    }

    /**
     * Return, as a JSON list of ids, the projects ('projects', optionally by customer)
     * or activities ('activities', optionally by project) available to the current user
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments ('action', optional 'key')
     * @return ResponseInterface
     */
    public function xhrAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($request->getHeaderLine('X-Requested-With') !== 'XMLHttpRequest') {
            // UX constraint, not security
            return $response->withStatus(403);
        }

        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            $response->getBody()->write(json_encode([]));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $action = (string)($args['action'] ?? '');
        $key    = (string)($args['key'] ?? '');

        switch ($action) {
            case 'projects':
                $results = ($key === '')
                    ? $this->projectService->findAllByUserId($currentUser->getId(), 1)
                    : $this->projectService->findAllByUserIdAndCustomerId($currentUser->getId(), (int)$key, 1);
                break;

            case 'activities':
                $results = ($key === '')
                    ? $this->activityService->findAllByUserId($currentUser->getId(), 1)
                    : $this->activityService->findAllByUserIdAndProjectId($currentUser->getId(), (int)$key, 1);
                break;

            default:
                $results = [];
                break;
        }

        $resultsList = [];
        foreach ($results as $entry) {
            $resultsList[] = $entry->getId();
        }
        sort($resultsList);

        $response->getBody()->write(json_encode($resultsList));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
