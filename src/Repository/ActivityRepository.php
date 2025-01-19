<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use PDO;

final class ActivityRepository
{
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Find Activity by id
     *
     * @param int $id
     * @return Activity or false
     */
    public function find(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_activities` WHERE `tacos_activities`.`id` = ?');
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
     * Find All Activities
     *
     * @return array of Activity
     */
    public function findAll() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_activities` ORDER BY `tacos_activities`.`name` ASC, `tacos_activities`.`number` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find (all) project activities
     *
     * @param int $projectId
     * @return array of Activities for this project
     */
    public function findProjectAllowedActivities(int $projectId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_activities`.* FROM `tacos_activities` LEFT JOIN `tacos_projects_activities` ON `tacos_projects_activities`.`activity_id` = `tacos_activities`.`id`  WHERE `tacos_projects_activities`.`project_id` = :projectId ORDER BY `tacos_activities`.`name` ASC');
        $stmt->execute([
            'projectId' => $projectId,
        ]);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Activities by projectId
     *
     * @param int $projectId
     * @return array of Activity
     */
    public function findAllActivitiesByProjectId(int $projectId) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_activities` WHERE `tacos_activities`.`project_id` = :projectId ORDER BY `tacos_activities`.`name` ASC');
        $stmt->execute([
            'projectId' => $projectId,
        ]);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Visible Activities by projectId
     *
     * @param int $projectId
     * @return array of Activity
     */
    public function findAllVisibleActivitiesByProjectId(int $projectId) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_activities` WHERE `tacos_activities`.`project_id` = :projectId AND `tacos_activities`.`visible` = 1 ORDER BY `tacos_activities`.`name` ASC');
        $stmt->execute([
            'projectId' => $projectId,
        ]);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Global Activities
     *
     * @return array of Activity
     */
    public function findAllGlobalActivities() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_activities` WHERE `tacos_activities`.`project_id` is NULL ORDER BY `tacos_activities`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Visible Global Activities
     *
     * @return array of Activity
     */
    public function findAllVisibleGlobalActivities() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_activities` WHERE `tacos_activities`.`project_id` is NULL AND `tacos_activities`.`visible` = 1 ORDER BY `tacos_activities`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }


    /**
     * Find All Activities have teams
     *
     * @return array of Activity
     */
    public function findAllActivitiesHaveTeams() {
        $stmt = $this->pdo->prepare('SELECT `tacos_activities`.* FROM `tacos_activities` LEFT JOIN `tacos_activities_teams` ON `tacos_activities_teams`.`activity_id` = `tacos_activities`.`id` WHERE `tacos_activities_teams`.`team_id` IS NOT NULL GROUP BY `tacos_activities`.`id` ORDER BY `tacos_activities`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All visible Activities have teams
     *
     * @return array of Activity
     */
    public function findAllVisibleActivitiesHaveTeams() {
        $stmt = $this->pdo->prepare('SELECT `tacos_activities`.* FROM `tacos_activities` LEFT JOIN `tacos_activities_teams` ON `tacos_activities_teams`.`activity_id` = `tacos_activities`.`id` WHERE `tacos_activities_teams`.`team_id` IS NOT NULL AND `tacos_activities`.`visible` = 1 GROUP BY `tacos_activities`.`id` ORDER BY `tacos_activities`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Activities not in a team
     *
     * @return array of Activity
     */
    public function findAllActivitiesNotInTeam() {
        $stmt = $this->pdo->prepare('SELECT `tacos_activities`.* FROM `tacos_activities` LEFT JOIN `tacos_activities_teams` ON `tacos_activities_teams`.`activity_id` = `tacos_activities`.`id` WHERE `tacos_activities_teams`.`team_id` IS NULL GROUP BY `tacos_activities`.`id` ORDER BY `tacos_activities`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Visible Activities not in a team (global activities)
     *
     * @return array of Activity
     */
    public function findAllVisibleActivitiesNotInTeam() {
        $stmt = $this->pdo->prepare('SELECT `tacos_activities`.* FROM `tacos_activities` LEFT JOIN `tacos_activities_teams` ON `tacos_activities_teams`.`activity_id` = `tacos_activities`.`id` WHERE `tacos_activities_teams`.`team_id` IS NULL AND `tacos_activities`.`visible` = 1 GROUP BY `tacos_activities`.`id` ORDER BY `tacos_activities`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Activities by user Id
     *
     * @param int $userId
     * @return array of Activity
     */
    public function findAllActivitiesByUserId(int $userId) {
        $sql  = 'SELECT `tacos_activities`.* ';
        $sql .= 'FROM `tacos_activities` ';
        $sql .= 'LEFT JOIN `tacos_activities_teams` ON `tacos_activities_teams`.`activity_id` = `tacos_activities`.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_activities_teams`.`team_id` ';
        $sql .= 'LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` ';
        $sql .= 'WHERE `tacos_users`.`id` = :userId ';
        $sql .= 'GROUP BY `tacos_activities`.`id` ORDER BY `tacos_activities`.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
        ]);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Visible Activities by user Id
     *
     * @param int $userId
     * @return array of Activity
     */
    public function findAllVisibleActivitiesByUserId(int $userId) {
        $sql  = 'SELECT `tacos_activities`.* ';
        $sql .= 'FROM `tacos_activities` ';
        $sql .= 'LEFT JOIN `tacos_activities_teams` ON `tacos_activities_teams`.`activity_id` = `tacos_activities`.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_activities_teams`.`team_id` ';
        $sql .= 'LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` ';
        $sql .= 'WHERE `tacos_users`.`id` = :userId ';
        $sql .= 'AND `tacos_activities`.`visible` = 1 ';
        $sql .= 'GROUP BY `tacos_activities`.`id` ORDER BY `tacos_activities`.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
        ]);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }



    /**
     * Get number of teams for activity
     *
     * @param int $activityId
     * @return int number of teams for activity
     */
    public function getNbOfTeamsForActivity(int $activityId) {
        $stmt = $this->pdo->prepare('SELECT count(*) as cnt FROM `tacos_activities_teams` WHERE `tacos_activities_teams`.`activity_id` = :activityId');
        $stmt->execute([
            'activityId' => $activityId,
        ]);
        return $stmt->fetchColumn();
    }

    /**
     * Get teams for activity
     *
     * @param int activityId
     * @return array list of Teams
     */
    public function getTeamsForactivity(int $activityId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_activities_teams`.`team_id` as teamId, `tacos_teams`.`name` as name FROM `tacos_activities_teams` LEFT JOIN `tacos_teams` ON `tacos_teams`.`id` = `tacos_activities_teams`.`team_id` WHERE `tacos_activities_teams`.`activity_id` = :activityId ORDER BY name');
        $stmt->execute([
            'activityId' => $activityId,
        ]);
        return $stmt->fetchAll();
    }






    /**
     * Insert Activity
     *
     * @param Activity $activity
     * @return lastInsertId or false
     */
    public function insert(Activity $activity) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_activities` (`id`, `project_id`, `name`, `color`, `number`, `comment`, `visible`, `created_at`) VALUES (NULL, :projectId, :name, :color, :number, :comment, :visible, :createdAt)');
            $res = $stmt->execute([
                'projectId' => $activity->getProjectId(),
                'name' => $activity->getName(),
                'color' => $activity->getColor(),
                'number' => $activity->getNumber(),
                'comment' => $activity->getComment(),
                'visible' => $activity->getVisible(),
                'createdAt' => $activity->getCreatedAt()
            ]);
            return $this->pdo->lastInsertId();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update Activity
     *
     * @param Activity $activity
     * @return bool
     */
    public function updateActivity(Activity $activity) {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_activities` SET `tacos_activities`.`name` = :name, `tacos_activities`.`color` = :color, `tacos_activities`.`number` = :number, `tacos_activities`.`comment` = :comment, `tacos_activities`.`visible` = :visible WHERE `tacos_activities`.`id` = :id');
            $res = $stmt->execute([
                'name' => $activity->getName(),
                'color' => $activity->getColor(),
                'number' => $activity->getNumber(),
                'comment' => $activity->getComment(),
                'visible' => $activity->getVisible(),
                'id' => $activity->getId()
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * Insert Teams
     *
     * @param int $activityId
     * @param array $data
     * @return bool
     */
    public function insertTeams(int $activityId, $data) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_activities_teams` (`activity_id`, `team_id`) VALUES (:activityId, :teamId)');
            foreach ($data as $teamId) {
                $stmt->execute([
                    'activityId' => $activityId,
                    'teamId' => $teamId
                ]);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update Teams
     *
     * @param int $activityId
     * @param array $data
     * @return bool
     */
    public function updateTeams(int $activityId, $data) {
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_activities_teams` WHERE `tacos_activities_teams`.`activity_id` = :activityId');
        $stmt->execute([
            'activityId' => $activityId
        ]);
        if (count($data) > 0) {
            return $this->insertTeams($activityId, $data);
        }
        return true;
    }



    /**
     * Creates Activity object
     *
     * @param array $row
     * @return Entity\Activity
     */
    protected function buildEntity(array $row) {
        $activity = new Activity();
        $activity->setId($row['id']);
        $activity->setProjectId($row['project_id']);
        $activity->setName($row['name']);
        $activity->setColor($row['color']);
        $activity->setNumber(isset($row['number']) ? $row['number'] : null);
        $activity->setComment(isset($row['comment']) ? $row['comment'] : null);
        $activity->setVisible($row['visible']);
        $activity->setCreatedAt(isset($row['created_at']) ? $row['created_at'] : null);

        return $activity;
    }
}
