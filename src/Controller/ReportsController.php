<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\TimesheetService;
use App\Service\UserService;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class ReportsController
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
        $translations = $this->container->get('translations');
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $session = $request->getAttribute('session');
        $currentUser = $this->userService->findUser($session['auth']['userId']);

        // Report List
        $reports = array(
            1 => $translations['form_label_project'],
            2 => $translations['form_label_project_number'],
            3 => $translations['form_label_activity'],
            4 => $translations['form_label_activity_number'],
            5 => $translations['form_label_customer'],
            6 => $translations['form_label_customer_number'],
        );

        $data = $request->getQueryParams();
        // Filter : date
        if (isset($data['date']) && !empty($data['date'])) {
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
        else if (isset($session['reports']['dateStart'])) {
            $dateStart = $session['reports']['dateStart'];
            $dateEnd = $session['reports']['dateEnd'];
        }
        // Filter : report
        if (isset($data['report']) && !empty($data['report'])) {
            if (array_key_exists($data['report'], $reports)) {
                $selectedReport = $data['report'];
            }
        }
        else if (isset($session['reports']['report'])) {
            $selectedReport = $session['reports']['report'];
        }
        // Filter : format
        if (isset($data['format']) && !empty($data['format'])) {
            if (array_key_exists($data['format'], $reports)) {
                $selectedFormat = $data['format'];
            }
        }
        else if (isset($session['reports']['format'])) {
            $selectedFormat = $session['reports']['format'];
        }

        $startOfTheWeek = $translations['dateFormats_startOfTheWeek'];
        $day = (date('w')+(7-$startOfTheWeek))%7;
        $dateStart = isset($dateStart) ? $dateStart : date("Y-m-d", strtotime('-'.$day.' days'));
        $dateEnd = isset($dateEnd) ? $dateEnd : date("Y-m-d", strtotime('+'.(6-$day).' days'));
        $selectedReport = isset($selectedReport) ? $selectedReport : 1;
        $selectedFormat = isset($selectedFormat) ? $selectedFormat : 1;
        $_SESSION['reports']['dateStart'] = $dateStart;
        $_SESSION['reports']['dateEnd'] = $dateEnd;
        $_SESSION['reports']['report'] = $selectedReport;
        $_SESSION['reports']['format'] = $selectedFormat;

        // Get data
        $res = $this->timesheetService->getReportData($currentUser->getId(), $dateStart, $dateEnd, intval($selectedReport), intval($selectedFormat));
        $viewData = array();
        $viewData['daterange']['start'] = $dateStart;
        $viewData['daterange']['end'] = $dateEnd;
        $viewData['selectedReport'] = $selectedReport;
        $viewData['selectedFormat'] = $selectedFormat;
        $viewData['reports'] = $reports;
        $viewData['pivot'] = isset($res['pivot']) ? $res['pivot'] : array();
        $viewData['chart'] = isset($res['chart']) ? $res['chart'] : array();

        return $twig->render($response, 'reports.html.twig', $viewData);
    }
}
