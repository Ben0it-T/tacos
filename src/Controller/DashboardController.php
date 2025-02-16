<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\TimesheetService;
use App\Service\UserService;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Flash\Messages;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class DashboardController
{
    private $container;
    private $timesheetService;
    private $userService;

    public function __construct(ContainerInterface $container, TimesheetService $timesheetService, UserService $userService)
    {
        $this->container = $container;
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

        // Active records
        $activeRecords = $this->timesheetService->getNbOfActiveRecordsByUserId($currentUser->getId());

        // Working hours today
        $workingHours = array(
            'today' => $this->timesheetService->timeToString(intval($this->timesheetService->getWorkingHoursByTimePeriodAndUserId('today', $currentUser->getId()))),
            'week' => $this->timesheetService->timeToString(intval($this->timesheetService->getWorkingHoursByTimePeriodAndUserId('week', $currentUser->getId()))),
            'month' => $this->timesheetService->timeToString(intval($this->timesheetService->getWorkingHoursByTimePeriodAndUserId('month', $currentUser->getId()))),
        );

        $viewData = array();
        $viewData['activeRecords'] = $activeRecords;
        $viewData['workingHours'] = $workingHours;

        return $twig->render($response, 'dashboard.html.twig', $viewData);
    }
}
