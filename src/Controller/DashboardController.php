<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\ControllerHelper;
use App\Service\TimesheetService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Views\Twig;

final class DashboardController
{
    private Twig $twig;
    private TimesheetService $timesheetService;
    private ControllerHelper $helper;

    public function __construct(Twig $twig, TimesheetService $timesheetService, ControllerHelper $helper)
    {
        $this->twig = $twig;
        $this->timesheetService = $timesheetService;
        $this->helper = $helper;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser($request);
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $activeRecords = $this->timesheetService->countActiveRecordsByUserId($currentUser->getId());

        // Working hours today
        $workingHours = array(
            'today'     => $this->timesheetService->timeToString(intval($this->timesheetService->getTotalDurationByUserIdAndPeriod('today', $currentUser->getId()))),
            'week'      => $this->timesheetService->timeToString(intval($this->timesheetService->getTotalDurationByUserIdAndPeriod('week', $currentUser->getId()))),
            'lastweek'  => $this->timesheetService->timeToString(intval($this->timesheetService->getTotalDurationByUserIdAndPeriod('lastweek', $currentUser->getId()))),
            'month'     => $this->timesheetService->timeToString(intval($this->timesheetService->getTotalDurationByUserIdAndPeriod('month', $currentUser->getId()))),
            'lastmonth' => $this->timesheetService->timeToString(intval($this->timesheetService->getTotalDurationByUserIdAndPeriod('lastmonth', $currentUser->getId()))),
        );

        return $this->twig->render($response, 'dashboard.html.twig', [
            'activeRecords' => $activeRecords,
            'workingHours'  => $workingHours,
        ]);
    }
}
