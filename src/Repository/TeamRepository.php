<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use Psr\Log\LoggerInterface;

use PDO;

final class TeamRepository
{
    private PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, LoggerInterface $logger) {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    /**
     * Find Team by id
     *
     * @param int $id
     * @return Team entity or false
     */
    public function find(int $id): Team|false {
        $sql = 'SELECT t.* FROM `tacos_teams` t WHERE t.`id` = :id LIMIT 1';
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
     * Find Team by id and teamleader id
     *
     * @param int $teamId
     * @param int $teamleaderId
     * @return Team entity or false
     */
    public function findOneByIdAndTeamleaderId(int $teamId, int $teamleaderId): Team|false {
        $sql  = 'SELECT t.* ';
        $sql .= 'FROM `tacos_teams` t ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = t.`id` ';
        $sql .= 'WHERE t.`id` = :teamId ';
        $sql .= 'AND ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';
        $sql .= 'LIMIT 1';

        $stmt = $this->pdo->prepare($sql);

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
     * @return array of Team entities
     */
    public function findAll(): array {
        $sql = 'SELECT t.* FROM `tacos_teams` t ORDER BY t.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $teams = array();
        foreach ($rows as $row) {
            $teams[$row['id']] = $this->buildEntity($row);
        }

        return $teams;
    }

    /**
     * Find all Teams by activity id
     *
     * @param int $activityId
     * @return array of Team entities
     */
    public function findAllByActivityId(int $activityId): array {
        $sql  = 'SELECT t.* ';
        $sql .= 'FROM `tacos_teams` t ';
        $sql .= 'LEFT JOIN `tacos_activities_teams` at ON at.`team_id` = t.`id` ';
        $sql .= 'WHERE at.`activity_id` = :activityId ';
        $sql .= 'ORDER BY t.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
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
     * Find all Teams by customer id
     *
     * @param int $customerId
     * @return array of Team entities
     */
    public function findAllByCustomerId(int $customerId): array {
        $sql  = 'SELECT t.* ';
        $sql .= 'FROM `tacos_teams` t ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`team_id` = t.`id` ';
        $sql .= 'WHERE ct.`customer_id` = :customerId ';
        $sql .= 'ORDER BY t.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
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
     * Find all Teams by project id
     *
     * @param int $projectId
     * @return array of Team entities
     */
    public function findAllByProjectId(int $projectId): array {
        $sql  = 'SELECT t.* ';
        $sql .= 'FROM `tacos_teams` t ';
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`team_id` = t.`id` ';
        $sql .= 'WHERE pt.`project_id` = :projectId ';
        $sql .= 'ORDER BY t.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
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
     * Find all Teams by user id
     *
     * @param int $userId
     * @return array of Team entities
     */
    public function findAllByUserId(int $userId): array {
        $sql  = 'SELECT t.* ';
        $sql .= 'FROM `tacos_teams` t ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = t.`id` ';
        $sql .= 'WHERE ut.`user_id` = :userId ';
        $sql .= 'ORDER BY t.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
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
     * Find all Teams by teamleader id
     *
     * @param int $teamleaderId
     * @return array of Team entities
     */
    public function findAllByTeamleaderId(int $teamleaderId): array {
        $sql  = 'SELECT t.* ';
        $sql .= 'FROM `tacos_teams` t ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = t.`id` ';
        $sql .= 'WHERE ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';
        $sql .= 'ORDER BY t.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
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
    public function isTeamNameExists(string $name, int $id = 0): bool {
        $sql  = 'SELECT count(*) as cnt ';
        $sql .= 'FROM `tacos_teams` t ';
        $sql .= 'WHERE t.`name` = :name AND t.`id` != :id';

        $stmt = $this->pdo->prepare($sql);
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
     * Find all Teams with teamlead by user id
     *
     * @param int $userId
     * @return array of Team entities
     */
    public function findAllTeamsWithTeamleadByUserId(int $userId): array {
        $sql  = 'SELECT t.`id`, t.`name`, t.`color`, ut.`teamlead` ';
        $sql .= 'FROM `tacos_teams` t ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = t.`id` ';
        $sql .= 'WHERE ut.`user_id` = :userId ';
        $sql .= 'ORDER BY t.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Find all Teams with Users count and Teamleaders
     *
     * @return array of Teams with Users count and Teamleaders
     */
    public function findAllTeamsWithUserCountAndTeamleads(): array {
        $sql  = "SELECT t.`id`, t.`name`, t.`color`, ";
        $sql .= "COUNT(DISTINCT ut.`user_id`) AS members, ";
        $sql .= "GROUP_CONCAT(DISTINCT IF(ut.`teamlead` = 1, u.`name`, NULL) ORDER BY u.`name` SEPARATOR ', ') AS teamleaders ";
        $sql .= "FROM `tacos_teams` t ";
        $sql .= "LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = t.`id` ";
        $sql .= "LEFT JOIN `tacos_users` u ON u.`id` = ut.`user_id` ";
        $sql .= "GROUP BY t.`id`, t.`name`, t.`color` ";
        $sql .= "ORDER BY t.`name`";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Find all Teams with Users count and Teamleaders by Teamleader id
     *
     * @return array of Teams with Users count and Teamleaders
     */
    public function findAllTeamsWithUserCountAndTeamleadsByTeamleaderId(int $teamleaderId): array {
        $sql  = "SELECT t.`id`, t.`name`, t.`color`, ";
        $sql .= "COUNT(DISTINCT ut1.`user_id`) AS members, ";
        $sql .= "GROUP_CONCAT(DISTINCT IF(ut1.`teamlead` = 1, `tacos_users`.`name`, NULL) ORDER BY `tacos_users`.`name` SEPARATOR ', ') AS teamleaders ";
        $sql .= "FROM `tacos_teams` t ";
        $sql .= "LEFT JOIN `tacos_users_teams` ut1 ON ut1.`team_id` = t.`id` ";
        $sql .= "LEFT JOIN `tacos_users` ON `tacos_users`.`id` = ut1.`user_id` ";

        $sql .= "INNER JOIN `tacos_users_teams` ut_tl ON ut_tl.`team_id` = t.`id` ";
        $sql .= "AND ut_tl.`user_id` = :teamleaderId ";
        $sql .= "AND ut_tl.`teamlead` = 1 ";

        $sql .= "GROUP BY t.`id`, t.`name`, t.`color` ";
        $sql .= "ORDER BY t.`name`";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'teamleaderId' => $teamleaderId
        ]);

        return $stmt->fetchAll();
    }



    /**
     * Insert Team
     *
     * @param Team $team
     * @return string|false Last insert ID on success, false on failure
     */
    public function insert(Team $team): string|false {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_teams` (`id`, `name`, `color`) VALUES (NULL, :name, :color)');
            $res = $stmt->execute([
                'name'  => $team->getName(),
                'color' => $team->getColor()
            ]);
            if (!$res) {
                $this->logger->error(
                    '[TeamRepository] Failed to insert team (execute returned false)',
                    [
                        'name'      => $team->getName(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return $this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            $this->logger->error(
                '[TeamRepository] Failed to insert team (exception)',
                [
                    'name'              => $team->getName(),
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
     * Update Team
     *
     * @param Team $team
     * @return bool
     */
    public function updateTeam(Team $team): bool {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_teams` SET `tacos_teams`.`name` = :name, `tacos_teams`.`color` = :color WHERE `tacos_teams`.`id` = :id');
            $res = $stmt->execute([
                'name' => $team->getName(),
                'color' => $team->getColor(),
                'id' => $team->getId()
            ]);
            if (!$res) {
                $this->logger->error(
                    '[TeamRepository] Failed to update team (execute returned false)',
                    [
                        'id'        => $team->getId(),
                        'name'      => $team->getName(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[TeamRepository] Failed to update team (exception)',
                [
                    'id'                => $team->getId(),
                    'name'              => $team->getName(),
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
     * Insert Members
     *
     * @param int $teamId
     * @param array $data Array of userIds
     * @return bool
     */
    public function insertMembers(int $teamId, array $data): bool {
        if ($data === []) {
            return true;
        }

        $startedTx = false;

        try {
            if (!$this->pdo->inTransaction()) {
                // Todo: move transaction to service
                $this->pdo->beginTransaction();
                $startedTx = true;
            }

            $stmt = $this->pdo->prepare('INSERT INTO `tacos_users_teams` (`user_id`, `team_id`, `teamlead`) VALUES (:user_id, :team_id, :teamlead)');

            foreach ($data as $userId => $member) {
                $res = $stmt->execute([
                    'user_id'  => $userId,
                    'team_id'  => $teamId,
                    'teamlead' => (int) $member['teamlead']
                ]);

                if (!$res) {
                    $this->logger->error(
                        '[TeamRepository] Failed to insert user link (execute returned false)',
                        [
                            'teamId'    => $teamId,
                            'userId'    => $userId,
                            'errorInfo' => $stmt->errorInfo(),
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
                '[TeamRepository] Failed to insert users link (exception)',
                [
                    'teamId'             => $teamId,
                    'userIds'            => array_keys($data),
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
     * Update Members
     *
     * @param int $teamId
     * @param array $data Array of userIds
     * @return bool
     */
    public function updateMembers(int $teamId, array $data): bool {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('DELETE FROM `tacos_users_teams` WHERE `tacos_users_teams`.`team_id` = :team_id');
            $res = $stmt->execute([
                'team_id' => $teamId
            ]);

            if (!$res) {
                $this->logger->error(
                    '[TeamRepository] Failed to delete team users links (execute returned false)',
                    [
                        'teamId'    => $teamId,
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                $this->pdo->rollBack();
                return false;
            }

            if (count($data) > 0) {
                if (!$this->insertMembers($teamId, $data)) {
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
                '[TeamRepository] Failed to update team users links (exception)',
                [
                    'teamId'             => $teamId,
                    'userCount'          => count($data),
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
     * Creates Team object
     *
     * @param array<string, mixed> $row
     * @return Team
     */
    private function buildEntity(array $row): Team {
        $team = new Team();
        $team->setId($row['id']);
        $team->setName($row['name']);
        $team->setColor(isset($row['color']) ? $row['color'] : null);

        return $team;
    }
}
