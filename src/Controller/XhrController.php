<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ActivityService;
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
    private $projectService;
    private $tagService;
    private $timesheetService;
    private $userService;

    public function __construct(ContainerInterface $container, ActivityService $activityService, ProjectService $projectService, TagService $tagService, TimesheetService $timesheetService, UserService $userService)
    {
        $this->container = $container;
        $this->activityService = $activityService;
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
                        $results = $this->projectService->findAllByUserIdAndVisibility($currentUser->getId(), 1);
                    }
                    else {
                        // Get by customer id
                        $results = $this->projectService->findAllByUserIdAndCustomerIdAndVisibility($currentUser->getId(), intval($key), 1);
                    }
                    break;

                case 'activities':
                    if ($key === "") {
                        // Get all
                        $results = $this->activityService->findAllByUserId($currentUser->getId(), 1);

                    }
                    else {
                        // Get by project id
                        $results = $this->activityService->findAllByUserIdAndProjectId($currentUser->getId(), intval($key), 1);
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
