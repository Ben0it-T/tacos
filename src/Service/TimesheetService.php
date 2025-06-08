<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Timesheet;
use App\Helper\RoundingHelper;
use App\Helper\ValidationHelper;
use App\Repository\ActivityRepository;
use App\Repository\TagRepository;
use App\Repository\TimesheetRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class TimesheetService
{
    private $container;
    private $activityRepository;
    private $tagRepository;
    private $timesheetRepository;
    private $roundingHelper;
    private $validationHelper;
    private $logger;

    public function __construct(ContainerInterface $container, ActivityRepository $activityRepository, TagRepository $tagRepository, TimesheetRepository $timesheetRepository, RoundingHelper $roundingHelper, ValidationHelper $validationHelper, LoggerInterface $logger) {
        $this->container = $container;
        $this->activityRepository = $activityRepository;
        $this->tagRepository = $tagRepository;
        $this->timesheetRepository = $timesheetRepository;
        $this->roundingHelper = $roundingHelper;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
    }

    /**
     * Find Timesheet
     *
     * @param int $id
     * @return Timesheet or false
     */
    public function findTimesheet(int $id) {
        return $this->timesheetRepository->find($id);
    }

    /**
     * Find Timesheet
     *
     * @param int $id
     * @return Timesheet or false
     */
    public function findTimesheetByIdAndUserId(int $id, int $userId) {
        return $this->timesheetRepository->findOneByIdAndUserId($id, $userId);
    }

    /**
     * Find Active Timesheet by user Id
     *
     * @param int $userId
     * @return Timesheet or false
     */
    public function findOneActiveTimesheetByUserId(int $userId) {
        return $this->timesheetRepository->findOneActiveTimesheetByUserId($userId);
    }



    /**
     * Find all Timesheets by user Id
     *
     * @param int $userId
     * @return array of Timesheet
     */
    public function findAllTimesheetByUserId(int $userId) {
        return $this->timesheetRepository->findAllTimesheetByUserId($userId);
    }

    /**
     * Find all Timesheets by user Id and date
     *
     * @param int $userId
     * @param $date
     * @return array of Timesheet
     */
    public function findAllTimesheetByUserIdAndStart(int $userId, $date) {
        return $this->timesheetRepository->findAllTimesheetByUserIdAndStart($userId, $date);
    }

    /**
     * Find all Timesheets by user Id between date
     *
     * @param int $userId
     * @param $date1
     * @param $date2
     * @return array of Timesheet
     */
    public function findAllTimesheetByUserIdBetween(int $userId, $date1, $date2) {
        return $this->timesheetRepository->findAllTimesheetByUserIdBetween($userId, $date1, $date2);
    }

    /**
     * Find all Timesheets by user and Filters
     *
     * @param int $userId
     * @param $date1
     * @param $date2
     * @param $projectIds
     * @param $activityIds
     * @param $tagIds
     * @return array of Timesheet
     */
    public function findAllTimesheetByUserIdAndFilters(int $userId, $date1, $date2, $projectIds = array(), $activityIds = array(), $tagIds = array()) {
        return $this->timesheetRepository->findAllTimesheetByUserIdAndFilters($userId, $date1, $date2, $projectIds, $activityIds, $tagIds);
    }

    /**
     * Find all Timesheets by users and Filters
     *
     * @param array $usersIds
     * @param $date1
     * @param $date2
     * @param $projectIds
     * @param $activityIds
     * @param $tagIds
     * @return array of Timesheet
     */
    public function findAllTimesheetsByUsersIdAndFilters(array $usersIds, $date1, $date2, $projectIds = array(), $activityIds = array(), $tagIds = array()) {
        return $this->timesheetRepository->findAllTimesheetsByUsersIdAndFilters($usersIds, $date1, $date2, $projectIds, $activityIds, $tagIds);
    }

    /**
     * Find all active timesheets by user Id
     *
     * @param int $userId
     * @return array of Timesheet
     */
    public function findAllActiveTimesheetByUserId(int $userId) {
        return $this->timesheetRepository->findAllActiveTimesheetByUserId($userId);
    }

    /**
     * Get number of active records
     *
     * @param int $userId
     * @return int
     */
    public function getNbOfActiveRecordsByUserId(int $userId) {
        return $this->timesheetRepository->getNbOfActiveRecordsByUserId($userId);
    }

    /**
     * Get working hours by user Id
     *
     * @param string $timePeriod
     * @return int
     */
    public function getWorkingHoursByTimePeriodAndUserId (string $timePeriod, int $userId) {
        return $this->timesheetRepository->getWorkingHoursByTimePeriodAndUserId($timePeriod, $userId);
    }

    /**
     * Convert time to H:i
     *
     * @param ?int $time
     * @return string $timeString
     */
    public function timeToString(?int $time) {
        //return !is_null($time) ? sprintf('%02d:%02d', floor($time/60), $time%60) : "";
        return !is_null($time) ? sprintf('%s%02d:%02d', ($time < 0 ? "- ":""),floor(abs($time)/60), abs($time)%60) : "";
    }

    /**
     * Get report
     *
     * @param int $userId
     * @param $date1
     * @param $date2
     * @param int $report
     * @param int $format
     * @return array
     */
    public function getReportData(int $userId, $date1, $date2, int $report, int $format = 1) {
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

            $chart = array();    // array(name => pcent)
            $totalRow = array(); // total (sum) of the columns in a row
            foreach ($res['sumRows'] as $entry) {
                $chart[$entry['name']] = number_format(intval($entry['duration']) / intval($res['total']) * 100, 1);
                switch ($format) {
                    case 'time':
                        $totalRow[$entry['name']] = $this->timeToString(intval($entry['duration']));
                        break;

                    case 'pcent':
                        $totalRow[$entry['name']] = number_format(intval($entry['duration']) / intval($res['total']) * 100, 1);
                        break;

                    case 'number':
                        $totalRow[$entry['name']] = number_format(intval($entry['duration']) / intval($res['total']), 2);
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
                    $total = $this->timeToString(intval($res['total']));
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
                        $data[$entry['name']][$entry['date']] = number_format(intval($entry['duration']) / intval($sumCols[$entry['date']]) * 100, 1);
                        break;

                    case 'number':
                        $data[$entry['name']][$entry['date']] = number_format(intval($entry['duration']) / intval($sumCols[$entry['date']]), 2);
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
                        $tFoot[] = $sumCols[$date] ? $this->timeToString(intval($sumCols[$col])) : '00:00';
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
    public function createTimesheet($data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";
        $dateFormat = $translations['dateFormats_datetime'];
        $rounding = $this->container->get('settings')['timesheet']['rounding'];

        $userId = intval($data['userId']);
        $activityId = intval($data['timesheet_edit_form_activity']);
        $projectId = intval($data['timesheet_edit_form_project']);
        $startDate = $this->validationHelper->sanitizeString($data['timesheet_edit_form_start_date']);

        $startTime = $this->validationHelper->sanitizeString($data['timesheet_edit_form_start_time']);
        $start = NULL;
        if (!empty($startDate) && !empty($startTime)) {
            $start = date_create_from_format($dateFormat,$startDate." ".$startTime);
            if ($rounding['active']) $start = $this->roundingHelper->roundDateTime($start, $rounding['start']['minutes'], $rounding['start']['mode']);
        }

        $endTime = $this->validationHelper->sanitizeString($data['timesheet_edit_form_end_time']);
        $end = NULL;
        if (!empty($startDate) && !empty($endTime)) {
            $end = date_create_from_format($dateFormat,$startDate." ".$endTime);
            if ($rounding['active']) $end = $this->roundingHelper->roundDateTime($end, $rounding['end']['minutes'], $rounding['end']['mode']);
            $max = date_create_from_format($dateFormat,$startDate." 23:59");
            if ($end > $max) {
                $end = $max;
            }
        }

        $duration = (!empty($start) && !empty($end)) ? (date_format($end,"U") - date_format($start,"U"))/60 : NULL;
        $comment = $this->validationHelper->sanitizeString($data['timesheet_edit_form_description']);
        $selectedTags = isset($data['timesheet_edit_form']['selectedTags']) ? $data['timesheet_edit_form']['selectedTags'] : array();

        // Validate userId
        if ($userId === 0) {
            $validation = false;
            // Oops
        }

        // Validate Dates
        if (!$this->validationHelper->validateDate($start)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_timesheet_start'], $translations['form_error_format']) . "\n";
        }
        if (!$this->validationHelper->validateDate($end, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_timesheet_end'], $translations['form_error_format']) . "\n";
        }
        if (!empty($start) && !empty($end)) {
            if (date_format($end,"U") < date_format($start,"U")) {
                $validation = false;
                $errorMsg .= str_replace("%fieldName%", $translations['form_label_timesheet_start'], $translations['form_error_format']) . "\n";
            }
        }


        // Validate project
        if ($projectId === 0) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project'], $translations['form_error_format']) . "\n";
        }

        // Validate activity
        if ($activityId === 0) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_activity'], $translations['form_error_format']) . "\n";
        }
        else {
            $activity = $this->activityRepository->find($activityId);
            if ($activity->getProjectId() != NULL && $activity->getProjectId() != $projectId) {
                $validation = false;
                $errorMsg .= str_replace("%fieldName%", $translations['form_label_activity'], $translations['form_error_format']) . "\n";
            }
        }

        if ($validation) {
            $timesheet = new Timesheet();
            $timesheet->setUserId($userId);
            $timesheet->setActivityId($activityId);
            $timesheet->setProjectId($projectId);
            $timesheet->setStart((!empty($start) ? date_format($start,"Y-m-d H:i") : NULL));
            $timesheet->setEnd((!empty($end) ? date_format($end,"Y-m-d H:i") : NULL));
            $timesheet->setDuration($duration);
            $timesheet->setComment($comment);
            $timesheet->setModifiedAt(date("Y-m-d H:i:s"));

            // Stop active timesheets
            $activeTimesheets = $this->timesheetRepository->findAllActiveTimesheetByUserId($userId);
            foreach ($activeTimesheets as $activeTimesheet) {
                $activeTimesheetStart = date("Y-m-d", strtotime($activeTimesheet->getStart()));
                $activeTimesheetEnd = $activeTimesheetStart < date_format($start,"Y-m-d") ? $activeTimesheetStart." 23:59:00" : date_format($start,"Y-m-d H:i");
                $activeTimesheet->setEnd($activeTimesheetEnd);
                $this->timesheetRepository->stopTimesheet($activeTimesheet);
            }

            $lastInsertId = $this->timesheetRepository->insert($timesheet);
            $this->logger->info("TimesheetService - Timesheet '" . $lastInsertId . "' created.");
            if (count($selectedTags) > 0) {
                $this->timesheetRepository->insertTags(intval($lastInsertId), $selectedTags);
                $this->logger->info("TimesheetService - Timesheet '" . $lastInsertId . "': tags created.");
            }


        }

        return $errorMsg;
    }

    /**
     * Update Timesheet
     *
     * @param Timesheet $timesheet
     * @param array $data
     * @return string $errorMsg
     */
    public function updateTimesheet($timesheet, $data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";
        $dateFormat = $translations['dateFormats_datetime'];
        $rounding = $this->container->get('settings')['timesheet']['rounding'];

        $activityId = intval($data['timesheet_edit_form_activity']);
        $projectId = intval($data['timesheet_edit_form_project']);
        $startDate = $this->validationHelper->sanitizeString($data['timesheet_edit_form_start_date']);
        $startTime = $this->validationHelper->sanitizeString($data['timesheet_edit_form_start_time']);
        $start = NULL;
        if (!empty($startDate) && !empty($startTime)) {
            $start = date_create_from_format($dateFormat,$startDate." ".$startTime);
            if ($rounding['active']) $start = $this->roundingHelper->roundDateTime($start, $rounding['start']['minutes'], $rounding['start']['mode']);
        }
        $endTime = $this->validationHelper->sanitizeString($data['timesheet_edit_form_end_time']);
        $end = NULL;
        if (!empty($startDate) && !empty($endTime)) {
            $end = date_create_from_format($dateFormat,$startDate." ".$endTime);
            if ($rounding['active']) $end = $this->roundingHelper->roundDateTime($end, $rounding['end']['minutes'], $rounding['end']['mode']);
            $max = date_create_from_format($dateFormat,$startDate." 23:59");
            if ($end > $max) {
                $end = $max;
            }
        }

        $duration = (!empty($start) && !empty($end)) ? (date_format($end,"U") - date_format($start,"U"))/60 : NULL;
        $comment = $this->validationHelper->sanitizeString($data['timesheet_edit_form_description']);
        $selectedTags = isset($data['timesheet_edit_form']['selectedTags']) ? $data['timesheet_edit_form']['selectedTags'] : array();

        // Validate Dates
        if (!$this->validationHelper->validateDate($start)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_timesheet_start'], $translations['form_error_format']) . "\n";
        }
        if (!$this->validationHelper->validateDate($end, true)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_timesheet_end'], $translations['form_error_format']) . "\n";
        }
        if (!empty($start) && !empty($end)) {
            if (date_format($end,"U") < date_format($start,"U")) {
                $validation = false;
                $errorMsg .= str_replace("%fieldName%", $translations['form_label_timesheet_start'], $translations['form_error_format']) . "\n";
            }
        }

        // Validate project
        if ($projectId === 0) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_project'], $translations['form_error_format']) . "\n";
        }

        // Validate activity
        if ($activityId === 0) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_activity'], $translations['form_error_format']) . "\n";
        }
        else {
            $activity = $this->activityRepository->find($activityId);
            if ($activity->getProjectId() != NULL && $activity->getProjectId() != $projectId) {
                $validation = false;
                $errorMsg .= str_replace("%fieldName%", $translations['form_label_activity'], $translations['form_error_format']) . "\n";
            }
        }

        if ($validation) {
            $timesheet->setActivityId($activityId);
            $timesheet->setProjectId($projectId);
            $timesheet->setStart((!empty($start) ? date_format($start,"Y-m-d H:i") : NULL));
            $timesheet->setEnd((!empty($end) ? date_format($end,"Y-m-d H:i") : NULL));
            $timesheet->setDuration($duration);
            $timesheet->setComment($comment);
            $timesheet->setModifiedAt(date("Y-m-d H:i:s"));


            $this->timesheetRepository->updateTimesheet($timesheet);
            $this->logger->info("TimesheetService - Timesheet '" . $timesheet->getId() . "' updated.");

            $this->timesheetRepository->updateTags($timesheet->getId(), $selectedTags);
            $this->logger->info("TimesheetService - Timesheet '" . $timesheet->getId() . "': tags updated.");
        }

        return $errorMsg;
    }

    /**
     * Restart Timesheet
     *
     * @param Timesheet $timesheet
     */
    public function restartTimesheet($timesheet) {
        $rounding = $this->container->get('settings')['timesheet']['rounding'];
        $start = new \DateTime("now");
        if ($rounding['active']) $start = $this->roundingHelper->roundDateTime($start, $rounding['start']['minutes'], $rounding['start']['mode']);
        $ts = new Timesheet();
        $ts->setUserId($timesheet->getUserId());
        $ts->setActivityId($timesheet->getActivityId());
        $ts->setProjectId($timesheet->getProjectId());
        $ts->setStart(date_format($start,"Y-m-d H:i"));
        $ts->setEnd(NULL);
        $ts->setDuration(NULL);
        $ts->setComment($timesheet->getComment());
        $ts->setModifiedAt(date("Y-m-d H:i:s"));

        // Stop active timesheets
        $activeTimesheets = $this->timesheetRepository->findAllActiveTimesheetByUserId($timesheet->getUserId());
        foreach ($activeTimesheets as $activeTimesheet) {
            $activeTimesheetStart = date("Y-m-d", strtotime($activeTimesheet->getStart()));
            $activeTimesheetEnd = $activeTimesheetStart < date_format($start,"Y-m-d") ? $activeTimesheetStart." 23:59:00" : date_format($start,"Y-m-d H:i");
            $activeTimesheet->setEnd($activeTimesheetEnd);
            $this->timesheetRepository->stopTimesheet($activeTimesheet);
        }

        $lastInsertId = $this->timesheetRepository->insert($ts);
        $this->logger->info("TimesheetService - Timesheet '" . $lastInsertId . "' created.");

        $tagIds = $this->tagRepository->findAllTagIdsByTimesheetId($timesheet->getId());
        if (count($tagIds) > 0) {
            $this->timesheetRepository->insertTags(intval($lastInsertId), $tagIds);
            $this->logger->info("TimesheetService - Timesheet '" . $lastInsertId . "': tags created.");
        }
    }

    /**
     * Stop Timesheet
     *
     * @param Timesheet $timesheet
     */
    public function stopTimesheet($timesheet) {
        $rounding = $this->container->get('settings')['timesheet']['rounding'];
        $end = date_create($timesheet->getEnd());
        if ($rounding['active']) $end = $this->roundingHelper->roundDateTime($end, $rounding['end']['minutes'], $rounding['end']['mode']);
        $max = date_create(date("Y-m-d", strtotime($timesheet->getStart())) . " 23:59:00");
        if ($end > $max) {
            $end = $max;
        }

        $timesheet->setEnd(date_format($end, "Y-m-d H:i"));
        $this->timesheetRepository->stopTimesheet($timesheet);
        $this->logger->info("TimesheetService - Timesheet '" . $timesheet->getId() . "' stopped.");
    }

    /**
     * Delete Timesheet
     *
     * @param Timesheet $timesheet
     * @return string $errorMsg
     */
    public function deleteTimesheet($timesheet) {
        $translations = $this->container->get('translations');
        $errorMsg = "";
        if ($this->timesheetRepository->deleteTimesheet($timesheet)) {
            $this->logger->info("TimesheetService - Timesheet '" . $timesheet->getId() . "' deleted.");
        }
        else {
            $errorMsg = $translations['form_error_delete_record'];
        }

        return $errorMsg;
    }

}
