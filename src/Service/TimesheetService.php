<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Timesheet;
use App\Helper\RoundingHelper;
use App\Helper\ValidationHelper;
use App\Repository\ActivityRepository;
use App\Repository\TagRepository;
use App\Repository\TimesheetRepository;

use Psr\Log\LoggerInterface;

final class TimesheetService
{
    private ActivityRepository $activityRepository;
    private TagRepository $tagRepository;
    private TimesheetRepository $timesheetRepository;
    private RoundingHelper $roundingHelper;
    private ValidationHelper $validationHelper;
    private LoggerInterface $logger;
    private array $options;
    private array $translations;

    public function __construct(ActivityRepository $activityRepository, TagRepository $tagRepository, TimesheetRepository $timesheetRepository, RoundingHelper $roundingHelper, ValidationHelper $validationHelper, LoggerInterface $logger, array $options, array $translations) {
        $this->activityRepository = $activityRepository;
        $this->tagRepository = $tagRepository;
        $this->timesheetRepository = $timesheetRepository;
        $this->roundingHelper = $roundingHelper;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
        $this->options = $options;
        $this->translations = $translations;
    }

    /**
     * Get QueryParams (criteria)
     *
     * @param array $queryParams
     * @param array $savedParams
     * @return array of params
     */
    public function getQueryParams(array $queryParams, array $savedParams): array {

        // Date
        if (isset($queryParams['date']) && !empty($queryParams['date'])) {
            if (str_contains($queryParams['date'], " - ")) {
                list($date1, $date2) = explode(" - ", $queryParams['date']);
            }
            else {
                $date1 = $date2 = $queryParams['date'];
            }

            $start = date_create($date1);
            if ($start instanceof \DateTimeInterface) {
                $start = $start->format('Y-m-d');
            }
            else {
                $start = date("Y-m-d");
            }

            $end = date_create($date2);
            if ($end instanceof \DateTimeInterface) {
                $end = $end->format('Y-m-d');
            }
            else {
                $end = $start;
            }
        }
        else if (isset($savedParams['start'])) {
            $start = $savedParams['start'];
            $end = $savedParams['end'];
        }

        // Users
        $users = array();
        if (isset($queryParams['users']) && is_array($queryParams['users'])) {
            if (($key = array_search("", $queryParams['users'])) !== false) {
                unset($queryParams['users'][$key]);
            }
            $users = $queryParams['users'];
        }
        else if (isset($savedParams['users'])) {
            $users = $savedParams['users'];
        }

        // Projects
        $projects = array();
        if (isset($queryParams['projects']) && is_array($queryParams['projects'])) {
            if (($key = array_search("", $queryParams['projects'])) !== false) {
                unset($queryParams['projects'][$key]);
            }
            $projects = $queryParams['projects'];
        }
        else if (isset($savedParams['projects'])) {
            $projects = $savedParams['projects'];
        }

        // Activities
        $activities = array();
        if (isset($queryParams['activities']) && is_array($queryParams['activities'])) {
            if (($key = array_search("", $queryParams['activities'])) !== false) {
                unset($queryParams['activities'][$key]);
            }
            $activities = $queryParams['activities'];
        }
        else if (isset($savedParams['activities'])) {
            $activities = $savedParams['activities'];
        }

        // Tags
        $tags = array();
        if (isset($queryParams['tags']) && is_array($queryParams['tags'])) {
            if (($key = array_search("", $queryParams['tags'])) !== false) {
                unset($queryParams['tags'][$key]);
            }
            $tags = $queryParams['tags'];
        }
        else if (isset($savedParams['tags'])) {
            $tags = $savedParams['tags'];
        }

        $startOfTheWeek = $this->translations['dateFormats_startOfTheWeek'];
        $day = (date('w')+(7-$startOfTheWeek))%7;
        $start = isset($start) ? $start : date("Y-m-d", strtotime('-'.$day.' days'));
        $end = isset($end) ? $end : date("Y-m-d", strtotime('+'.(6-$day).' days'));

        return array(
            'start' => $start,
            'end' => $end,
            'users' => $users,
            'projects' => $projects,
            'activities' => $activities,
            'tags' => $tags
        );
    }

    /**
     * Find Timesheet by id
     *
     * @param int $id
     * @return Timesheet entity or false
     */
    public function findTimesheet(int $id): Timesheet|false {
        return $this->timesheetRepository->find($id);
    }

    /**
     * Find Timesheet by id and user Id
     *
     * @param int $id
     * @param int $userId
     * @return Timesheet or false
     */
    public function findTimesheetByIdAndUserId(int $id, int $userId): Timesheet|false {
        return $this->timesheetRepository->findOneByIdAndUserId($id, $userId);
    }

    /**
     * Find the last active Timesheet by user ID
     *
     * @param int $userId
     * @return Timesheet or false
     */
    public function findOneActiveRecordByUserId(int $userId): Timesheet|false {
        return $this->timesheetRepository->findOneActiveRecordByUserId($userId);
    }

    /**
     * Find all active Timesheets by user Id
     *
     * @param int $userId
     * @return array of Timesheets
     */
    public function findAllActiveRecordsByUserId(int $userId): array {
        return $this->timesheetRepository->findAllActiveRecordsByUserId($userId);
    }

    /**
     * Find Timesheets (with User, Projet, Activity and Tags) by criteria (dates, users, projects, activities, tags)
     *
     * @param array $queryParams
     * @return array of Timesheet with User, Projet, Activity and Tags
     */
    public function findTimesheetsByCriteria(array $criteria): array {
        if (!isset($criteria['users'])) {
            return [];
        }

        $criteria['dates'] = (isset($criteria['start']) && isset($criteria['end'])) ? [$criteria['start'], $criteria['end']] : array();
        $criteria['projects'] = isset($criteria['projects']) ? $criteria['projects'] : array();
        $criteria['activities'] = isset($criteria['activities']) ? $criteria['activities'] : array();
        $criteria['tags'] = isset($criteria['tags']) ? $criteria['tags'] : array();

        return $this->timesheetRepository->findTimesheetsWithUserAndProjectAndActivityAndTagsByCriteria($criteria['dates'], $criteria['users'], $criteria['projects'], $criteria['activities'], $criteria['tags']);
    }

    /**
     * Get number of active records by user Id
     *
     * @param int $userId
     * @return int
     */
    public function countActiveRecordsByUserId(int $userId): int {
        return $this->timesheetRepository->countActiveRecordsByUserId($userId);
    }

    /**
     * Get total duration (minutes) for a user in a given time period
     *
     * @param string $timePeriod 'today', 'week', 'lastweek', 'month', 'lastmonth'
     * @param int $userId
     * @return int Total duration in minutes
     */
    public function getTotalDurationByUserIdAndPeriod (string $timePeriod, int $userId): int {
        return $this->timesheetRepository->getTotalDurationByUserIdAndPeriod($timePeriod, $userId);
    }

    /**
     * Convert time to H:i
     *
     * @param ?int $time
     * @return string $timeString
     */
    public function timeToString(?int $time): string {
        //return !is_null($time) ? sprintf('%02d:%02d', floor($time/60), $time%60) : "";
        return !is_null($time) ? sprintf('%s%02d:%02d', ($time < 0 ? "- ":""),floor(abs($time)/60), abs($time)%60) : "";
    }

    /**
     * Get report data
     *
     * @param int $userId
     * @param $date1
     * @param $date2
     * @param int $report
     * @param int $format
     * @return array
     */
    public function getReportData(int $userId, $date1, $date2, int $report, int $format = 1): array {
        $res = $this->timesheetRepository->getReportData($userId, $date1, $date2, $report);

        // Format
        $outputFormat = array(
            1 => 'time',
            2 => 'minutes',
            3 => 'pcent',
            4 => 'number',
        );
        $format = $outputFormat[$format];

        if (count($res['data']) > 0) {
            $resTotal = max(1, intval($res['total']));
            $chart = array();    // array(name => pcent)
            $totalRow = array(); // total (sum) of the columns in a row
            foreach ($res['sumRows'] as $entry) {
                $chart[$entry['name']] = number_format(intval($entry['duration']) / $resTotal * 100, 1);
                switch ($format) {
                    case 'time':
                        $totalRow[$entry['name']] = $this->timeToString(intval($entry['duration']));
                        break;

                    case 'pcent':
                        $totalRow[$entry['name']] = number_format(intval($entry['duration']) / $resTotal * 100, 1);
                        break;

                    case 'number':
                        $totalRow[$entry['name']] = number_format(intval($entry['duration']) / $resTotal, 2);
                        break;

                    default:
                        // minutes
                        $totalRow[$entry['name']] = $entry['duration'];
                        break;
                }
            }
            arsort($chart);

            // sum of the columns
            $sumCols = array();
            foreach ($res['sumCols'] as $entry) {
                $sumCols[$entry['date']] = $entry['duration'];
            }

            // Grand total
            switch ($format) {
                case 'time':
                    $total = $this->timeToString($resTotal);
                    break;

                case 'pcent':
                    $total = number_format(100, 1);
                    break;

                case 'number':
                    $total = number_format(1, 2);
                    break;

                default:
                    // minutes
                    $total = $res['total'];
                    break;
            }

            // Reorganize the data
            $data = array();
            $cols = array();
            foreach ($res['data'] as $entry) {
                // Set data
                switch ($format) {
                    case 'time':
                        $data[$entry['name']][$entry['date']] = $this->timeToString(intval($entry['duration']));
                        break;

                    case 'pcent':
                        $data[$entry['name']][$entry['date']] = intval($sumCols[$entry['date']]) > 0 ? number_format(intval($entry['duration']) / intval($sumCols[$entry['date']]) * 100, 1) : number_format(0,1);
                        break;

                    case 'number':
                        $data[$entry['name']][$entry['date']] = intval($sumCols[$entry['date']]) > 0 ? number_format(intval($entry['duration']) / intval($sumCols[$entry['date']]), 2) : number_format(0,2);
                        break;

                    default:
                        // minutes
                        $data[$entry['name']][$entry['date']] = $entry['duration'];
                        break;
                }
                // Set col 'name'
                if (!in_array($entry['date'], $cols)) {
                    $cols[] = $entry['date'];
                }
            }
            ksort($data);

            // table head
            $tHead = array_merge(array("#"), $cols, array("Total"));

            // table body
            $tBody = array();
            foreach ($data as $key => $value) {
                $row = array();

                // Key
                $row[] = empty($key) ? "---" : $key;

                // Data
                foreach ($cols as $date) {
                    $row[] = $value[$date] ?? '-';
                }

                // Total
                $row[] = $totalRow[$key];

                $tBody[] = $row;
            }

            // table foot
            $tFoot = array(0 => "Total");
            foreach ($cols as $col) {
                switch ($format) {
                    case 'time':
                        $tFoot[] = $sumCols[$col] ? $this->timeToString(intval($sumCols[$col])) : '00:00';
                        break;

                    case 'pcent':
                        $tFoot[] = $sumCols[$col] ? number_format(100,1) : number_format(0,1);
                        break;

                    case 'number':
                        $tFoot[] = $sumCols[$col] ? number_format(1,2) : number_format(0,2);
                        break;

                    default:
                        // minutes
                        $tFoot[] = $sumCols[$col] ?? '0';
                        break;
                }
            }
            $tFoot[] = $total;


            return array(
                'pivot' => array(
                    'tHead' => $tHead,
                    'tBody' => $tBody,
                    'tFoot' => $tFoot,
                ),
                'chart' => $chart,
            );
        }

        return array();
    }


    /**
     * Create new Timesheet
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createTimesheet(array $data): string {
        $errorMsg = "";
        $dateFormat = $this->translations['dateFormats_datetime'];
        $rounding = $this->options['rounding'];

        $userId = intval($data['userId']);
        $activityId = intval($data['timesheet_edit_form_activity']);
        $projectId = intval($data['timesheet_edit_form_project']);
        $startDate = $this->validationHelper->sanitizeString($data['timesheet_edit_form_start_date']);

        $startTime = $this->validationHelper->sanitizeString($data['timesheet_edit_form_start_time']);
        $start = null;
        if (!empty($startDate) && !empty($startTime)) {
            $tmp = date_create_from_format($dateFormat, $startDate . " " . $startTime);

            if ($tmp instanceof \DateTimeInterface) {
                $start = $tmp;

                if ($rounding['active']) {
                    $start = $this->roundingHelper->roundDateTime(
                        $start,
                        $rounding['start']['minutes'],
                        $rounding['start']['mode']
                    );
                }
            }
        }

        $endTime = $this->validationHelper->sanitizeString($data['timesheet_edit_form_end_time']);
        $end = null;
        if (!empty($startDate) && !empty($endTime)) {
            $tmp = date_create_from_format($dateFormat, $startDate . " " . $endTime);

            if ($tmp instanceof \DateTimeInterface) {
                $end = $tmp;

                if ($rounding['active']) {
                    $end = $this->roundingHelper->roundDateTime(
                        $end,
                        $rounding['end']['minutes'],
                        $rounding['end']['mode']
                    );
                }

                $max = date_create_from_format($dateFormat, $startDate . " 23:59");

                if ($end > $max) {
                    $end = $max;
                }
            }
        }

        $duration = (!empty($start) && !empty($end)) ? (date_format($end,"U") - date_format($start,"U"))/60 : null;
        $comment = $this->validationHelper->sanitizeString($data['timesheet_edit_form_description']);
        $selectedTags = $data['timesheet_edit_form']['selectedTags'] ?? [];

        // Validate userId
        if ($userId === 0) {
            // Oops
            return $errorMsg;
        }

        // Validate Dates
        if (!$this->validationHelper->validateDate($start)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_timesheet_start'], $this->translations['form_error_format']) . "\n";
        }
        if (!$this->validationHelper->validateDate($end, true)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_timesheet_end'], $this->translations['form_error_format']) . "\n";
        }
        if (!empty($start) && !empty($end)) {
            if (date_format($end,"U") < date_format($start,"U")) {
                $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_timesheet_start'], $this->translations['form_error_format']) . "\n";
            }
        }

        // Validate project
        if ($projectId === 0) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_project'], $this->translations['form_error_format']) . "\n";
        }

        // Validate activity
        if ($activityId === 0) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_activity'], $this->translations['form_error_format']) . "\n";
        }
        else {
            $activity = $this->activityRepository->find($activityId);
            if (!$activity) {
                $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_activity'], $this->translations['form_error_format']) . "\n";
            }
            elseif ($activity->getProjectId() != null && $activity->getProjectId() != $projectId) {
                $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_activity'], $this->translations['form_error_format']) . "\n";
            }
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        if (!$start instanceof \DateTimeInterface) {
            return $this->translations['error_occurred'];
        }

        $timesheet = new Timesheet();
        $timesheet->setUserId($userId);
        $timesheet->setActivityId($activityId);
        $timesheet->setProjectId($projectId);
        $timesheet->setStart((!empty($start) ? date_format($start,"Y-m-d H:i") : null));
        $timesheet->setEnd((!empty($end) ? date_format($end,"Y-m-d H:i") : null));
        $timesheet->setDuration($duration);
        $timesheet->setComment($comment);
        $timesheet->setModifiedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        // Stop active timesheets
        $activeTimesheets = $this->timesheetRepository->findAllActiveRecordsByUserId($userId);
        $startYmdHi = $start->format('Y-m-d H:i');
        foreach ($activeTimesheets as $activeTimesheet) {
            $activeStart = date_create($activeTimesheet->getStart());

            if (!$activeStart instanceof \DateTimeInterface) {
                continue;
            }

            // Compare full timestamp
            if ($start < $activeStart) {
                continue;
            }

            $activeYmd = $activeStart->format('Y-m-d');
            if ($activeYmd < $start->format('Y-m-d')) {
                $activeTimesheetEnd = $activeYmd . " 23:59:00";
            } else {
                $activeTimesheetEnd = $startYmdHi;
            }

            $activeTimesheet->setEnd($activeTimesheetEnd);
            if(!$this->timesheetRepository->stopTimesheet($activeTimesheet)){
                return $this->translations['error_occurred'];
            }

            $this->logger->info(
                "[TimesheetService] Timesheet '".$activeTimesheet->getId()."' auto stopped",
                [
                    'id'    => $activeTimesheet->getId(),
                    'start' => $activeTimesheet->getStart(),
                    'end'   => $activeTimesheet->getEnd(),
                ]
            );
        }

        $lastInsertId = $this->timesheetRepository->insert($timesheet);

        if (!$lastInsertId) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[TimesheetService] Timesheet '".$lastInsertId."' created",
            [
                'id' => $lastInsertId,
            ]
        );

        if (count($selectedTags) > 0) {
            if (!$this->timesheetRepository->insertTags(intval($lastInsertId), $selectedTags)) {
                return $this->translations['error_occurred'];
            }

            $this->logger->info(
                "[TimesheetService] Timesheet '".$lastInsertId."': tags link created",
                [
                    'id'     => $lastInsertId,
                    'tagIds' => $selectedTags,
                ]
            );
        }

        return '';
    }

    /**
     * Update Timesheet
     *
     * @param Timesheet $timesheet
     * @param array $data
     * @return string $errorMsg
     */
    public function updateTimesheet(Timesheet $timesheet, array $data): string {
        $errorMsg = "";
        $dateFormat = $this->translations['dateFormats_datetime'];
        $rounding = $this->options['rounding'];

        $activityId = intval($data['timesheet_edit_form_activity']);
        $projectId = intval($data['timesheet_edit_form_project']);
        $startDate = $this->validationHelper->sanitizeString($data['timesheet_edit_form_start_date']);

        $startTime = $this->validationHelper->sanitizeString($data['timesheet_edit_form_start_time']);
        $start = null;
        if (!empty($startDate) && !empty($startTime)) {
            $tmp = date_create_from_format($dateFormat, $startDate . " " . $startTime);

            if ($tmp instanceof \DateTimeInterface) {
                $start = $tmp;

                if ($rounding['active']) {
                    $start = $this->roundingHelper->roundDateTime(
                        $start,
                        $rounding['start']['minutes'],
                        $rounding['start']['mode']
                    );
                }
            }
        }

        $endTime = $this->validationHelper->sanitizeString($data['timesheet_edit_form_end_time']);
        $end = null;
        if (!empty($startDate) && !empty($endTime)) {
            $tmp = date_create_from_format($dateFormat, $startDate . " " . $endTime);

            if ($tmp instanceof \DateTimeInterface) {
                $end = $tmp;

                if ($rounding['active']) {
                    $end = $this->roundingHelper->roundDateTime(
                        $end,
                        $rounding['end']['minutes'],
                        $rounding['end']['mode']
                    );
                }

                $max = date_create_from_format($dateFormat, $startDate . " 23:59");

                if ($end > $max) {
                    $end = $max;
                }
            }
        }

        $duration = (!empty($start) && !empty($end)) ? (date_format($end,"U") - date_format($start,"U"))/60 : null;
        $comment = $this->validationHelper->sanitizeString($data['timesheet_edit_form_description']);
        $selectedTags = $data['timesheet_edit_form']['selectedTags'] ?? [];

        // Validate Dates
        if (!$this->validationHelper->validateDate($start)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_timesheet_start'], $this->translations['form_error_format']) . "\n";
        }
        if (!$this->validationHelper->validateDate($end, true)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_timesheet_end'], $this->translations['form_error_format']) . "\n";
        }
        if (!empty($start) && !empty($end)) {
            if (date_format($end,"U") < date_format($start,"U")) {
                $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_timesheet_start'], $this->translations['form_error_format']) . "\n";
            }
        }

        // Validate project
        if ($projectId === 0) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_project'], $this->translations['form_error_format']) . "\n";
        }

        // Validate activity
        if ($activityId === 0) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_activity'], $this->translations['form_error_format']) . "\n";
        }
        else {
            $activity = $this->activityRepository->find($activityId);
            if (!$activity) {
                $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_activity'], $this->translations['form_error_format']) . "\n";
            }
            elseif ($activity->getProjectId() != null && $activity->getProjectId() != $projectId) {
                $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_activity'], $this->translations['form_error_format']) . "\n";
            }
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        if (!$start instanceof \DateTimeInterface) {
            return $this->translations['error_occurred'];
        }

        $timesheet->setActivityId($activityId);
        $timesheet->setProjectId($projectId);
        $timesheet->setStart((!empty($start) ? date_format($start,"Y-m-d H:i") : null));
        $timesheet->setEnd((!empty($end) ? date_format($end,"Y-m-d H:i") : null));
        $timesheet->setDuration($duration);
        $timesheet->setComment($comment);
        $timesheet->setModifiedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));


        if (!$this->timesheetRepository->updateTimesheet($timesheet)) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[TimesheetService] Timesheet '".$timesheet->getId()."' updated",
            [
                'id' => $timesheet->getId(),
            ]
        );

        if (!$this->timesheetRepository->updateTags($timesheet->getId(), $selectedTags)) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[TimesheetService] Timesheet '".$timesheet->getId()."': tags link updated",
            [
                'id'     => $timesheet->getId(),
                'tagIds' => $selectedTags,
            ]
        );

        return '';
    }

    /**
     * Restart Timesheet
     *
     * @param Timesheet $timesheet
     * @return string $errorMsg
     */
    public function restartTimesheet(Timesheet $timesheet): string {
        $rounding = $this->options['rounding'];
        $start = new \DateTime("now");
        if ($rounding['active']) $start = $this->roundingHelper->roundDateTime($start, $rounding['start']['minutes'], $rounding['start']['mode']);

        $ts = new Timesheet();
        $ts->setUserId($timesheet->getUserId());
        $ts->setActivityId($timesheet->getActivityId());
        $ts->setProjectId($timesheet->getProjectId());
        $ts->setStart(date_format($start,"Y-m-d H:i"));
        $ts->setEnd(null);
        $ts->setDuration(null);
        $ts->setComment($timesheet->getComment());
        $ts->setModifiedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        // Stop active timesheets
        $activeTimesheets = $this->timesheetRepository->findAllActiveRecordsByUserId($timesheet->getUserId());
        $startYmdHi = $start->format('Y-m-d H:i');
        foreach ($activeTimesheets as $activeTimesheet) {
            $activeStart = date_create($activeTimesheet->getStart());

            if (!$activeStart instanceof \DateTimeInterface) {
                continue;
            }

            // Compare full timestamp
            if ($start < $activeStart) {
                continue;
            }

            $activeYmd = $activeStart->format('Y-m-d');
            if ($activeYmd < $start->format('Y-m-d')) {
                $activeTimesheetEnd = $activeYmd . " 23:59:00";
            } else {
                $activeTimesheetEnd = $startYmdHi;
            }

            $activeTimesheet->setEnd($activeTimesheetEnd);
            if(!$this->timesheetRepository->stopTimesheet($activeTimesheet)){
                return $this->translations['error_occurred'];
            }

            $this->logger->info(
                "[TimesheetService] Timesheet '".$activeTimesheet->getId()."' auto stopped",
                [
                    'id'    => $activeTimesheet->getId(),
                    'start' => $activeTimesheet->getStart(),
                    'end'   => $activeTimesheet->getEnd(),
                ]
            );
        }

        $lastInsertId = $this->timesheetRepository->insert($ts);

        if (!$lastInsertId) {
            return $this->translations['error_occurred'];
        }
        $this->logger->info(
            "[TimesheetService] Timesheet '".$lastInsertId."' created",
            [
                'id' => $lastInsertId,
            ]
        );

        $tagIds = $this->tagRepository->findAllTagIdsByTimesheetId($timesheet->getId());
        if (count($tagIds) > 0) {
            if (!$this->timesheetRepository->insertTags(intval($lastInsertId), $tagIds)) {
                return $this->translations['error_occurred'];
            }
            $this->logger->info(
                "[TimesheetService] Timesheet '".$lastInsertId."': tags link created",
                [
                    'id'     => $lastInsertId,
                    'tagIds' => $tagIds,
                ]
            );
        }

        return '';
    }

    /**
     * Stop Timesheet
     *
     * @param Timesheet $timesheet
     * @return string $errorMsg
     */
    public function stopTimesheet(Timesheet $timesheet): string {
        $rounding = $this->options['rounding'];
        $end = $timesheet->getEnd() ? date_create($timesheet->getEnd()) : new \DateTime();
        if ($rounding['active']) $end = $this->roundingHelper->roundDateTime($end, $rounding['end']['minutes'], $rounding['end']['mode']);

        $max = date_create(date("Y-m-d", strtotime($timesheet->getStart())) . " 23:59:00");
        if ($end > $max) {
            $end = $max;
        }

        $timesheet->setEnd(date_format($end, "Y-m-d H:i"));
        if(!$this->timesheetRepository->stopTimesheet($timesheet)) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[TimesheetService] Timesheet '".$timesheet->getId()."' stopped",
            [
                'id' => $timesheet->getId(),
            ]
        );

        return '';
    }

    /**
     * Delete Timesheet
     *
     * @param Timesheet $timesheet
     * @return string $errorMsg
     */
    public function deleteTimesheet(Timesheet $timesheet): string {
        $errorMsg = "";

        if (!$this->timesheetRepository->deleteTimesheet($timesheet)) {
            return $this->translations['form_error_delete_record'];
        }

        $this->logger->info(
            "[TimesheetService] Timesheet '".$timesheet->getId()."' deleted",
            [
                'id' => $timesheet->getId(),
            ]
        );

        return '';

    }

}
