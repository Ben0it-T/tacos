<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use PDO;

final class ProjectRepository
{
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Find Project by id
     *
     * @param int $id
     * @return Project or false
     */
    public function find(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_projects` WHERE `tacos_projects`.`id` = ?');
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
     * Find Projects
     *
     * @return array of Projects
     */
    public function findAll() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_projects` ORDER BY `tacos_projects`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find all Projects by customer
     *
     * @param int $customerId
     * @return array of Projects
     */
    public function findAllProjectsByCustomerId($customerId) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_projects` WHERE `tacos_projects`.`customer_id` = :customerId ORDER BY `tacos_projects`.`name` ASC');
        $stmt->execute([
            'customerId' => $customerId,
        ]);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find all Visible Projects by customer
     *
     * @param int $customerId
     * @return array of Projects
     */
    public function findAllVisibleProjectsByCustomerId($customerId) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_projects` WHERE `tacos_projects`.`customer_id` = :customerId AND `tacos_projects`.`visible` = 1 ORDER BY `tacos_projects`.`name` ASC');
        $stmt->execute([
            'customerId' => $customerId,
        ]);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects have teams
     *
     * @return array of Projects
     */
    public function findAllProjectsHaveTeams() {
        $stmt = $this->pdo->prepare('SELECT `tacos_projects`.* FROM `tacos_projects` LEFT JOIN `tacos_projects_teams` ON `tacos_projects_teams`.`project_id` = `tacos_projects`.`id` WHERE `tacos_projects_teams`.`team_id` IS NOT NULL GROUP BY `tacos_projects`.`id` ORDER BY `tacos_projects`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Visible Projects have teams
     *
     * @return array of Projects
     */
    public function findAllVisibleProjectsHaveTeams() {
        $stmt = $this->pdo->prepare('SELECT `tacos_projects`.* FROM `tacos_projects` LEFT JOIN `tacos_projects_teams` ON `tacos_projects_teams`.`project_id` = `tacos_projects`.`id` WHERE `tacos_projects_teams`.`team_id` IS NOT NULL AND `tacos_projects`.`visible` = 1 GROUP BY `tacos_projects`.`id` ORDER BY `tacos_projects`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects not in a team
     *
     * @return array of Projects
     */
    public function findAllProjectsNotInTeam() {
        $stmt = $this->pdo->prepare('SELECT `tacos_projects`.* FROM `tacos_projects` LEFT JOIN `tacos_projects_teams` ON `tacos_projects_teams`.`project_id` = `tacos_projects`.`id` WHERE `tacos_projects_teams`.`team_id` IS NULL GROUP BY `tacos_projects`.`id` ORDER BY `tacos_projects`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Visible Projects not in a team
     *
     * @return array of Projects
     */
    public function findAllVisibleProjectsNotInTeam() {
        $stmt = $this->pdo->prepare('SELECT `tacos_projects`.* FROM `tacos_projects` LEFT JOIN `tacos_projects_teams` ON `tacos_projects_teams`.`project_id` = `tacos_projects`.`id` WHERE `tacos_projects_teams`.`team_id` IS NULL AND `tacos_projects`.`visible` = 1 GROUP BY `tacos_projects`.`id` ORDER BY `tacos_projects`.`name` ASC');

        $stmt->execute();
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects by user Id
     *
     * @param int $userId
     * @return array of Projects
     */
    public function findAllProjectsByUserId(int $userId) {
        $sql  = 'SELECT `tacos_projects`.* ';
        $sql .= 'FROM `tacos_projects` ';
        $sql .= 'LEFT JOIN `tacos_projects_teams` ON `tacos_projects_teams`.`project_id` = `tacos_projects`.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_projects_teams`.`team_id` ';
        $sql .= 'LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` ';
        $sql .= 'WHERE `tacos_users`.`id` = :userId ';
        $sql .= 'GROUP BY `tacos_projects`.`id` ORDER BY `tacos_projects`.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
        ]);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Visible Projects by user Id
     *
     * @param int $userId
     * @return array of Projects
     */
    public function findAllVisibleProjectsByUserId(int $userId) {
        $sql  = 'SELECT `tacos_projects`.* ';
        $sql .= 'FROM `tacos_projects` ';
        $sql .= 'LEFT JOIN `tacos_projects_teams` ON `tacos_projects_teams`.`project_id` = `tacos_projects`.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_projects_teams`.`team_id` ';
        $sql .= 'LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` ';
        $sql .= 'WHERE `tacos_users`.`id` = :userId ';
        $sql .= 'AND `tacos_projects`.`visible` = 1 ';
        $sql .= 'GROUP BY `tacos_projects`.`id` ORDER BY `tacos_projects`.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
        ]);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Visible Projects by user Id and customer Id
     *
     * @param int $userId
     * @param int $customerId
     * @return array of Projects
     */
    public function findAllVisibleProjectsByUserIdAndCustomerId(int $userId, int $customerId) {
        $sql  = 'SELECT `tacos_projects`.* ';
        $sql .= 'FROM `tacos_projects` ';
        $sql .= 'LEFT JOIN `tacos_projects_teams` ON `tacos_projects_teams`.`project_id` = `tacos_projects`.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_projects_teams`.`team_id` ';
        $sql .= 'LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` ';
        $sql .= 'WHERE `tacos_users`.`id` = :userId ';
        $sql .= 'AND `tacos_projects`.`customer_id` = :customerId ';
        $sql .= 'AND `tacos_projects`.`visible` = 1 ';
        $sql .= 'GROUP BY `tacos_projects`.`id` ORDER BY `tacos_projects`.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
            'customerId' => $customerId,
        ]);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }



    /**
     * Get number of projects for customer
     *
     * @param int $customerId
     * @return int number of Projects
     */
    public function getNbOfProjectsForCustomer(int $customerId) {
        $stmt = $this->pdo->prepare('SELECT count(*) as cnt FROM `tacos_projects` WHERE `tacos_projects`.`customer_id` = :customerId');
        $stmt->execute([
            'customerId' => $customerId,
        ]);
        return $stmt->fetchColumn();
    }

    /**
     * Get number of teams for project
     *
     * @param int $projectId
     * @return int number of teams for project
     */
    public function getNbOfTeamsForProject(int $projectId) {
        $stmt = $this->pdo->prepare('SELECT count(*) as cnt FROM `tacos_projects_teams` WHERE `tacos_projects_teams`.`project_id` = :projectId');
        $stmt->execute([
            'projectId' => $projectId,
        ]);
        return $stmt->fetchColumn();
    }

    /**
     * Get teams for project
     *
     * @param int projectId
     * @return array list of Teams
     */
    public function getTeamsForProject(int $projectId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_projects_teams`.`team_id` as teamId, `tacos_teams`.`name` as name FROM `tacos_projects_teams` LEFT JOIN `tacos_teams` ON `tacos_teams`.`id` = `tacos_projects_teams`.`team_id` WHERE `tacos_projects_teams`.`project_id` = :projectId ORDER BY name');
        $stmt->execute([
            'projectId' => $projectId,
        ]);
        return $stmt->fetchAll();
    }



    /**
     * Insert Project
     *
     * @param Project $project
     * @return lastInsertId or false
     */
    public function insert(Project $project) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_projects` (`id`, `customer_id`, `name`, `color`, `number`, `comment`, `start`, `end`, `last_activity`, `global_activities`, `visible`, `created_at`) VALUES (NULL, :customerId, :name, :color, :number, :comment, :start, :end, NULL, :globalActivities, :visible, :createdAt)');
            $res = $stmt->execute([
                'customerId' => $project->getCustomerId(),
                'name' => $project->getName(),
                'color' => $project->getColor(),
                'number' => $project->getNumber(),
                'comment' => $project->getComment(),
                'start' => $project->getStart(),
                'end' => $project->getEnd(),
                'globalActivities' => $project->getGlobalActivities(),
                'visible' => $project->getVisible(),
                'createdAt' => $project->getCreatedAt()
            ]);
            return $this->pdo->lastInsertId();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update Project
     *
     * @param Project $project
     * @return bool
     */
    public function updateProject(Project $project) {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_projects` SET `tacos_projects`.`customer_id` = :customerId, `tacos_projects`.`name` = :name, `tacos_projects`.`color` = :color, `tacos_projects`.`number` = :number, `tacos_projects`.`comment` = :comment, `tacos_projects`.`start` = :start, `tacos_projects`.`end` = :end, `tacos_projects`.`global_activities` = :globalActivities, `tacos_projects`.`visible` = :visible WHERE `tacos_projects`.`id` = :id');
            $res = $stmt->execute([
                'customerId' => $project->getCustomerId(),
                'name' => $project->getName(),
                'color' => $project->getColor(),
                'number' => $project->getNumber(),
                'comment' => $project->getComment(),
                'start' => $project->getStart(),
                'end' => $project->getEnd(),
                'globalActivities' => $project->getGlobalActivities(),
                'visible' => $project->getVisible(),
                'id' => $project->getId()
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }



    /**
     * Insert Teams
     *
     * @param int $projectId
     * @param array $data
     * @return bool
     */
    public function insertTeams(int $projectId, $data) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_projects_teams` (`project_id`, `team_id`) VALUES (:project_id, :team_id)');
            foreach ($data as $teamId) {
                $stmt->execute([
                    'project_id' => $projectId,
                    'team_id' => $teamId
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
     * @param int $projectId
     * @param array $data
     * @return bool
     */
    public function updateTeams(int $projectId, $data) {
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_projects_teams` WHERE `tacos_projects_teams`.`project_id` = :projectId');
        $stmt->execute([
            'projectId' => $projectId
        ]);
        if (count($data) > 0) {
            return $this->insertTeams($projectId, $data);
        }
        return true;
    }



    /**
     * Insert Activities
     *
     * @param int $projectId
     * @param array $data
     * @return bool
     */
    public function insertActivities(int $projectId, $data) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_projects_activities` (`project_id`, `activity_id`) VALUES (:project_id, :activity_id)');
            foreach ($data as $activityId) {
                $stmt->execute([
                    'project_id' => $projectId,
                    'activity_id' => $activityId
                ]);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update Activities
     *
     * @param int $projectId
     * @param array $data
     * @return bool
     */
    public function updateActivities(int $projectId, $data) {
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_projects_activities` WHERE `tacos_projects_activities`.`project_id` = :projectId');
        $stmt->execute([
            'projectId' => $projectId
        ]);
        if (count($data) > 0) {
            return $this->insertActivities($projectId, $data);
        }
        return true;
    }




    /**
     * Creates Project object
     *
     * @param array $row
     * @return Entity\Project
     */
    protected function buildEntity(array $row) {
        $project = new Project();
        $project->setId($row['id']);
        $project->setCustomerId($row['customer_id']);
        $project->setName($row['name']);
        $project->setColor($row['color']);
        $project->setNumber(isset($row['number']) ? $row['number'] : null);
        $project->setComment(isset($row['comment']) ? $row['comment'] : null);
        $project->setStart(isset($row['start']) ? $row['start'] : null);
        $project->setEnd(isset($row['end']) ? $row['end'] : null);
        $project->setLastActivity(isset($row['last_activity']) ? $row['last_activity'] : null);
        $project->setGlobalActivities($row['global_activities']);
        $project->setVisible($row['visible']);
        $project->setCreatedAt(isset($row['created_at']) ? $row['created_at'] : null);

        return $project;
    }
}
