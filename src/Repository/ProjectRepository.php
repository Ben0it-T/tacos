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
     * Find One Project by id
     *
     * @param int $id
     * @return Project entity or false
     */
    public function findOneById(int $id) {
        $stmt = $this->pdo->prepare('SELECT `tacos_projects`.* FROM `tacos_projects` WHERE `tacos_projects`.`id` = ? LIMIT 1');
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
     * Find One Project by id and teamleader id
     *
     * @param int $projectId
     * @param int $teamleaderId
     * @return Project entity or false
     */
    public function findOneByIdAndTeamleaderId(int $projectId, int $teamleaderId) {
        $sql  = 'SELECT p.* ';
        $sql .= 'FROM `tacos_projects` p ';
        $sql .= 'JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'JOIN `tacos_users_teams` ut ON ut.`team_id` = pt.`team_id` AND ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';
        $sql .= 'WHERE p.`id` = :projectId ';
        $sql .= 'LIMIT 1';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'projectId' => $projectId,
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
     * Find All Projects
     *
     * @return array of Project entities
     */
    public function findAll() {
        $stmt = $this->pdo->prepare('SELECT `tacos_projects`.* FROM `tacos_projects` ORDER BY `tacos_projects`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects by visibility
     *
     * @param int $visible
     * @return array of Project entities
     */
    public function findAllByVisibility(int $visible) {
        $stmt = $this->pdo->prepare('SELECT `tacos_projects`.* FROM `tacos_projects` WHERE `tacos_projects`.`visible` = :visible ORDER BY `tacos_projects`.`name` ASC');
        $stmt->execute([
            'visible' => $visible
        ]);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects by Customer id
     *
     * @param int $customerId
     * @return array of Project entities
     */
    public function findAllByCustomerId(int $customerId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_projects`.* FROM `tacos_projects` WHERE `tacos_projects`.`customer_id` = :customerId ORDER BY `tacos_projects`.`name` ASC');
        $stmt->execute([
            'customerId' => $customerId
        ]);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects by Customer id and visibility
     *
     * @param int $customerId
     * @param int $visible
     * @return array of Project entities
     */
    public function findAllByCustomerIdAndVisibility(int $customerId, int $visible) {
        $stmt = $this->pdo->prepare('SELECT `tacos_projects`.* FROM `tacos_projects` WHERE `tacos_projects`.`customer_id` = :customerId AND `tacos_projects`.`visible` = :visible ORDER BY `tacos_projects`.`name` ASC');
        $stmt->execute([
            'customerId' => $customerId,
            'visible' => $visible
        ]);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects by user Id
     * Note : A project is either linked to at least one team, or linked to none.
     *        A user can see the projects associated/linked with their teams AND projects that are not associated/linked with any team.
     *
     * @param int $userId
     * @return array of Project entities
     */
    public function findAllByUserId(int $userId) {
        $sql  = 'SELECT DISTINCT p.* ';
        $sql .= 'FROM `tacos_projects` p ';
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = pt.`team_id` AND ut.`user_id` = :userId ';
        $sql .= 'WHERE (ut.`user_id` IS NOT NULL OR pt.`project_id` IS NULL) ';
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId
        ]);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects by user Id and visibility
     * Note : A project is either linked to at least one team, or linked to none.
     *        A user can see the projects associated/linked with their teams AND projects that are not associated/linked with any team.
     *
     * @param int $userId
     * @param int $visible
     * @return array of Project entities
     */
    public function findAllByUserIdAndVisibility(int $userId, int $visible) {
        $sql  = 'SELECT DISTINCT p.* ';
        $sql .= 'FROM `tacos_projects` p ';
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = pt.`team_id` AND ut.`user_id` = :userId ';
        $sql .= 'WHERE (ut.`user_id` IS NOT NULL OR pt.`project_id` IS NULL) ';
        $sql .= 'AND p.`visible` = :visible ';
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
            'visible' => $visible
        ]);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects by user Id and customer id and visibility
     * Note : A project is either linked to at least one team, or linked to none.
     *        A user can see the projects associated/linked with their teams AND projects that are not associated/linked with any team.
     *
     * @param int $userId
     * @param int $customerId
     * @param int $visible
     * @return array of Project entities
     */
    public function findAllByUserIdAndCustomerIdAndVisibility(int $userId, int $customerId, int $visible) {
        $sql  = 'SELECT DISTINCT p.* ';
        $sql .= 'FROM `tacos_projects` p ';
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = pt.`team_id` AND ut.`user_id` = :userId ';
        $sql .= 'WHERE p.`customer_id` = :customerId ';
        $sql .= 'AND (ut.`user_id` IS NOT NULL OR pt.`project_id` IS NULL) ';
        $sql .= 'AND p.`visible` = :visible ';
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
            'customerId' => $customerId,
            'visible' => $visible
        ]);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects by teamleader Id and visibility
     *
     * @param int $teamleaderId
     * @param int $visible
     * @return array of Project entities
     */
    public function findAllByTeamleaderIdAndVisibility(int $teamleaderId, int $visible) {
        $sql  = 'SELECT DISTINCT p.* ';
        $sql .= 'FROM `tacos_projects` p ';
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = pt.`team_id` AND ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';
        $sql .= 'WHERE (ut.`user_id` IS NOT NULL OR pt.`project_id` IS NULL) ';
        $sql .= 'AND p.`visible` = :visible ';
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'teamleaderId' => $teamleaderId,
            'visible' => $visible
        ]);
        $rows = $stmt->fetchAll();
        $sql;
        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }



    /**
     * Find All Projects with Teams count and Customer
     *
     * @return array of Projects with Teams count and Customer
     */
    public function findAllProjectsWithTeamsCountAndCustomer() {
        $sql  = 'SELECT p.`id`, p.`name`, p.`color`, p.`number`, p.`comment`, p.`visible`, ';
        $sql .= 'c.`name` as customerName , c.`color` as customerColor, ';
        $sql .= 'COUNT(DISTINCT pt.`team_id`) AS teamsCount ';
        $sql .= 'FROM `tacos_projects` p ';
        $sql .= 'LEFT JOIN `tacos_customers` c ON c.`id` = p.`customer_id` ';
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';

        $sql .= 'GROUP BY p.`id` ';
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Find All Projects with Teams count and Customer by Teamleader id
     *
     * @param int $teamleaderId
     * @return array of Projects with Teams count and Customer
     */
    public function findAllProjectsWithTeamsCountAndCustomerByTeamleaderId(int $teamleaderId) {
        $sql  = 'SELECT p.`id`, p.`name`, p.`color`, p.`number`, p.`comment`, p.`visible`, ';
        $sql .= 'c.`name` as customerName , c.`color` as customerColor, ';
        $sql .= 'COUNT(DISTINCT pt2.`team_id`) AS teamsCount ';
        $sql .= 'FROM `tacos_projects` p ';
        $sql .= 'LEFT JOIN `tacos_customers` c ON c.`id` = p.`customer_id` ';
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = pt.`team_id` AND ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';

        // Count all teams
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt2 ON pt2.`project_id` = p.`id` ';

        $sql .= 'WHERE ut.`user_id` IS NOT NULL OR pt.`project_id` IS NULL ';
        $sql .= 'GROUP BY p.`id` ';
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'teamleaderId' => $teamleaderId,
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
