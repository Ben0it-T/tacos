<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Helper\SqlHelper;
use Psr\Log\LoggerInterface;

use PDO;

final class UserRepository
{
    private PDO $pdo;
    private SqlHelper $sqlHelper;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, SqlHelper $sqlHelper, LoggerInterface $logger) {
        $this->pdo = $pdo;
        $this->sqlHelper = $sqlHelper;
        $this->logger = $logger;
    }

    /**
     * Find (enabled) User by id
     *
     * @param int $id
     * @return User entity or false
     */
    public function find(int $id): User|false {
        $sql = 'SELECT u.* FROM `tacos_users` u WHERE u.`id` = :id AND u.`enabled` = 1 LIMIT 1';
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
     * Find (enabled) User by identifier
     *
     * @param string $identifier
     * @return User entity or false
     */
    public function findOneByIdentifier(string $identifier): User|false {
        $sql  = 'SELECT u.* ';
        $sql .= 'FROM `tacos_users` u ';
        $sql .= 'WHERE (u.`username` = :username OR u.`email` = :email) ';
        $sql .= 'AND u.`enabled` = 1 ';
        $sql .= 'LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'username' => $identifier,
            'email' => $identifier
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
     * Find (enabled) User by token
     *
     * @param string $token
     * @param int $tokenLifetime
     * @return User entity or false
     */
    public function findOneByToken(string $token, int $tokenLifetime): User|false {
        $threshold = date("Y-m-d H:i:s", intval(time() - $tokenLifetime));

        $sql  = 'SELECT u.* ';
        $sql .= 'FROM `tacos_users` u ';
        $sql .= 'WHERE u.`password_request_token` = :token AND u.`password_request_date` > :threshold ';
        $sql .= 'AND u.`enabled` = 1 ';
        $sql .= 'LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'threshold' => $threshold,
            'token'     => $token,
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
     * Find User by username
     *
     * @param string $username
     * @return User entity or false
     */
    public function findOneByUsername(string $username): User|false {
        $sql  = 'SELECT u.* ';
        $sql .= 'FROM `tacos_users` u ';
        $sql .= 'WHERE u.`username` = :username ';
        $sql .= 'LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'username' => $username,
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
     * Find all Users
     *
     * @param ?int $enabled
     * @return array of User entities
     */
    public function findAll(?int $enabled = null): array {
        $sql  = 'SELECT u.* ';
        $sql .= 'FROM `tacos_users` u ';
        if (!is_null($enabled)) {
            $sql .= 'WHERE u.`enabled` = :enabled ';
        }
        $sql .= 'ORDER BY u.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $params = array();
        if (!is_null($enabled)) {
            $params['enabled'] = $enabled;
        }
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $users = array();
        foreach ($rows as $row) {
            $users[$row['id']] = $this->buildEntity($row);
        }

        return $users;
    }

    /**
     * Find all Users in Teams
     *
     * @param array list of teamsIds
     * @param ?int $enabled
     * @return array of User entities
     */
    public function findAllUsersInTeams(array $teamsIds, ?int $enabled = null): array {
        $users = array();

        if (count($teamsIds) > 0) {
            [$teamsIN, $pdoParams] = $this->sqlHelper->buildInClause($teamsIds, 'teamsId', 't.`team_id`');

            $sql  = 'SELECT DISTINCT u.* ';
            $sql .= 'FROM `tacos_users` u ';
            $sql .= 'INNER JOIN `tacos_users_teams` t ON t.`user_id` = u.`id` ';
            $sql .= 'WHERE '.$teamsIN.' ';
            if (!is_null($enabled)) {
                $sql .= 'AND u.`enabled` = :enabled ';
            }
            $sql .= 'ORDER BY u.`name`';

            $stmt = $this->pdo->prepare($sql);
            if (!is_null($enabled)) {
                $pdoParams['enabled'] = $enabled;
            }
            $stmt->execute($pdoParams);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $users[$row['id']] = $this->buildEntity($row);
            }

        }

        return $users;
    }

    /**
     * Find all Users by Team Id
     *
     * @param $teamId
     * @param $enabled
     * @return array of Users
     */
    public function findAllUsersByTeamId(int $teamId, ?int $enabled = null): array {
        $sql  = 'SELECT u.`id`, u.`name`, u.`enabled`, ut.`teamlead` ';
        $sql .= 'FROM `tacos_users` u ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`user_id` = u.`id` ';
        $sql .= 'WHERE ut.`team_id` = :teamId ';
        if (!is_null($enabled)) {
            $sql .= 'AND u.`enabled` = :enabled ';
        }
        $sql .= 'ORDER BY u.`name`';

        $stmt = $this->pdo->prepare($sql);

        $params = ['teamId' => $teamId];
        if (!is_null($enabled)) {
            $params['enabled'] = $enabled;
        }

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Find all Teamleaders by Team Id
     *
     * @param $teamId
     * @param $enabled
     * @return array of Teamleaders
     */
    public function findAllTeamleadersByTeamId(int $teamId, ?int $enabled = null): array {
        $sql  = 'SELECT u.`id`, u.`name`, u.`enabled` ';
        $sql .= 'FROM `tacos_users` u ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`user_id` = u.`id` ';
        $sql .= 'WHERE ut.`team_id` = :teamId AND ut.`teamlead` = 1 ';
        if (!is_null($enabled)) {
            $sql .= 'AND u.`enabled` = :enabled ';
        }
        $sql .= 'ORDER BY u.`name`';

        $stmt = $this->pdo->prepare($sql);

        $params = ['teamId' => $teamId];
        if (!is_null($enabled)) {
            $params['enabled'] = $enabled;
        }

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Find all Users with Teams count
     *
     * @return array of Users with Role and Teams count
     */
    public function findAllUsersWithTeamCount(): array {
        $sql  = 'SELECT u.`id`, u.`username`, u.`name`, u.`enabled` as enable, u.`last_login` as lastLogin, ';
        $sql .= 'r.`name` as role,  ';
        $sql .= 'COUNT(ut.`team_id`) AS teams ';
        $sql .= 'FROM `tacos_users` u ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`user_id` = u.`id` ';
        $sql .= 'LEFT JOIN `tacos_roles` r ON r.`id` = u.`role_id` ';
        $sql .= 'GROUP BY u.`id` ';
        $sql .= 'ORDER BY u.`name`';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }



    /**
     * Check if token exists
     *
     * @param string $token
     * @param int $tokenLifetime
     * @return bool
     */
    public function isTokenExists(string $token, int $tokenLifetime): bool {
        $threshold = date("Y-m-d H:i:s", intval(time() - $tokenLifetime));

        $sql  = 'SELECT count(*) as cnt ';
        $sql .= 'FROM `tacos_users` u ';
        $sql .= 'WHERE u.`password_request_token` = :token AND u.`password_request_date` > :threshold AND u.`enabled` = 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'threshold' => $threshold,
            'token'     => $token,
        ]);
        $cnt = $stmt->fetchColumn();

        return (int)$cnt === 1;
    }

    /**
     * Check if username exists
     *
     * @param string $username
     * @param int $id
     * @return bool
     */
    public function isUsernameExists(string $username, int $id = 0): bool {
        $sql  = 'SELECT count(*) as cnt ';
        $sql .= 'FROM `tacos_users` u ';
        $sql .= 'WHERE u.`username` = :username AND u.`id` != :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'id' => $id,
        ]);
        $cnt = $stmt->fetchColumn();

        if ($cnt > 0) {
            return true;
        }
        return false;
    }

    /**
     * Check if email exists
     *
     * @param string $email
     * @param int $id
     * @return bool
     */
    public function isEmailExists(string $email, int $id = 0): bool {
        $sql  = 'SELECT count(*) as cnt ';
        $sql .= 'FROM `tacos_users` u ';
        $sql .= 'WHERE u.`email` = :email AND u.`id` != :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'email' => $email,
            'id' => $id,
        ]);
        $cnt = $stmt->fetchColumn();

        if ($cnt > 0) {
            return true;
        }
        return false;
    }



    /**
     * Create User password request
     *
     * @param User $user
     * @return bool
     */
    public function setUserPasswordRequest(User $user): bool {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`password_request_date` = :requestDate, `tacos_users`.`password_request_token` = :requestToken WHERE `tacos_users`.`id` = :userId AND `tacos_users`.`enabled` = 1');
            $res = $stmt->execute([
                'requestDate'  => $user->getRequestDate(),
                'requestToken' => $user->getRequestToken(),
                'userId'       => $user->getId()
            ]);

            if (!$res) {
                $this->logger->error(
                    '[UserRepository] Failed to set user password request (execute returned false)',
                    [
                        'userId'    => $user->getId(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[UserRepository] Failed to set user password request (exception)',
                [
                    'userId'            => $user->getId(),
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
     * Unset User password request
     *
     * @param User $user
     * @return bool
     */
    public function unsetUserPasswordRequest(User $user): bool {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`password_request_date` = NULL, `tacos_users`.`password_request_token` = NULL WHERE `tacos_users`.`id` = :id AND `tacos_users`.`enabled` = 1');
            $res = $stmt->execute([
                'id' => $user->getId()
            ]);

            if (!$res) {
                $this->logger->error(
                    '[UserRepository] Failed to unset user password request (execute returned false)',
                    [
                        'userId'    => $user->getId(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[UserRepository] Failed to unset user password request (exception)',
                [
                    'userId'            => $user->getId(),
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
     * Unset Users password requests
     *
     * @param int $lifetime
     * @return bool
     */
    public function unsetUsersPasswordRequests(int $lifetime): bool {
        $threshold = date("Y-m-d H:i:s", time() - intval($lifetime));
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`password_request_date` = NULL, `tacos_users`.`password_request_token` = NULL WHERE `tacos_users`.`password_request_date` < :threshold AND `tacos_users`.`password_request_token` IS NOT NULL');
            $res = $stmt->execute([
                'threshold' => $threshold
            ]);

            if (!$res) {
                $this->logger->error(
                    '[UserRepository] Failed to unset users password requests (execute returned false)',
                    [
                        'threshold' => $threshold,
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[UserRepository] Failed to unset users password requests (exception)',
                [
                    'threshold'         => $threshold,
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
     * Insert User
     *
     * @param User $user
     * @return lastInsertId or false
     */
    public function insert(User $user): string|false {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_users` (`id`, `username`, `name`, `email`, `password`, `enabled`, `registration_date`, `role_id`, `last_login`, `password_request_token`, `password_request_date`) VALUES (NULL, :username, :name, :email, :password, :enabled, :registrationDate, :role, NULL, NULL, NULL)');
            $res = $stmt->execute([
                'username'         => $user->getUsername(),
                'name'             => $user->getName(),
                'email'            => $user->getEmail(),
                'password'         => $user->getPassword(),
                'enabled'          => 1,
                'registrationDate' => $user->getRegistrationDate(),
                'role'             => $user->getRole()
            ]);

            if (!$res) {
                $this->logger->error(
                    '[UserRepository] Failed to insert user (execute returned false)',
                    [
                        'name'      => $user->getName(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return $this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            $this->logger->error(
                '[UserRepository] Failed to insert user (exception)',
                [
                    'name'              => $user->getName(),
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
     * Update User profile (name, username, email)
     *
     * @param User $user
     * @return bool
     */
    public function updateUserProfile(User $user): bool {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`name` = :name, `tacos_users`.`username` = :username, `tacos_users`.`email` = :email WHERE `tacos_users`.`id` = :id AND `tacos_users`.`enabled` = 1');
            $res = $stmt->execute([
                'name' => $user->getName(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'id' => $user->getId()
            ]);

            if (!$res) {
                $this->logger->error(
                    '[UserRepository] Failed to update user profile (execute returned false)',
                    [
                        'id'        => $user->getId(),
                        'name'      => $user->getName(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[UserRepository] Failed to update user profile (exception)',
                [
                    'id'                => $user->getId(),
                    'name'              => $user->getName(),
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
     * Update User
     *
     * @param User $user
     * @return bool
     */
    public function updateUser(User $user): bool {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`name` = :name, `tacos_users`.`username` = :username, `tacos_users`.`email` = :email, `tacos_users`.`enabled` = :enabled, `tacos_users`.`role_id` = :roleId WHERE `tacos_users`.`id` = :id');
            $res = $stmt->execute([
                'name'     => $user->getName(),
                'username' => $user->getUsername(),
                'email'    => $user->getEmail(),
                'enabled'  => $user->getEnabled(),
                'roleId'   => $user->getRole(),
                'id'       => $user->getId()
            ]);

            if (!$res) {
                $this->logger->error(
                    '[UserRepository] Failed to update user (execute returned false)',
                    [
                        'id'        => $user->getId(),
                        'name'      => $user->getName(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[UserRepository] Failed to update user (exception)',
                [
                    'id'                => $user->getId(),
                    'name'              => $user->getName(),
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
     * Update User password hash
     *
     * @param User
     * @return bool
     */
    public function updatePasswordHash(User $user): bool {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`password` = :password WHERE `tacos_users`.`id` = :id AND `tacos_users`.`enabled` = 1');
            $res = $stmt->execute([
                'password' => $user->getPassword(),
                'id'       => $user->getId()
            ]);

            if (!$res) {
                $this->logger->error(
                    '[UserRepository] Failed to update user password hash (execute returned false)',
                    [
                        'id'        => $user->getId(),
                        'name'      => $user->getName(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[UserRepository] Failed to update user password hash (exception)',
                [
                    'id'                => $user->getId(),
                    'name'              => $user->getName(),
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
     * Update User last login
     *
     * @param User
     * @return bool
     */
    public function updateUserLastLogin(User $user): bool {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`last_login` = :lastLogin WHERE `tacos_users`.`id` = :id AND `tacos_users`.`enabled` = 1');
            $res = $stmt->execute([
                'lastLogin' => $user->getLastLogin(),
                'id'        => $user->getId()
            ]);

            if (!$res) {
                $this->logger->error(
                    '[UserRepository] Failed to update user last login (execute returned false)',
                    [
                        'id'        => $user->getId(),
                        'name'      => $user->getName(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[UserRepository] Failed to update user last login (exception)',
                [
                    'id'                => $user->getId(),
                    'name'              => $user->getName(),
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
     * Creates User object
     *
     * @param array $row
     * @return Entity\User
     */
    private function buildEntity(array $row): User {
        $user = new User();
        $user->setId($row['id']);
        $user->setUsername($row['username']);
        $user->setName($row['name']);
        $user->setEmail($row['email']);
        $user->setPassword($row['password']);
        $user->setEnabled((int) $row['enabled']);
        $user->setRegistrationDate(isset($row['registration_date']) ? $row['registration_date'] : null);
        $user->setRole($row['role_id']);
        $user->setLastLogin(isset($row['last_login']) ? $row['last_login'] : null);
        $user->setRequestToken(isset($row['password_request_token']) ? $row['password_request_token'] : null);
        $user->setRequestDate(isset($row['password_request_date']) ? $row['password_request_date'] : null);

        return $user;
    }
}
