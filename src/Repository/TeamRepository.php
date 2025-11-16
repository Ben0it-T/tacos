<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use PDO;

final class TeamRepository
{
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Find Team by id
     *
     * @param int $id
     * @return Team or false
     */
    public function find(int $id) {
        $stmt = $this->pdo->prepare('SELECT `tacos_teams`.* FROM `tacos_teams` WHERE `tacos_teams`.`id` = ? LIMIT 1');
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
     * Find Team by id and teamleader
     *
     * @param int $teamId
     * @param int $teamleaderId
     * @return Team or false
     */
    public function findOneByIdAndTeamleader(int $teamId, int $teamleaderId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_teams`.* FROM `tacos_teams` LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_teams`.`id` WHERE `tacos_teams`.`id` = :teamId AND `tacos_users_teams`.`user_id` = :teamleaderId AND `tacos_users_teams`.`teamlead` = 1 ORDER BY `tacos_teams`.`name` ASC LIMIT 1');

        $stmt->execute([
            'teamId' => $teamId,
            'teamleaderId' => $teamleaderId,
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
     * Find all Teams
     *
     * @return array of Teams
     */
    public function findAll() {
        $stmt = $this->pdo->prepare('SELECT `tacos_teams`.* FROM `tacos_teams` ORDER BY `tacos_teams`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $teams = array();
        foreach ($rows as $row) {
            $teams[$row['id']] = $this->buildEntity($row);
        }

        return $teams;
    }

    /**
     * Find all Teams by activity
     *
     * @param int $activityId
     * @return array of Teams
     */
    public function findAllTeamsByActivityId(int $activityId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_teams`.* FROM `tacos_teams` LEFT JOIN `tacos_activities_teams` ON `tacos_activities_teams`.`team_id` = `tacos_teams`.`id` WHERE `tacos_activities_teams`.`activity_id` = :activityId ORDER BY `tacos_teams`.`name` ASC');
        $stmt->execute([
            'activityId' => $activityId,
        ]);
        $rows = $stmt->fetchAll();

        $teams = array();
        foreach ($rows as $row) {
            $teams[$row['id']] = $this->buildEntity($row);
        }

        return $teams;
    }

    /**
     * Find all Teams by customer
     *
     * @param int $customerId
     * @return array of Teams
     */
    public function findAllTeamsByCustomerId(int $customerId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_teams`.* FROM `tacos_teams` LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`team_id` = `tacos_teams`.`id` WHERE `tacos_customers_teams`.`customer_id` = :customerId ORDER BY `tacos_teams`.`name` ASC');
        $stmt->execute([
            'customerId' => $customerId,
        ]);
        $rows = $stmt->fetchAll();

        $teams = array();
        foreach ($rows as $row) {
            $teams[$row['id']] = $this->buildEntity($row);
        }

        return $teams;
    }

    /**
     * Find all Teams by project
     *
     * @param int $projectId
     * @return array of Teams
     */
    public function findAllTeamsByProjectId(int $projectId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_teams`.* FROM `tacos_teams` LEFT JOIN `tacos_projects_teams` ON `tacos_projects_teams`.`team_id` = `tacos_teams`.`id` WHERE `tacos_projects_teams`.`project_id` = :projectId ORDER BY `tacos_teams`.`name` ASC');
        $stmt->execute([
            'projectId' => $projectId,
        ]);
        $rows = $stmt->fetchAll();

        $teams = array();
        foreach ($rows as $row) {
            $teams[$row['id']] = $this->buildEntity($row);
        }

        return $teams;
    }

    /**
     * Find all Teams by user
     *
     * @param int $userId
     * @return array of Teams
     */
    public function findAllByUserId(int $userId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_teams`.* FROM `tacos_teams` LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_teams`.`id` WHERE `tacos_users_teams`.`user_id` = :userId ORDER BY `tacos_teams`.`name` ASC');
        $stmt->execute([
            'userId' => $userId,
        ]);
        $rows = $stmt->fetchAll();

        $teams = array();
        foreach ($rows as $row) {
            $teams[$row['id']] = $this->buildEntity($row);
        }

        return $teams;
    }

    /**
     * Find Teamleader's Teams
     *
     * @param int $teamleaderId
     * @return array of Teams
     */
    public function findAllByTeamleaderId(int $teamleaderId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_teams`.* FROM `tacos_teams` LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_teams`.`id` WHERE `tacos_users_teams`.`user_id` = :teamleaderId AND `tacos_users_teams`.`teamlead` = 1 ORDER BY `tacos_teams`.`name` ASC');
        $stmt->execute([
            'teamleaderId' => $teamleaderId,
        ]);
        $rows = $stmt->fetchAll();

        $teams = array();
        foreach ($rows as $row) {
            $teams[$row['id']] = $this->buildEntity($row);
        }

        return $teams;
    }



    /**
     * Check if name exists
     *
     * @param string $name
     * @param int $id
     * @return bool
     */
    public function isTeamNameExists(string $name, int $id = 0) {
        $stmt = $this->pdo->prepare('SELECT count(*) as cnt FROM `tacos_teams` WHERE `tacos_teams`.`name` = :name AND `tacos_teams`.`id` != :id');
        $stmt->execute([
            'name' => $name,
            'id' => $id,
        ]);
        $cnt = $stmt->fetchColumn();

        if ($cnt > 0) {
            return true;
        }
        return false;
    }

    /**
     * Find all Teams with Users count and Teamleaders
     *
     * @return array of Teams with Users count and Teamleaders
     */
    public function findAllTeamsWithUserCountAndTeamleads() {
        $sql  = "SELECT `tacos_teams`.`id`, `tacos_teams`.`name`, `tacos_teams`.`color`, ";
        $sql .= "COUNT(DISTINCT `tacos_users_teams`.`user_id`) AS members, ";
        $sql .= "GROUP_CONCAT(DISTINCT IF(`tacos_users_teams`.`teamlead` = 1, `tacos_users`.`name`, NULL) ORDER BY `tacos_users`.`name` SEPARATOR ', ') AS teamleaders ";
        $sql .= "FROM `tacos_teams` ";
        $sql .= "LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_teams`.`id` ";
        $sql .= "LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` ";
        $sql .= "GROUP BY `tacos_teams`.`id`, `tacos_teams`.`name`, `tacos_teams`.`color` ";
        $sql .= "ORDER BY `tacos_teams`.`name`";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Find all Teams with Users count and Teamleaders by Teamleader id
     *
     * @return array of Teams with Users count and Teamleaders
     */
    public function findAllTeamsWithUserCountAndTeamleadsByTeamleaderId(int $teamleaderId) {
        $sql  = "SELECT `tacos_teams`.`id`, `tacos_teams`.`name`, `tacos_teams`.`color`, ";
        $sql .= "COUNT(DISTINCT `tacos_users_teams`.`user_id`) AS members, ";
        $sql .= "GROUP_CONCAT(DISTINCT IF(`tacos_users_teams`.`teamlead` = 1, `tacos_users`.`name`, NULL) ORDER BY `tacos_users`.`name` SEPARATOR ', ') AS teamleaders ";
        $sql .= "FROM `tacos_teams` ";
        $sql .= "LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_teams`.`id` ";
        $sql .= "LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` ";

        $sql .= "INNER JOIN `tacos_users_teams` ut_tl ON ut_tl.`team_id` = `tacos_teams`.`id` ";
        $sql .= "AND ut_tl.`user_id` = :teamleaderId ";
        $sql .= "AND ut_tl.`teamlead` = 1 ";

        $sql .= "GROUP BY `tacos_teams`.`id`, `tacos_teams`.`name`, `tacos_teams`.`color` ";
        $sql .= "ORDER BY `tacos_teams`.`name`";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'teamleaderId' => $teamleaderId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Get Team Members
     *
     * @param int teamId
     * @return array list of Members
     */
    public function getTeamMembers(int $teamId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_users_teams`.`user_id` as userId, `tacos_users`.`name` as name, `tacos_users_teams`.`teamlead` as teamlead FROM `tacos_users_teams` LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` WHERE `tacos_users_teams`.`team_id` = :teamId ORDER BY name');
        $stmt->execute([
            'teamId' => $teamId,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Get Team Teamleaders
     *
     * @param int teamId
     * @return array list of Teamleaders
     */
    public function getTeamTeamleaders(int $teamId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_users_teams`.`user_id` as userId, `tacos_users`.`name` as name, `tacos_users_teams`.`teamlead` as teamlead FROM `tacos_users_teams` LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` WHERE `tacos_users_teams`.`team_id` = :teamId AND `tacos_users_teams`.`teamlead` = 1 ORDER BY name');
        $stmt->execute([
            'teamId' => $teamId,
        ]);
        return $stmt->fetchAll();
    }



    /**
     * Insert Team
     *
     * @param Team $team
     * @return lastInsertId or false
     */
    public function insert(Team $team) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_teams` (`id`, `name`, `color`) VALUES (NULL, :name, :color)');
            $res = $stmt->execute([
                'name' => $team->getName(),
                'color' => $team->getColor()
            ]);
            return $this->pdo->lastInsertId();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update Team
     *
     * @param Team $team
     * @return bool
     */
    public function updateTeam(Team $team) {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_teams` SET `tacos_teams`.`name` = :name, `tacos_teams`.`color` = :color WHERE `tacos_teams`.`id` = :id');
            $res = $stmt->execute([
                'name' => $team->getName(),
                'color' => $team->getColor(),
                'id' => $team->getId()
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }



    /**
     * Insert Members
     *
     * @param int $teamId
     * @param array $data
     * @return bool
     */
    public function insertMembers(int $teamId, $data) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_users_teams` (`user_id`, `team_id`, `teamlead`) VALUES (:user_id, :team_id, :teamlead)');
            foreach ($data as $userId => $member) {
                $stmt->execute([
                    'user_id' => $userId,
                    'team_id' => $teamId,
                    'teamlead' => $member['teamlead']
                ]);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update Members
     *
     * @param int $teamId
     * @param array $data
     * @return bool
     */
    public function updateMembers(int $teamId, $data) {
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_users_teams` WHERE `tacos_users_teams`.`team_id` = :team_id');
        $stmt->execute([
            'team_id' => $teamId
        ]);
        if (count($data) > 0) {
            return $this->insertMembers($teamId, $data);
        }
        return true;
    }



    /**
     * Creates Team object
     *
     * @param array $row
     * @return Entity\Team
     */
    private function buildEntity(array $row) {
        $team = new Team();
        $team->setId($row['id']);
        $team->setName($row['name']);
        $team->setColor(isset($row['color']) ? $row['color'] : null);

        return $team;
    }
}
