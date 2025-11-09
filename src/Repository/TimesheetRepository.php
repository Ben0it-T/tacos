<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Timesheet;
use PDO;

final class TimesheetRepository
{
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
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
     * Find Active Timesheet by user Id
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
     * Find all Timesheets by user Id
     *
     * @param int $userId
     * @return array of Timesheet
     */
    public function findAllTimesheetByUserId(int $userId) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_timesheet` WHERE `tacos_timesheet`.`user_id` = :userId ORDER BY `tacos_timesheet`.`start` DESC, `tacos_timesheet`.`id` DESC');
        $stmt->execute([
            'userId' => $userId
        ]);
        $rows = $stmt->fetchAll();

        $timesheet = array();
        foreach ($rows as $row) {
            $timesheet[$row['id']] = $this->buildEntity($row);
        }

        return $timesheet;
    }

    /**
     * Find all Timesheets by user Id and date
     *
     * @param int $userId
     * @param $date
     * @return array of Timesheet
     */
    public function findAllTimesheetByUserIdAndStart(int $userId, $date) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_timesheet` WHERE `tacos_timesheet`.`user_id` = :userId AND DATE(`tacos_timesheet`.`start`) = :date ORDER BY `tacos_timesheet`.`start` DESC, `tacos_timesheet`.`id` DESC');
        $stmt->execute([
            'userId' => $userId,
            'date' => $date,
        ]);
        $rows = $stmt->fetchAll();

        $timesheet = array();
        foreach ($rows as $row) {
            $timesheet[$row['id']] = $this->buildEntity($row);
        }

        return $timesheet;
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
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_timesheet` WHERE `tacos_timesheet`.`user_id` = :userId AND (DATE(`tacos_timesheet`.`start`) BETWEEN :date1 AND :date2) ORDER BY `tacos_timesheet`.`start` DESC, `tacos_timesheet`.`id` DESC');
        $stmt->execute([
            'userId' => $userId,
            'date1' => $date1,
            'date2' => $date2,
        ]);
        $rows = $stmt->fetchAll();

        $timesheet = array();
        foreach ($rows as $row) {
            $timesheet[$row['id']] = $this->buildEntity($row);
        }

        return $timesheet;
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
    public function findAllTimesheetByUserIdAndFilters(int $userId, $date1, $date2, $projectIds, $activityIds, $tagIds) {
        $params = array(
            'userId' => $userId,
            'date1' => $date1,
            'date2' => $date2,
        );
        $in_params = array();

        $inProjectIds = "";
        if (count($projectIds) > 0) {
            $in = "";
            $i = 0;
            foreach ($projectIds as $item) {
                $key = ":projectId".$i++;
                $in .= "$key,";
                $in_params[$key] = $item;
            }
            $in = rtrim($in,",");
            $inProjectIds = "AND `tacos_timesheet`.`project_id` IN ($in) ";
        }

        $inActivityIds = "";
        if (count($activityIds) > 0) {
            $in = "";
            $i = 0;
            foreach ($activityIds as $item) {
                $key = ":activityId".$i++;
                $in .= "$key,";
                $in_params[$key] = $item;
            }
            $in = rtrim($in,",");
            $inActivityIds = "AND `tacos_timesheet`.`activity_id` IN ($in) ";
        }

        $inTags = "";
        if (count($tagIds) > 0) {
            $in = "";
            $i = 0;
            foreach ($tagIds as $item) {
                $key = ":tagId".$i++;
                $in .= "$key,";
                $in_params[$key] = $item;
            }
            $in = rtrim($in,",");
            $inTags = "AND `tacos_timesheet_tags`.`tag_id` IN ($in) ";
        }

        $sql  = "SELECT `tacos_timesheet`.* FROM `tacos_timesheet` ";
        if (count($tagIds) > 0) {
            $sql .= 'INNER JOIN `tacos_timesheet_tags` ON `tacos_timesheet_tags`.`timesheet_id` = `tacos_timesheet`.`id` ';
        }
        $sql .= "WHERE `tacos_timesheet`.`user_id` = :userId ";
        $sql .= "AND (DATE(`tacos_timesheet`.`start`) BETWEEN :date1 AND :date2) ";
        $sql .= $inProjectIds;
        $sql .= $inActivityIds;
        $sql .= $inTags;
        if (count($tagIds) > 0) {
            $sql .= "GROUP BY `tacos_timesheet`.`id` ";
        }
        $sql .= "ORDER BY `tacos_timesheet`.`start` DESC, `tacos_timesheet`.`id` DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params,$in_params));
        $rows = $stmt->fetchAll();

        $timesheet = array();
        foreach ($rows as $row) {
            $timesheet[$row['id']] = $this->buildEntity($row);
        }

        return $timesheet;
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
    public function findAllTimesheetsByUsersIdAndFilters(array $usersIds, $date1, $date2, $projectIds, $activityIds, $tagIds) {
        $params = array(
            'date1' => $date1,
            'date2' => $date2,
        );
        $in_params = array();

        $inUsersIds = "";
        if (count($usersIds) > 0) {
            $in = "";
            $i = 0;
            foreach ($usersIds as $item) {
                $key = ":usersIds".$i++;
                $in .= "$key,";
                $in_params[$key] = $item;
            }
            $in = rtrim($in,",");
            $inUsersIds = "AND `tacos_timesheet`.`user_id` IN ($in) ";
        }

        $inProjectIds = "";
        if (count($projectIds) > 0) {
            $in = "";
            $i = 0;
            foreach ($projectIds as $item) {
                $key = ":projectId".$i++;
                $in .= "$key,";
                $in_params[$key] = $item;
            }
            $in = rtrim($in,",");
            $inProjectIds = "AND `tacos_timesheet`.`project_id` IN ($in) ";
        }

        $inActivityIds = "";
        if (count($activityIds) > 0) {
            $in = "";
            $i = 0;
            foreach ($activityIds as $item) {
                $key = ":activityId".$i++;
                $in .= "$key,";
                $in_params[$key] = $item;
            }
            $in = rtrim($in,",");
            $inActivityIds = "AND `tacos_timesheet`.`activity_id` IN ($in) ";
        }

        $inTags = "";
        if (count($tagIds) > 0) {
            $in = "";
            $i = 0;
            foreach ($tagIds as $item) {
                $key = ":tagId".$i++;
                $in .= "$key,";
                $in_params[$key] = $item;
            }
            $in = rtrim($in,",");
            $inTags = "AND `tacos_timesheet_tags`.`tag_id` IN ($in) ";
        }

        $sql  = "SELECT `tacos_timesheet`.* FROM `tacos_timesheet` ";
        if (count($tagIds) > 0) {
            $sql .= 'INNER JOIN `tacos_timesheet_tags` ON `tacos_timesheet_tags`.`timesheet_id` = `tacos_timesheet`.`id` ';
        }
        $sql .= "WHERE (DATE(`tacos_timesheet`.`start`) BETWEEN :date1 AND :date2) ";
        $sql .= $inUsersIds;
        $sql .= $inProjectIds;
        $sql .= $inActivityIds;
        $sql .= $inTags;
        if (count($tagIds) > 0) {
            $sql .= "GROUP BY `tacos_timesheet`.`id` ";
        }
        $sql .= "ORDER BY `tacos_timesheet`.`start` DESC, `tacos_timesheet`.`id` DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params,$in_params));
        $rows = $stmt->fetchAll();

        $timesheet = array();
        foreach ($rows as $row) {
            $timesheet[$row['id']] = $this->buildEntity($row);
        }

        return $timesheet;
    }

    /**
     * Find all Timesheets by users and project Id
     *
     * @param array $usersIds
     * @param $projectId
     * @return array of Timesheet
     */
    public function findAllTimesheetsByUsersIdAndProjetId(array $usersIds, int $projectId) {
        $params = array(
            'projectId' => $projectId
        );
        $in_params = array();

        $inUsersIds = "";
        if (count($usersIds) > 0) {
            $in = "";
            $i = 0;
            foreach ($usersIds as $item) {
                $key = ":usersIds".$i++;
                $in .= "$key,";
                $in_params[$key] = $item;
            }
            $in = rtrim($in,",");
            $inUsersIds = "AND `tacos_timesheet`.`user_id` IN ($in) ";
        }

        $sql  = "SELECT `tacos_timesheet`.* FROM `tacos_timesheet` ";
        $sql .= "WHERE `tacos_timesheet`.`project_id` = :projectId  ";
        $sql .= $inUsersIds;
        $sql .= "ORDER BY `tacos_timesheet`.`start` DESC, `tacos_timesheet`.`id` DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params,$in_params));
        $rows = $stmt->fetchAll();

        $timesheet = array();
        foreach ($rows as $row) {
            $timesheet[$row['id']] = $this->buildEntity($row);
        }

        return $timesheet;
    }

    /**
     * Find all active timesheets by user Id
     *
     * @param int $userId
     * @return array of Timesheet
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
        $sql .= "WHERE `tacos_timesheet`.`user_id` = :userId AND (DATE(`tacos_timesheet`.`start`) BETWEEN :date1 AND :date2) ";
        $sql .= "GROUP BY " . $group . ", `date` ";
        $sql .= "ORDER BY `date` ASC , `name` ASC ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res['data'] = $stmt->fetchAll();

        // Sum of Rows (fields)
        $sql  = "SELECT " . $field . " as `name`, SUM(`tacos_timesheet`.duration) as `duration` ";
        $sql .= "FROM `tacos_timesheet` ";
        $sql .= $join;
        $sql .= "WHERE `tacos_timesheet`.`user_id` = :userId AND (DATE(`tacos_timesheet`.`start`) BETWEEN :date1 AND :date2) ";
        $sql .= "GROUP BY " . $group . " ";
        $sql .= "ORDER BY `name` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res['sumRows'] = $stmt->fetchAll();

        // Sum of Cols (dates)
        $sql  = "SELECT DATE_FORMAT(`tacos_timesheet`.`start`, '%Y-%m-%d') as `date`, SUM(`tacos_timesheet`.duration) as `duration` ";
        $sql .= "FROM `tacos_timesheet` ";
        $sql .= "WHERE `tacos_timesheet`.`user_id` = :userId AND (DATE(`tacos_timesheet`.`start`) BETWEEN :date1 AND :date2) ";
        $sql .= "GROUP BY `date` ";
        $sql .= "ORDER BY `date` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res['sumCols'] = $stmt->fetchAll();

        // Total
        $sql  = "SELECT SUM(`tacos_timesheet`.duration) as `duration` ";
        $sql .= "FROM `tacos_timesheet` ";
        $sql .= "WHERE `tacos_timesheet`.`user_id` = :userId AND (DATE(`tacos_timesheet`.`start`) BETWEEN :date1 AND :date2) ";
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
     * Sop All Timesheets for user
     *
     * @param int $userId
     * @param string $date
     * @return bool
     */
    public function stopAllTimesheetsForUserId(int $userId, $date) {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_timesheet` SET `tacos_timesheet`.`end` = :end1, `tacos_timesheet`.`duration` = CAST(TIME_TO_SEC(TIMEDIFF(:end2, `tacos_timesheet`.`start`))/60 AS UNSIGNED) , `tacos_timesheet`.`modified_at` = :modifiedAt WHERE `tacos_timesheet`.`user_id` = :userId AND `tacos_timesheet`.`end` is NULL');
            $res = $stmt->execute([
                'end1' => $date,
                'end2' => $date,
                'modifiedAt' => date("Y-m-d H:i:s"),
                'userId' => $userId,
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
