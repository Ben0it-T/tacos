<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\ControllerHelper;
use App\Service\TimesheetService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Views\Twig;

final class ReportsController
{
    private Twig $twig;
    private TimesheetService $timesheetService;
    private ControllerHelper $helper;
    private array $translations;

    public function __construct(Twig $twig, TimesheetService $timesheetService, ControllerHelper $helper, array $translations)
    {
        $this->twig = $twig;
        $this->timesheetService = $timesheetService;
        $this->helper = $helper;
        $this->translations = $translations;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $session = $this->helper->getSessionValue('reports', []);

        // Report List
        $reports = [
            1 => $this->translations['form_label_project'],
            2 => $this->translations['form_label_project_number'],
            3 => $this->translations['form_label_activity'],
            4 => $this->translations['form_label_activity_number'],
            5 => $this->translations['form_label_customer'],
            6 => $this->translations['form_label_customer_number'],
        ];

        // Format List
        $formats = [
            1 => $this->translations['form_label_format_time'],
            2 => $this->translations['form_label_format_minutes'],
            3 => $this->translations['form_label_format_pcent'],
            4 => $this->translations['form_label_format_number'],
        ];

        $data = (array) $request->getQueryParams();

        // Filter : date
        if (isset($data['date']) && !empty($data['date'])) {
            $today = date("Y-m-d");

            $parts = explode(" - ", $data['date'], 2);

            if (count($parts) === 2) {
                [$date1, $date2] = $parts;
            } else {
                $date1 = $today;
                $date2 = $today;
            }

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
        else if (isset($session['dateStart'])) {
            $dateStart = $session['dateStart'];
            $dateEnd = $session['dateEnd'];
        }
        // Filter : report
        if (isset($data['report']) && !empty($data['report'])) {
            if (array_key_exists($data['report'], $reports)) {
                $selectedReport = $data['report'];
            }
        }
        else if (isset($session['report'])) {
            $selectedReport = $session['report'];
        }
        // Filter : format
        if (isset($data['format']) && !empty($data['format'])) {
            if (array_key_exists($data['format'], $formats)) {
                $selectedFormat = $data['format'];
            }
        }
        else if (isset($session['format'])) {
            $selectedFormat = $session['format'];
        }

        $startOfTheWeek = (int) $this->translations['dateFormats_startOfTheWeek'];
        $day = (date('w')+(7-$startOfTheWeek))%7;
        $dateStart = $dateStart ?? date("Y-m-d", strtotime('-'.$day.' days'));
        $dateEnd = $dateEnd ?? date("Y-m-d", strtotime('+'.(6-$day).' days'));
        $selectedReport = $selectedReport ?? 1;
        $selectedFormat = $selectedFormat ?? 1;
        $this->helper->setSessionValue('reports', [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'report' => $selectedReport,
            'format' => $selectedFormat,
        ]);

        // Get data
        $res = $this->timesheetService->getReportData($currentUser->getId(), $dateStart, $dateEnd, intval($selectedReport), intval($selectedFormat));

        return $this->twig->render($response, 'reports.html.twig', [
            'daterange' => [
                'start' => $dateStart,
                'end'   => $dateEnd,
            ],
            'selectedReport' => $selectedReport,
            'selectedFormat' => $selectedFormat,
            'reports' => $reports,
            'formats' => $formats,
            'pivot'   => $res['pivot'] ?? [],
            'chart'   => $res['chart'] ?? [],
        ]);
    }
}
