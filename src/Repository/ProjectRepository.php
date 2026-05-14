<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use Psr\Log\LoggerInterface;

use PDO;

final class ProjectRepository
{
    private PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, LoggerInterface $logger) {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    /**
     * Find One Project by id
     *
     * @param int $id
     * @return Project entity or false
     */
    public function find(int $id): Project|false {
        $sql = 'SELECT p.* FROM `tacos_projects` p WHERE p.`id` = :id LIMIT 1';
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
     * Find One Project by id and teamleader id
     * Accepts projects without a team
     *
     * Visibility rules:
     *  - customer without team + project without team => visible to all
     *  - customer without team + project with team    => visible if user is member of a project team
     *  - customer with team    + project without team => visible if user is member of a customer team
     *  - customer with team    + project with team    => visible if user is member of at least one customer team AND at least one project team
     *
     * @param int $projectId
     * @param int $teamleaderId
     * @return Project entity or false
     */
    public function findOneByIdAndTeamleaderId(int $projectId, int $teamleaderId): Project|false {
        $sql  = 'SELECT DISTINCT p.* ';
        $sql .= 'FROM `tacos_projects` p ';
         // customer teams + whether current teamleader is member of one of them
        $sql .= 'LEFT JOIN `tacos_customers` c ON c.`id` = p.`customer_id` ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utc ON utc.`team_id` = ct.`team_id` AND utc.`user_id` = :teamleaderId1 AND utc.`teamlead` = 1 ';
        // project teams + whether current user is member of one of them
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utp ON utp.`team_id` = pt.`team_id` AND utp.`user_id` = :teamleaderId2 AND utp.`teamlead` = 1 ';

        $sql .= 'WHERE p.`id` = :projectId ';
        $sql .= 'AND (ct.`team_id` IS NULL OR utc.`user_id` IS NOT NULL) ';    // customer: either customer has no team OR user is member of a customer team
        $sql .= 'AND (pt.`project_id` IS NULL OR utp.`user_id` IS NOT NULL) '; // project: either project has no team OR user is member of a project team

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'projectId' => $projectId,
            'teamleaderId1' => $teamleaderId,
            'teamleaderId2' => $teamleaderId
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
     * Find One Project by id and user id is teamleader
     * Requires teamlead on at least one team
     *
     * Visibility rules:
     *  - customer without team + project with team    => visible if user is member of a project team
     *  - customer with team    + project with team    => visible if user is member of at least one customer team AND at least one project team
     *
     * @param int $projectId
     * @param int $teamleaderId
     * @return Project entity or false
     */
    public function findOneByIdAndTeamleaderIdStrict(int $projectId, int $teamleaderId): Project|false {
        $sql  = 'SELECT DISTINCT p.* ';
        $sql .= 'FROM `tacos_projects` p ';
         // customer teams + whether current teamleader is member of one of them
        $sql .= 'LEFT JOIN `tacos_customers` c ON c.`id` = p.`customer_id` ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utc ON utc.`team_id` = ct.`team_id` AND utc.`user_id` = :teamleaderId1 AND utc.`teamlead` = 1 ';
        // project teams + whether current user is member of one of them
        $sql .= 'JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'JOIN `tacos_users_teams` utp ON utp.`team_id` = pt.`team_id` AND utp.`user_id` = :teamleaderId2 AND utp.`teamlead` = 1 ';

        $sql .= 'WHERE p.`id` = :projectId ';
        $sql .= 'AND (ct.`customer_id` IS NULL OR utc.`user_id` IS NOT NULL) ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'projectId' => $projectId,
            'teamleaderId1' => $teamleaderId,
            'teamleaderId2' => $teamleaderId
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
     * @param ?int $visible
     * @return array of Project entities
     */
    public function findAll(?int $visible = null): array {
        $sql  = 'SELECT p.* FROM `tacos_projects` p ';
        if (!is_null($visible)) {
            $sql .= 'WHERE p.`visible` = :visible ';
        }
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $params = array();
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }
        $stmt->execute($params);
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
     * @param int  $customerId
     * @param ?int $visible
     * @return array of Project entities
     */
    public function findAllByCustomerId(int $customerId, ?int $visible = null): array {
        $sql  = 'SELECT p.* FROM `tacos_projects` p ';
        $sql .= 'WHERE p.`customer_id` = :customerId ';
        if (!is_null($visible)) {
            $sql .= 'AND p.`visible` = :visible ';
        }
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);

        $params = ['customerId' => $customerId];
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
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
     * Visibility rules:
     *  - customer without team + project without team => visible to all
     *  - customer without team + project with team    => visible if user is member of a project team
     *  - customer with team    + project without team => visible if user is member of a customer team
     *  - customer with team    + project with team    => visible if user is member of at least one customer team AND at least one project team
     *
     * @param int  $userId
     * @param ?int $visible
     * @return array of Project entities
     */
    public function findAllByUserId(int $userId, ?int $visible = null): array {
        $sql  = 'SELECT DISTINCT p.* ';
        $sql .= 'FROM `tacos_projects` p ';
         // customer teams + whether current user is member of one of them
        $sql .= 'LEFT JOIN `tacos_customers` c ON c.`id` = p.`customer_id` ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utc ON utc.`team_id` = ct.`team_id` AND utc.`user_id` = :userId1 ';
        // project teams + whether current user is member of one of them
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utp ON utp.`team_id` = pt.`team_id` AND utp.`user_id` = :userId2 ';

        $sql .= 'WHERE (ct.`team_id` IS NULL OR utc.`user_id` IS NOT NULL) ';  // customer: either customer has no team OR user is member of a customer team
        $sql .= 'AND (pt.`project_id` IS NULL OR utp.`user_id` IS NOT NULL) '; // project: either project has no team OR user is member of a project team

        if (!is_null($visible)) {
            $sql .= 'AND p.`visible` = :visible ';
        }
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);

        $params = array(
            'userId1' => $userId,
            'userId2' => $userId
        );
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects by user Id and customer id
     *
     * Visibility rules:
     *  - customer without team + project without team => visible to all
     *  - customer without team + project with team    => visible if user is member of a project team
     *  - customer with team    + project without team => visible if user is member of a customer team
     *  - customer with team    + project with team    => visible if user is member of at least one customer team AND at least one project team
     *
     * @param int  $userId
     * @param int  $customerId
     * @param ?int $visible
     * @return array of Project entities
     */
    public function findAllByUserIdAndCustomerId(int $userId, int $customerId, ?int $visible = null): array {
        $sql  = 'SELECT DISTINCT p.* ';
        $sql .= 'FROM `tacos_projects` p ';
         // customer teams + whether current user is member of one of them
        $sql .= 'LEFT JOIN `tacos_customers` c ON c.`id` = p.`customer_id` ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utc ON utc.`team_id` = ct.`team_id` AND utc.`user_id` = :userId1 ';
        // project teams + whether current user is member of one of them
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utp ON utp.`team_id` = pt.`team_id` AND utp.`user_id` = :userId2 ';

        $sql .= 'WHERE p.`customer_id` = :customerId ';
        $sql .= 'AND (ct.`team_id` IS NULL OR utc.`user_id` IS NOT NULL) ';  // customer: either customer has no team OR user is member of a customer team
        $sql .= 'AND (pt.`project_id` IS NULL OR utp.`user_id` IS NOT NULL) '; // project: either project has no team OR user is member of a project team

        if (!is_null($visible)) {
            $sql .= 'AND p.`visible` = :visible ';
        }
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);

        $params = array(
            'userId1' => $userId,
            'userId2' => $userId,
            'customerId' => $customerId,
        );
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $projects = array();
        foreach ($rows as $row) {
            $projects[$row['id']] = $this->buildEntity($row);
        }

        return $projects;
    }

    /**
     * Find All Projects by teamleader Id
     *
     * Visibility rules:
     *  - customer without team + project without team => visible to all
     *  - customer without team + project with team    => visible if user is member of a project team
     *  - customer with team    + project without team => visible if user is member of a customer team
     *  - customer with team    + project with team    => visible if user is member of at least one customer team AND at least one project team
     *
     * @param int $teamleaderId
     * @param ?int $visible
     * @return array of Project entities
     */
    public function findAllByTeamleaderId(int $teamleaderId, ?int $visible = null): array {
        $sql  = 'SELECT DISTINCT p.* ';
        $sql .= 'FROM `tacos_projects` p ';
         // customer teams + whether current user is teamlead of one of them
        $sql .= 'LEFT JOIN `tacos_customers` c ON c.`id` = p.`customer_id` ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utc ON utc.`team_id` = ct.`team_id` AND utc.`user_id` = :teamleaderId1 AND utc.`teamlead` = 1 ';
        // project teams + whether current user is teamlead of one of them
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utp ON utp.`team_id` = pt.`team_id` AND utp.`user_id` = :teamleaderId2 AND utp.`teamlead` = 1 ';

        $sql .= 'WHERE (ct.`team_id` IS NULL OR utc.`user_id` IS NOT NULL) ';  // customer: either customer has no team OR user is member of a customer team
        $sql .= 'AND (pt.`project_id` IS NULL OR utp.`user_id` IS NOT NULL) '; // project: either project has no team OR user is member of a project team

        if (!is_null($visible)) {
            $sql .= 'AND p.`visible` = :visible ';
        }
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $params = array(
            'teamleaderId1' => $teamleaderId,
            'teamleaderId2' => $teamleaderId
        );
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();
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
    public function findAllProjectsWithTeamsCountAndCustomer(): array {
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
     * Visibility rules:
     *  - customer without team + project without team => visible to all
     *  - customer without team + project with team    => visible if user is member of a project team
     *  - customer with team    + project without team => visible if user is member of a customer team
     *  - customer with team    + project with team    => visible if user is member of at least one customer team AND at least one project team
     *
     * @param int $teamleaderId
     * @return array of Projects with Teams count and Customer
     */
    public function findAllProjectsWithTeamsCountAndCustomerByTeamleaderId(int $teamleaderId): array {
        $sql  = 'SELECT p.`id`, p.`name`, p.`color`, p.`number`, p.`comment`, p.`visible`, ';
        $sql .= 'c.`name` as customerName , c.`color` as customerColor, ';
        $sql .= 'COUNT(DISTINCT pt2.`team_id`) AS teamsCount ';
        $sql .= 'FROM `tacos_projects` p ';
        // customer teams + whether current user is teamlead of one of them
        $sql .= 'LEFT JOIN `tacos_customers` c ON c.`id` = p.`customer_id` ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utc ON utc.`team_id` = ct.`team_id` AND utc.`user_id` = :teamleaderId1 AND utc.`teamlead` = 1 ';
        // project teams + whether current user is teamlead of one of them
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utp ON utp.`team_id` = pt.`team_id` AND utp.`user_id` = :teamleaderId2 AND utp.`teamlead` = 1 ';
        // count all teams linked to the project
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt2 ON pt2.`project_id` = p.`id` ';

        $sql .= 'WHERE (ct.`team_id` IS NULL OR utc.`user_id` IS NOT NULL) ';  // customer: either customer has no team OR user is member of a customer team
        $sql .= 'AND (pt.`project_id` IS NULL OR utp.`user_id` IS NOT NULL) '; // project: either project has no team OR user is member of a project team

        $sql .= 'GROUP BY p.`id` ';
        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'teamleaderId1' => $teamleaderId,
            'teamleaderId2' => $teamleaderId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Find All Projects with Customer by User id
     *
     * Visibility rules:
     *  - customer without team + project without team => visible to all
     *  - customer without team + project with team    => visible if user is member of a project team
     *  - customer with team    + project without team => visible if user is member of a customer team
     *  - customer with team    + project with team    => visible if user is member of at least one customer team AND at least one project team
     *
     * @param int $userId
     * @return array of Projects with Customer
     */
    public function findAllProjectsWithCustomerByUserId(int $userId): array {
        $sql  = 'SELECT DISTINCT p.`id`, p.`name`, p.`color`, p.`number`, p.`comment`, p.`visible`, ';
        $sql .= 'c.`name` as customerName , c.`color` as customerColor ';
        $sql .= 'FROM `tacos_projects` p ';
        // customer teams + whether current user is member of one of them
        $sql .= 'LEFT JOIN `tacos_customers` c ON c.`id` = p.`customer_id` ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utc ON utc.`team_id` = ct.`team_id` AND utc.`user_id` = :userId1 ';
        // project teams + whether current user is member of one of them
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utp ON utp.`team_id` = pt.`team_id` AND utp.`user_id` = :userId2 ';

        $sql .= 'WHERE (ct.`team_id` IS NULL OR utc.`user_id` IS NOT NULL) ';  // customer: either customer has no team OR user is member of a customer team
        $sql .= 'AND (pt.`project_id` IS NULL OR utp.`user_id` IS NOT NULL) '; // project: either project has no team OR user is member of a project team

        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId1' => $userId,
            'userId2' => $userId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Find All Projects with Customer by Team id
     *
     * Visibility rules:
     *  - customer without team + project without team => visible to all
     *  - customer without team + project with team    => visible if user is member of a project team
     *  - customer with team    + project without team => visible if user is member of a customer team
     *  - customer with team    + project with team    => visible if user is member of at least one customer team AND at least one project team
     *
     * @param int $teamId
     * @return array of Projects with Customer
     */
    public function findAllProjectsWithCustomerByTeamId(int $teamId): array {
        $sql  = 'SELECT DISTINCT p.`id`, p.`name`, p.`color`, p.`number`, p.`comment`, p.`visible`, ';
        $sql .= 'c.`name` as customerName , c.`color` as customerColor ';
        $sql .= 'FROM `tacos_projects` p ';
        // customer teams
        $sql .= 'LEFT JOIN `tacos_customers` c ON c.`id` = p.`customer_id` ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        // project teams
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';

        $sql .= 'WHERE (ct.`team_id` IS NULL OR ct.`team_id` = :teamId1) ';  // customer: either customer has no team OR this team is attached to the customer
        $sql .= 'AND (pt.`project_id` IS NULL OR pt.`team_id` = :teamId2) '; // project: either project has no team OR this team is attached to the project

        $sql .= 'ORDER BY p.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'teamId1' => $teamId,
            'teamId2' => $teamId
        ]);

        return $stmt->fetchAll();
    }



    /**
     * Insert Project
     *
     * @param Project $project
     * @return lastInsertId or false
     */
    public function insert(Project $project): string|false {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_projects` (`id`, `customer_id`, `name`, `color`, `number`, `comment`, `start`, `end`, `last_activity`, `global_activities`, `visible`, `created_at`) VALUES (NULL, :customerId, :name, :color, :number, :comment, :start, :end, NULL, :globalActivities, :visible, :createdAt)');
            $res = $stmt->execute([
                'customerId'       => $project->getCustomerId(),
                'name'             => $project->getName(),
                'color'            => $project->getColor(),
                'number'           => $project->getNumber(),
                'comment'          => $project->getComment(),
                'start'            => $project->getStart(),
                'end'              => $project->getEnd(),
                'globalActivities' => $project->getGlobalActivities(),
                'visible'          => $project->getVisible(),
                'createdAt'        => $project->getCreatedAt()
            ]);

            if (!$res) {
                $this->logger->error(
                    '[ProjectRepository] Failed to insert project (execute returned false)',
                    [
                        'name'      => $project->getName(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return $this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            $this->logger->error(
                '[ProjectRepository] Failed to insert project (exception)',
                [
                    'name'              => $project->getName(),
                    'exception_class'   => $e::class,
                    'exception_message' => $e->getMessage(),
                    'exception_code'    => $e->getCode(),
                    'exception'         => $e,
                ]
            );
            return false;
        }
    }

    /**
     * Update Project
     *
     * @param Project $project
     * @return bool
     */
    public function updateProject(Project $project): bool {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_projects` SET `tacos_projects`.`customer_id` = :customerId, `tacos_projects`.`name` = :name, `tacos_projects`.`color` = :color, `tacos_projects`.`number` = :number, `tacos_projects`.`comment` = :comment, `tacos_projects`.`start` = :start, `tacos_projects`.`end` = :end, `tacos_projects`.`global_activities` = :globalActivities, `tacos_projects`.`visible` = :visible WHERE `tacos_projects`.`id` = :id');
            $res = $stmt->execute([
                'customerId'       => $project->getCustomerId(),
                'name'             => $project->getName(),
                'color'            => $project->getColor(),
                'number'           => $project->getNumber(),
                'comment'          => $project->getComment(),
                'start'            => $project->getStart(),
                'end'              => $project->getEnd(),
                'globalActivities' => $project->getGlobalActivities(),
                'visible'          => $project->getVisible(),
                'id'               => $project->getId()
            ]);
            if (!$res) {
                $this->logger->error(
                    '[ProjectRepository] Failed to update project (execute returned false)',
                    [
                        'id'        => $project->getId(),
                        'name'      => $project->getName(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[ProjectRepository] Failed to update project (exception)',
                [
                    'id'                => $project->getId(),
                    'name'              => $project->getName(),
                    'exception_class'   => $e::class,
                    'exception_message' => $e->getMessage(),
                    'exception_code'    => $e->getCode(),
                    'exception'         => $e,
                ]
            );
            return false;
        }
    }



    /**
     * Insert Teams
     *
     * @param int $projectId
     * @param array $data Array of teamIds
     * @return bool
     */
    public function insertTeams(int $projectId, array $data): bool {
        if ($data === []) {
            return true;
        }

        // cast & clean ids
        $teamIds = array_map(
            static function ($teamId): int {
                return (int) $teamId;
            },
            $data
        );
        $teamIds = array_unique($teamIds);
        $teamIds = array_values($teamIds);

        $startedTx = false;

        try {
            if (!$this->pdo->inTransaction()) {
                // Todo: move transaction to service
                $this->pdo->beginTransaction();
                $startedTx = true;
            }

            $stmt = $this->pdo->prepare('INSERT INTO `tacos_projects_teams` (`project_id`, `team_id`) VALUES (:project_id, :team_id)');

            foreach ($teamIds as $teamId) {
                $res = $stmt->execute([
                    'project_id' => $projectId,
                    'team_id'    => $teamId
                ]);

                if (!$res) {
                    $this->logger->error(
                        '[ProjectRepository] Failed to insert team link (execute returned false)',
                        [
                            'projectId'  => $projectId,
                            'teamId'     => $teamId,
                            'errorInfo'  => $stmt->errorInfo(),
                        ]
                    );

                    if ($startedTx) {
                        $this->pdo->rollBack();
                    }
                    return false;
                }
            }
            if ($startedTx) {
                $this->pdo->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error(
                '[ProjectRepository] Failed to insert teams link (exception)',
                [
                    'projectId'          => $projectId,
                    'teamIds'            => $teamIds,
                    'exception_class'    => $e::class,
                    'exception_message'  => $e->getMessage(),
                    'exception_code'     => $e->getCode(),
                    'exception'          => $e,
                ]
            );

            return false;
        }
    }

    /**
     * Update Teams
     *
     * @param int $projectId
     * @param array $data Array of teamIds
     * @return bool
     */
    public function updateTeams(int $projectId, array $data): bool {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('DELETE FROM `tacos_projects_teams` WHERE `tacos_projects_teams`.`project_id` = :projectId');
            $res = $stmt->execute([
                'projectId' => $projectId
            ]);

            if (!$res) {
                $this->logger->error(
                    '[ProjectRepository] Failed to delete project teams links (execute returned false)',
                    [
                        'projectId'  => $projectId,
                        'errorInfo'  => $stmt->errorInfo(),
                    ]
                );
                $this->pdo->rollBack();
                return false;
            }

            if (count($data) > 0) {
                if (!$this->insertTeams($projectId, $data)) {
                    $this->pdo->rollBack();
                    return false;
                }
            }
            $this->pdo->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error(
                '[ProjectRepository] Failed to update project teams links (exception)',
                [
                    'projectId'          => $projectId,
                    'teamCount'          => count($data),
                    'exception_class'    => $e::class,
                    'exception_message'  => $e->getMessage(),
                    'exception_code'     => $e->getCode(),
                    'exception'          => $e,
                ]
            );

            return false;
        }
    }



    /**
     * Insert Activities
     *
     * @param int $projectId
     * @param array $data Array of activityIds
     * @return bool
     */
    public function insertActivities(int $projectId, array $data): bool {
        if ($data === []) {
            return true;
        }

        // cast & clean ids
        $activityIds = array_map(
            static function ($activityId): int {
                return (int) $activityId;
            },
            $data
        );
        $activityIds = array_unique($activityIds);
        $activityIds = array_values($activityIds);

        $startedTx = false;

        try {
            if (!$this->pdo->inTransaction()) {
                // Todo: move transaction to service
                $this->pdo->beginTransaction();
                $startedTx = true;
            }

            $stmt = $this->pdo->prepare('INSERT INTO `tacos_projects_activities` (`project_id`, `activity_id`) VALUES (:project_id, :activity_id)');

            foreach ($activityIds as $activityId) {
                $res = $stmt->execute([
                    'project_id' => $projectId,
                    'activity_id' => $activityId
                ]);

                if (!$res) {
                    $this->logger->error(
                        '[ProjectRepository] Failed to insert activity link (execute returned false)',
                        [
                            'projectId'  => $projectId,
                            'activityId' => $activityId,
                            'errorInfo'  => $stmt->errorInfo(),
                        ]
                    );

                    if ($startedTx) {
                        $this->pdo->rollBack();
                    }
                    return false;
                }
            }

            if ($startedTx) {
                $this->pdo->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error(
                '[ProjectRepository] Failed to insert activities link (exception)',
                [
                    'projectId'          => $projectId,
                    'activityIds'        => $activityIds,
                    'exception_class'    => $e::class,
                    'exception_message'  => $e->getMessage(),
                    'exception_code'     => $e->getCode(),
                    'exception'          => $e,
                ]
            );

            return false;
        }
    }

    /**
     * Update Activities
     *
     * @param int $projectId
     * @param array $data Array of activityIds
     * @return bool
     */
    public function updateActivities(int $projectId, array $data): bool {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('DELETE FROM `tacos_projects_activities` WHERE `tacos_projects_activities`.`project_id` = :projectId');
            $res = $stmt->execute([
                'projectId' => $projectId
            ]);

            if (!$res) {
                $this->logger->error(
                    '[ProjectRepository] Failed to delete project activities links (execute returned false)',
                    [
                        'projectId' => $projectId,
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                $this->pdo->rollBack();
                return false;
            }

            if (count($data) > 0) {
                if (!$this->insertActivities($projectId, $data)) {
                    $this->pdo->rollBack();
                    return false;
                }
            }
            $this->pdo->commit();
            return true;

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error(
                '[ProjectRepository] Failed to update project activities links (exception)',
                [
                    'projectId'          => $projectId,
                    'activityCount'      => count($data),
                    'exception_class'    => $e::class,
                    'exception_message'  => $e->getMessage(),
                    'exception_code'     => $e->getCode(),
                    'exception'          => $e,
                ]
            );

            return false;
        }
    }




    /**
     * Creates Project object
     *
     * @param array $row
     * @return Entity\Project
     */
    protected function buildEntity(array $row): Project {
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
        $project->setGlobalActivities((int) $row['global_activities']);
        $project->setVisible((int) $row['visible']);
        $project->setCreatedAt(isset($row['created_at']) ? $row['created_at'] : null);

        return $project;
    }
}
