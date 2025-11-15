<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Timesheet;
use App\Helper\SqlHelper;
use PDO;

final class TimesheetRepository
{
    private $pdo;
    private $sqlHelper;

    public function __construct(PDO $pdo, SqlHelper $sqlHelper) {
        $this->pdo = $pdo;
        $this->sqlHelper = $sqlHelper;
    }

    /**
     * Find Timesheet by id
     *
     * @param int $id
     * @return Timesheet or false
     */
    public function find(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_timesheet` WHERE `tacos_timesheet`.`id` = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row) {
            return $this->buildEntity($row);
        }
        else {
            return false;
        }
    }

    /**
     * Find Timesheet by id and user Id
     *
     * @param int $id
     * @param int $userId
     * @return Timesheet or false
     */
    public function findOneByIdAndUserId(int $id, int $userId) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_timesheet` WHERE `tacos_timesheet`.`id` = :id AND `tacos_timesheet`.`user_id` = :userId');
        $stmt->execute([
            'id' => $id,
            'userId' => $userId,
        ]);
        $row = $stmt->fetch();

        if ($row) {
            return $this->buildEntity($row);
        }
        else {
            return false;
        }
    }

    /**
     * Find the last active Timesheet by user ID
     *
     * @param int $userId
     * @return Timesheet or false
     */
    public function findOneActiveTimesheetByUserId(int $userId) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_timesheet` WHERE `tacos_timesheet`.`user_id` = :userId AND `tacos_timesheet`.`end` is NULL ORDER BY `tacos_timesheet`.`start` DESC, `tacos_timesheet`.`id` DESC LIMIT 1');
        $stmt->execute([
            'userId' => $userId,
        ]);
        $row = $stmt->fetch();

        if ($row) {
            return $this->buildEntity($row);
        }
        else {
            return false;
        }
    }

    /**
     * Find all Timesheets
     *
     * @return array of Timesheet
     */
    public function findAll() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_timesheet` ORDER BY `tacos_timesheet`.`start` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $timesheet = array();
        foreach ($rows as $row) {
            $timesheet[$row['id']] = $this->buildEntity($row);
        }

        return $timesheet;
    }

    /**
     * Find all Timesheets with filters
     *
     * @param array $dates
     * @param array $usersIds
     * @param array $projectIds
     * @param array $activityIds
     * @param array $tagIds
     *
     * @return array of Timesheet with User, Projet, Activity and Tags
     */
    public function findTimesheetsWithUserAndProjectAndActivityAndTagsByCriteria(array $dates, array $usersIds, array $projectIds, array $activityIds, array $tagIds) {
        $params = array();
        $where = array();

        // dates
        if (count($dates) > 0) {
            $params['date1'] = $dates[0];
            $params['date2'] = $dates[1];
            $where[] = "(`tacos_timesheet`.`start` >= :date1 AND `tacos_timesheet`.`start` < DATE_ADD(:date2, INTERVAL 1 DAY))";
        }

        // usersIds
        [$clause, $pdoParams] = $this->sqlHelper->buildInClause($usersIds, 'usersId', '`tacos_timesheet`.`user_id`');
        if ($clause !== '') {
            $where[] = $clause;
            $params = array_merge($params, $pdoParams);
        }

        // projectIds
        [$clause, $pdoParams] = $this->sqlHelper->buildInClause($projectIds, 'projectId', '`tacos_timesheet`.`project_id`');
        if ($clause !== '') {
            $where[] = $clause;
            $params = array_merge($params, $pdoParams);
        }

        // activityIds
        [$clause, $pdoParams] = $this->sqlHelper->buildInClause($activityIds, 'activityId', '`tacos_timesheet`.`activity_id`');
        if ($clause !== '') {
            $where[] = $clause;
            $params = array_merge($params, $pdoParams);
        }

        // tagIds
        [$clause, $pdoParams] = $this->sqlHelper->buildInClause($tagIds, 'tagId', '`tacos_timesheet_tags`.`tag_id`');
        if ($clause !== '') {
            $where[] = $clause;
            $params = array_merge($params, $pdoParams);
        }

        $sql  = 'SELECT `tacos_timesheet`.*, ';
        $sql .= '`tacos_users`.`name` as userName, ';
        $sql .= '`tacos_projects`.`name` as projectName, `tacos_projects`.`color` as projectColor, `tacos_projects`.`number` as projectNumber, ';
        $sql .= '`tacos_activities`.`name` as activityName, `tacos_activities`.`color` as activityColor, `tacos_activities`.`number` as activityNumber, ';
        $sql .= 'GROUP_CONCAT(DISTINCT `tacos_tags`.`id`) as tagIds ';
        $sql .= 'FROM `tacos_timesheet` ';
        $sql .= 'LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_timesheet`.`user_id` ';
        $sql .= 'LEFT JOIN `tacos_projects` ON `tacos_projects`.`id` = `tacos_timesheet`.`project_id` ';
        $sql .= 'LEFT JOIN `tacos_activities` ON `tacos_activities`.`id` = `tacos_timesheet`.`activity_id` ';
        $sql .= 'LEFT JOIN `tacos_timesheet_tags` ON `tacos_timesheet_tags`.`timesheet_id` = `tacos_timesheet`.`id` ';
        $sql .= 'LEFT JOIN `tacos_tags` ON `tacos_tags`.`id` = `tacos_timesheet_tags`.`tag_id` ';

        if (!empty($where)) {
            $sql .= 'WHERE ' . implode(' AND ', $where) . ' ';
        }

        $sql .= "GROUP BY `tacos_timesheet`.`id` ";
        $sql .= "ORDER BY `tacos_timesheet`.`start` DESC, `tacos_timesheet`.`id` DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Find all active Timesheets by user Id
     *
     * @param int $userId
     * @return array of Timesheets
     */
    public function findAllActiveTimesheetByUserId(int $userId) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_timesheet` WHERE `tacos_timesheet`.`user_id` = :userId AND `tacos_timesheet`.`end` is null ORDER BY `tacos_timesheet`.`start` DESC, `tacos_timesheet`.`id` DESC');
        $stmt->execute([
            'userId' => $userId,
        ]);
        $rows = $stmt->fetchAll();

        $timesheet = array();
        foreach ($rows as $row) {
            $timesheet[$row['id']] = $this->buildEntity($row);
        }

        return $timesheet;
    }

    /**
     * Get number of active records by user Id
     *
     * @param int $userId
     * @return int
     */
    public function getNbOfActiveRecordsByUserId(int $userId) {
        $stmt = $this->pdo->prepare('SELECT count(*) as cnt FROM `tacos_timesheet` WHERE `tacos_timesheet`.`user_id` = :userId AND `tacos_timesheet`.`end` is null');
        $stmt->execute([
            'userId' => $userId,
        ]);

        return $stmt->fetchColumn();
    }

    /**
     * Get working hours by user Id
     *
     * @param string $timePeriod
     * @return int
     */
    public function getWorkingHoursByTimePeriodAndUserId(string $timePeriod, int $userId) {
        switch ($timePeriod) {
            case 'week':
                $condition = "YEARWEEK(`tacos_timesheet`.`start`, 1) = YEARWEEK(CURDATE(), 1)";
                break;

            case 'lastweek':
                $condition = "YEARWEEK(`tacos_timesheet`.`start`, 1) = YEARWEEK(CURDATE(), 1) - 1";
                break;

            case 'month':
                $condition = "DATE_FORMAT(`tacos_timesheet`.`start`, '%Y%m') = DATE_FORMAT(CURDATE(), '%Y%m')";
                break;

             case 'lastmonth':
                $condition = "DATE_FORMAT(`tacos_timesheet`.`start`, '%Y%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y%m')";
                break;

            default:
                // today
                $condition = "DATE(`tacos_timesheet`.`start`) = CURDATE()";
                break;
        }

        $stmt = $this->pdo->prepare('SELECT SUM(duration) as duration FROM `tacos_timesheet` WHERE `tacos_timesheet`.`user_id` = :userId AND '.$condition.' AND `tacos_timesheet`.`end` is not null');
        $stmt->execute([
            'userId' => $userId,
        ]);

        return $stmt->fetchColumn();
    }

    /**
     * Get report data
     *
     * @param int $userId
     * @param $date1
     * @param $date2
     * @param int $report
     * @return array
     */
    public function getReportData(int $userId, $date1, $date2, int $report) {

        switch ($report) {
            case 1:
                // project.name
                $field = "`tacos_projects`.`name`";
                $group = "`tacos_projects`.`id`";
                $join  = "LEFT JOIN `tacos_projects` ON `tacos_projects`.`id` = `tacos_timesheet`.`project_id` ";
                break;

            case 2:
                // project.number
                $field = "`tacos_projects`.`number`";
                $group = "`tacos_projects`.`number`";
                $join  = "LEFT JOIN `tacos_projects` ON `tacos_projects`.`id` = `tacos_timesheet`.`project_id` ";
                break;

            case 3:
                // activities.id
                $field = "`tacos_activities`.`name`";
                $group = "`tacos_activities`.`id`";
                $join  = "LEFT JOIN `tacos_activities` ON `tacos_activities`.`id` = `tacos_timesheet`.`activity_id` ";
                break;

            case 4:
                // activities.number
                $field = "`tacos_activities`.`number`";
                $group = "`tacos_activities`.`number`";
                $join  = "LEFT JOIN `tacos_activities` ON `tacos_activities`.`id` = `tacos_timesheet`.`activity_id` ";
                break;

            case 5:
                // customers.id
                $field = "`tacos_customers`.`name` ";
                $group = "`tacos_customers`.`id`";
                $join  = "LEFT JOIN `tacos_projects` ON `tacos_projects`.`id` = `tacos_timesheet`.`project_id` ";
                $join .= "LEFT JOIN `tacos_customers` ON `tacos_customers`.`id` = `tacos_projects`.`customer_id` ";
                break;

            case 6:
                // customers.number
                $field = "`tacos_customers`.`number` ";
                $group = "`tacos_customers`.`number`";
                $join  = "LEFT JOIN `tacos_projects` ON `tacos_projects`.`id` = `tacos_timesheet`.`project_id` ";
                $join .= "LEFT JOIN `tacos_customers` ON `tacos_customers`.`id` = `tacos_projects`.`customer_id` ";
                break;

            default:
                // project.name
                $field = "`tacos_projects`.`name`";
                $group = "`tacos_projects`.`id`";
                $join  = "LEFT JOIN `tacos_projects` ON `tacos_projects`.`id` = `tacos_timesheet`.`project_id` ";

        }

        $params = array(
            'userId' => $userId,
            'date1' => $date1,
            'date2' => $date2,
        );

        // Data
        $sql  = "SELECT " . $field . " as `name`, DATE_FORMAT(`tacos_timesheet`.`start`, '%Y-%m-%d') as `date`, SUM(`tacos_timesheet`.duration) as `duration` ";
        $sql .= "FROM `tacos_timesheet` ";
        $sql .= $join;
        $sql .= "WHERE `tacos_timesheet`.`user_id` = :userId ";
        $sql .= "AND (`tacos_timesheet`.`start` >= :date1 AND `tacos_timesheet`.`start` < DATE_ADD(:date2, INTERVAL 1 DAY)) ";
        $sql .= "GROUP BY " . $group . ", `date` ";
        $sql .= "ORDER BY `date` ASC , `name` ASC ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res['data'] = $stmt->fetchAll();

        // Sum of Rows (fields)
        $sql  = "SELECT " . $field . " as `name`, SUM(`tacos_timesheet`.duration) as `duration` ";
        $sql .= "FROM `tacos_timesheet` ";
        $sql .= $join;
        $sql .= "WHERE `tacos_timesheet`.`user_id` = :userId ";
        $sql .= "AND (`tacos_timesheet`.`start` >= :date1 AND `tacos_timesheet`.`start` < DATE_ADD(:date2, INTERVAL 1 DAY)) ";
        $sql .= "GROUP BY " . $group . " ";
        $sql .= "ORDER BY `name` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res['sumRows'] = $stmt->fetchAll();

        // Sum of Cols (dates)
        $sql  = "SELECT DATE_FORMAT(`tacos_timesheet`.`start`, '%Y-%m-%d') as `date`, SUM(`tacos_timesheet`.duration) as `duration` ";
        $sql .= "FROM `tacos_timesheet` ";
        $sql .= "WHERE `tacos_timesheet`.`user_id` = :userId ";
        $sql .= "AND (`tacos_timesheet`.`start` >= :date1 AND `tacos_timesheet`.`start` < DATE_ADD(:date2, INTERVAL 1 DAY)) ";
        $sql .= "GROUP BY `date` ";
        $sql .= "ORDER BY `date` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res['sumCols'] = $stmt->fetchAll();

        // Total
        $sql  = "SELECT SUM(`tacos_timesheet`.duration) as `duration` ";
        $sql .= "FROM `tacos_timesheet` ";
        $sql .= "WHERE `tacos_timesheet`.`user_id` = :userId ";
        $sql .= "AND (`tacos_timesheet`.`start` >= :date1 AND `tacos_timesheet`.`start` < DATE_ADD(:date2, INTERVAL 1 DAY)) ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res['total'] = $stmt->fetchColumn();

        return $res;
    }

    /**
     * Stop Timesheet
     *
     * @param int $timesheet
     * @return bool
     */
    public function stopTimesheet(Timesheet $timesheet) {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_timesheet` SET `tacos_timesheet`.`end` = :end1, `tacos_timesheet`.`duration` = CAST(TIME_TO_SEC(TIMEDIFF(:end2, `tacos_timesheet`.`start`))/60 AS UNSIGNED) , `tacos_timesheet`.`modified_at` = :modifiedAt WHERE `tacos_timesheet`.`id` = :id AND `tacos_timesheet`.`end` is NULL');
            $res = $stmt->execute([
                'end1' => $timesheet->getEnd(),
                'end2' => $timesheet->getEnd(),
                'modifiedAt' => date("Y-m-d H:i:s"),
                'id' => $timesheet->getId(),
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * Insert Timesheet
     *
     * @param Timesheet $timesheet
     * @return lastInsertId or false
     */
    public function insert(Timesheet $timesheet) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_timesheet` (`id`, `user_id`, `activity_id`, `project_id`, `start`, `end`, `duration`, `comment`, `modified_at`) VALUES (NULL, :userId, :activityId, :projectId, :start, :end, :duration, :comment, :modifiedAt)');
            $res = $stmt->execute([
                'userId' => $timesheet->getUserId(),
                'activityId' => $timesheet->getActivityId(),
                'projectId' => $timesheet->getProjectId(),
                'start' => $timesheet->getStart(),
                'end' => $timesheet->getEnd(),
                'duration' => $timesheet->getDuration(),
                'comment' => $timesheet->getComment(),
                'modifiedAt' => $timesheet->getModifiedAt()
            ]);
            return $this->pdo->lastInsertId();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update Timesheet
     *
     * @param Timesheet $timesheet
     * @return bool
     */
    public function updateTimesheet(Timesheet $timesheet) {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_timesheet` SET `tacos_timesheet`.`user_id` = :userId, `tacos_timesheet`.`activity_id` = :activityId, `tacos_timesheet`.`project_id` = :projectId, `tacos_timesheet`.`start` = :start, `tacos_timesheet`.`end` = :end, `tacos_timesheet`.`duration` = :duration, `tacos_timesheet`.`comment` = :comment, `tacos_timesheet`.`modified_at` = :modifiedAt WHERE  `tacos_timesheet`.`id` = :id');
            $res = $stmt->execute([
                'userId' => $timesheet->getUserId(),
                'activityId' => $timesheet->getActivityId(),
                'projectId' => $timesheet->getProjectId(),
                'start' => $timesheet->getStart(),
                'end' => $timesheet->getEnd(),
                'duration' => $timesheet->getDuration(),
                'comment' => $timesheet->getComment(),
                'modifiedAt' => $timesheet->getModifiedAt(),
                'id' => $timesheet->getId()
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete Timesheet
     *
     * @param Timesheet $timesheet
     * @return string $errorMsg
     */
    public function deleteTimesheet($timesheet) {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM `tacos_timesheet` WHERE `tacos_timesheet`.`id` = :id');
            $res = $stmt->execute([
                'id' => $timesheet->getId()
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }



    /**
     * Insert Tags
     *
     * @param int $timesheetId
     * @param array $data
     * @return bool
     */
    public function insertTags(int $timesheetId, $data) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_timesheet_tags` (`timesheet_id`, `tag_id`) VALUES (:timesheetId, :tagId)');
            foreach ($data as $tagId) {
                $stmt->execute([
                    'timesheetId' => $timesheetId,
                    'tagId' => $tagId
                ]);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update Tags
     *
     * @param int $timesheetId
     * @param array $data
     * @return bool
     */
    public function updateTags(int $timesheetId, $data) {
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_timesheet_tags` WHERE `tacos_timesheet_tags`.`timesheet_id` = :timesheetId');
        $stmt->execute([
            'timesheetId' => $timesheetId
        ]);
        if (count($data) > 0) {
            return $this->insertTags($timesheetId, $data);
        }
        return true;
    }



    /**
     * Creates Timesheet object
     *
     * @param array $row
     * @return Entity\Timesheet
     */
    protected function buildEntity(array $row) {
        $timesheet = new Timesheet();
        $timesheet->setId($row['id']);
        $timesheet->setUserId($row['user_id']);
        $timesheet->setActivityId($row['activity_id']);
        $timesheet->setProjectId($row['project_id']);
        $timesheet->setStart($row['start']);
        $timesheet->setEnd(isset($row['end']) ? $row['end'] : null);
        $timesheet->setDuration(isset($row['duration']) ? $row['duration'] : null);
        $timesheet->setComment(isset($row['comment']) ? $row['comment'] : null);
        $timesheet->setModifiedAt(isset($row['modified_at']) ? $row['modified_at'] : null);

        return $timesheet;
    }

}
