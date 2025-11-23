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
     * @return Activity entity or false
     */
    public function find(int $id): Activity|false {
        $sql = 'SELECT a.* FROM `tacos_activities` a WHERE a.`id` = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id
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
     * Find One Activity by id and User id
     *
     * @param int $activityId
     * @param int $userId
     * @return Activity entity or false
     */
    public function findOneByIdAndUserId(int $activityId, int $userId): Activity|false {
        $sql  = 'SELECT a.* ';
        $sql .= 'FROM `tacos_activities` a ';
        $sql .= 'LEFT JOIN `tacos_activities_teams` at ON at.`activity_id` = a.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = at.`team_id` AND ut.`user_id` = :userId ';
        $sql .= 'WHERE a.`id` = :activityId AND (ut.`user_id` IS NOT NULL OR at.`activity_id` IS NULL) ';
        $sql .= 'LIMIT 1';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'activityId' => $activityId,
            'userId' => $userId
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
     * Find One Activity by id and teamleader id
     * Note : accepts activities without a team
     *
     * @param int $activityId
     * @param int $teamleaderId
     * @return Activity entity or false
     */
    public function findOneByIdAndTeamleaderId(int $activityId, int $teamleaderId): Activity|false {
        $sql  = 'SELECT a.* ';
        $sql .= 'FROM `tacos_activities` a ';
        $sql .= 'LEFT JOIN `tacos_activities_teams` at ON at.`activity_id` = a.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = at.`team_id` AND ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';
        $sql .= 'WHERE a.`id` = :activityId AND (ut.`user_id` IS NOT NULL OR at.`activity_id` IS NULL) ';
        $sql .= 'LIMIT 1';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'activityId' => $activityId,
            'teamleaderId' => $teamleaderId
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
     * Find One Activity by id user id is teamleader
     * Note : requires teamlead on at least one team
     *
     * @param int $activityId
     * @param int $teamleaderId
     * @return Activity entity or false
     */
    public function findOneByIdAndTeamleaderIdStrict(int $activityId, int $teamleaderId): Activity|false {
        $sql  = 'SELECT a.* ';
        $sql .= 'FROM `tacos_activities` a ';
        $sql .= 'JOIN `tacos_activities_teams` at ON at.`activity_id` = a.`id` ';
        $sql .= 'JOIN `tacos_users_teams` ut ON ut.`team_id` = at.`team_id` AND ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';
        $sql .= 'WHERE a.`id` = :activityId ';
        $sql .= 'LIMIT 1';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'activityId' => $activityId,
            'teamleaderId' => $teamleaderId
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
     * Find All Activities
     *
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAll(?int $visible = null): array {
        $sql  = 'SELECT a.* FROM `tacos_activities` a ';
        if (!is_null($visible)) {
            $sql .= 'WHERE a.`visible` = :visible ';
        }
        $sql .= 'ORDER BY a.`name` ASC, `a.`number` ASC';

        $stmt = $this->pdo->prepare($sql);
        $params = array();
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Activities by Project id
     * Activities linked to a project
     * = projet "global activities" + project "project activities"
     *
     * @param int  $projectId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllByProjectId(int $projectId, ?int $visible = null): array {
        $sql  = 'SELECT a.* ';
        $sql .= 'FROM `tacos_activities` a ';
        $sql .= 'LEFT JOIN `tacos_projects_activities` pa ON pa.`activity_id` = a.`id` ';
        $sql .= 'WHERE pa.`project_id` = :projectId ';
        if (!is_null($visible)) {
            $sql .= 'AND a.`visible` = :visible ';
        }
        $sql .= 'ORDER BY a.`name` ASC';

        $stmt = $this->pdo->prepare($sql);

        $params = ['projectId' => $projectId];
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All 'Project Activities' by projectId
     *
     * @param int  $projectId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllProjectActivitiesByProjectId(int $projectId, ?int $visible = null): array {
        $sql  = 'SELECT a.* ';
        $sql .= 'FROM `tacos_activities` a ';
        $sql .= 'WHERE a.`project_id` = :projectId ';
        if (!is_null($visible)) {
            $sql .= 'AND a.`visible` = :visible ';
        }
        $sql .= 'ORDER BY a.`name` ASC';

        $stmt = $this->pdo->prepare($sql);

        $params = ['projectId' => $projectId];
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All 'Global Activities'
     *
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllGlobalActivities(?int $visible = null): array {
        $sql  = 'SELECT a.* ';
        $sql .= 'FROM `tacos_activities` a ';
        $sql .= 'WHERE a.`project_id` IS NULL ';
        if (!is_null($visible)) {
            $sql .= 'AND a.`visible` = :visible ';
        }
        $sql .= 'ORDER BY a.`name` ASC';

        $stmt = $this->pdo->prepare($sql);

        $params = array();
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Activities by User Id
     *
     * @param int  $userId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllByUserId(int $userId, ?int $visible = null): array {
        $sql  = 'SELECT DISTINCT a.* ';
        $sql .= 'FROM `tacos_activities` a ';
        $sql .= 'LEFT JOIN `tacos_activities_teams` at ON at.`activity_id` = a.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = at.`team_id` AND ut.`user_id` = :userId ';
        $sql .= 'WHERE (ut.`user_id` IS NOT NULL OR at.`activity_id` IS NULL) ';
        if (!is_null($visible)) {
            $sql .= 'AND a.`visible` = :visible ';
        }
        $sql .= 'ORDER BY a.`name` ASC';

        $stmt = $this->pdo->prepare($sql);

        $params = ['userId' => $userId];
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Activities by Teamleader Id
     *
     * @param int  $teamleaderId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllByTeamleaderId(int $teamleaderId, ?int $visible = null): array {
        $sql  = 'SELECT DISTINCT a.* ';
        $sql .= 'FROM `tacos_activities` a ';
        $sql .= 'LEFT JOIN `tacos_activities_teams` at ON at.`activity_id` = a.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = at.`team_id` AND ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';
        $sql .= 'WHERE (ut.`user_id` IS NOT NULL OR at.`activity_id` IS NULL) ';
        if (!is_null($visible)) {
            $sql .= 'AND a.`visible` = :visible ';
        }
        $sql .= 'ORDER BY a.`name` ASC';

        $stmt = $this->pdo->prepare($sql);

        $params = ['teamleaderId' => $teamleaderId];
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }

    /**
     * Find All Activities by User Id and by Project Id
     *
     * @param int  $projectId
     * @param int  $userId
     * @param ?int $visible
     * @return array of Activity entities
     */
    public function findAllByUserIdAndProjectId(int $userId, int $projectId, ?int $visible = null): array {
        $sql  = 'SELECT DISTINCT a.* ';
        $sql .= 'FROM `tacos_activities` a ';
        $sql .= 'LEFT JOIN `tacos_projects_activities` pa ON pa.`activity_id` = a.`id` ';
        $sql .= 'LEFT JOIN `tacos_activities_teams` at ON at.`activity_id` = a.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = at.`team_id` AND ut.`user_id` = :userId ';
        $sql .= 'WHERE pa.`project_id` = :projectId AND (ut.`user_id` IS NOT NULL OR at.`activity_id` IS NULL) ';
        if (!is_null($visible)) {
            $sql .= 'AND a.`visible` = :visible ';
        }
        $sql .= 'ORDER BY a.`name` ASC';

        $stmt = $this->pdo->prepare($sql);

        $params = array(
            'projectId' => $projectId,
            'userId' => $userId
        );
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $activities = array();
        foreach ($rows as $row) {
            $activities[$row['id']] = $this->buildEntity($row);
        }

        return $activities;
    }





    /**
     * Find All Activities with Teams count and Project
     *
     * @return array of Activities with Teams count and Project
     */
    public function findAllActivitiesWithTeamsCountAndProject(): array {
        $sql  = 'SELECT a.`id`, a.`name`, a.`color`, a.`number`, a.`comment`, a.`visible`, ';
        $sql .= 'p.`name` as projectName , p.`color` as projectColor, ';
        $sql .= 'COUNT(DISTINCT at.`team_id`) AS teamsCount ';
        $sql .= 'FROM `tacos_activities` a ';
        $sql .= 'LEFT JOIN `tacos_projects` p ON p.`id` = a.`project_id` ';
        $sql .= 'LEFT JOIN `tacos_activities_teams` at ON at.`activity_id` = a.`id` ';
        $sql .= 'GROUP BY a.`id` ';
        $sql .= 'ORDER BY a.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Find All Activities with Teams count and Project by Teamleader id
     *
     * @return array of Activities with Teams count and Project
     */
    public function findAllActivitiesWithTeamsCountAndProjectByTeamleaderId(int $teamleaderId): array {
        $sql  = 'SELECT a.`id`, a.`name`, a.`color`, a.`number`, a.`comment`, a.`visible`, ';
        $sql .= 'p.`name` as projectName , p.`color` as projectColor, ';
        $sql .= 'COUNT(DISTINCT at.`team_id`) AS teamsCount ';
        $sql .= 'FROM `tacos_activities` a ';
        $sql .= 'LEFT JOIN `tacos_projects` p ON p.`id` = a.`project_id` ';
        $sql .= 'LEFT JOIN `tacos_activities_teams` at ON at.`activity_id` = a.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = at.`team_id` AND ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';
        $sql .= 'WHERE (ut.`user_id` IS NOT NULL OR at.`activity_id` IS NULL)  ';
        $sql .= 'GROUP BY a.`id` ';
        $sql .= 'ORDER BY a.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'teamleaderId' => $teamleaderId
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
