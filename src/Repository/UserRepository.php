<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use PDO;

final class UserRepository
{
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Find User by id
     *
     * @param int $id
     * @return User or false
     */
    public function find(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_users` WHERE `tacos_users`.`id` = ? AND `tacos_users`.`enabled` = 1');
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
     * Find User by identifier
     *
     * @param string $identifier
     * @return User or false
     */
    public function findOneByIdentifier(string $identifier) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_users` WHERE (`tacos_users`.`username` = :username OR `tacos_users`.`email` = :email) AND `tacos_users`.`enabled` = 1');
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
     * Find User by token
     *
     * @param string $token
     * @param int $tokenLifetime
     * @return User or false
     */
    public function findOneByToken(string $token, int $tokenLifetime) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_users` WHERE UNIX_TIMESTAMP(`tacos_users`.`password_request_date`) > :tokenLifetime AND `tacos_users`.`password_request_token` = :token AND `tacos_users`.`enabled` = 1');
        $stmt->execute([
            'tokenLifetime' => intval($tokenLifetime),
            'token'         => $token,
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
     * @return User or false
     */
    public function findOneByUsername(string $username) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_users` WHERE `tacos_users`.`username` = :username');
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
     * @return array of Users
     */
    public function findAll() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_users` ORDER BY `tacos_users`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $users = array();
        foreach ($rows as $row) {
            $users[$row['id']] = $this->buildEntity($row);
        }

        return $users;
    }

    /**
     * Find all enabled Users
     *
     * @return array of Users
     */
    public function findAllEnabled() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_users` WHERE `tacos_users`.`enabled` = 1 ORDER BY `tacos_users`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $users = array();
        foreach ($rows as $row) {
            $users[$row['id']] = $this->buildEntity($row);
        }

        return $users;
    }

    /**
     * Find all enabled Users in Teams
     *
     * @param array list of teamsIds
     * @return array of Users
     */
    public function findAllEnabledUsersInTeams($teamsIds) {
        $users = array();
        if (count($teamsIds) > 0) {
            $in = str_repeat('?,', count($teamsIds) - 1) . '?';
            // distinct users ?
            $stmt = $this->pdo->prepare("SELECT `tacos_users`.* FROM `tacos_users_teams` LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` WHERE `tacos_users_teams`.`team_id` IN ($in) AND `tacos_users`.`enabled` = 1 GROUP BY `tacos_users`.`id` ORDER BY `tacos_users`.`name`");
            $stmt->execute($teamsIds);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $users[$row['id']] = $this->buildEntity($row);
            }
        }

        return $users;
    }



    /**
     * Get number of teams for user
     *
     * @param int $userId
     * @return int number of teams for user
     */
    public function getNbOfTeamsForUser($userId) {
        $stmt = $this->pdo->prepare('SELECT count(*) as cnt FROM `tacos_users_teams` WHERE `tacos_users_teams`.`user_id` = :userId');
        $stmt->execute([
            'userId' => $userId,
        ]);
        return $stmt->fetchColumn();
    }

    /**
     * Get teams for user
     *
     * @param int userId
     * @return array list of Teams
     */
    public function getTeamsForUser(int $userId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_teams`.`id` as teamId, `tacos_teams`.`name` as teamName, `tacos_teams`.`color` as teamColor, `tacos_users_teams`.`teamlead` as teamlead FROM `tacos_users_teams` LEFT JOIN `tacos_teams` ON `tacos_teams`.`id` = `tacos_users_teams`.`team_id` WHERE `tacos_users_teams`.`user_id` = :userId ORDER BY name');
        $stmt->execute([
            'userId' => $userId,
        ]);
        return $stmt->fetchAll();
    }



    /**
     * Check if token exists
     *
     * @param string $token
     * @param int $tokenLifetime
     * @return bool
     */
    public function isTokenExists(string $token, int $tokenLifetime) {
        $stmt = $this->pdo->prepare('SELECT count(*) as cnt FROM `tacos_users` WHERE UNIX_TIMESTAMP(`tacos_users`.`password_request_date`) > :tokenLifetime AND `tacos_users`.`password_request_token` = :token AND `tacos_users`.`enabled` = 1');
        $stmt->execute([
            'tokenLifetime' => intval($tokenLifetime),
            'token'         => $token,
        ]);
        $cnt = $stmt->fetchColumn();

        if ($cnt === 1) {
            return true;
        }
        return false;
    }

    /**
     * Check if username exists
     *
     * @param string $username
     * @param int $id
     * @return bool
     */
    public function isUsernameExists(string $username, int $id = 0) {
        $stmt = $this->pdo->prepare('SELECT count(*) as cnt FROM `tacos_users` WHERE `tacos_users`.`username` = :username AND `tacos_users`.`id` != :id');
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
    public function isEmailExists(string $email, int $id = 0) {
        $stmt = $this->pdo->prepare('SELECT count(*) as cnt FROM `tacos_users` WHERE `tacos_users`.`email` = :email AND `tacos_users`.`id` != :id');
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
     * @param User
     */
    public function setUserPasswordRequest(User $user): void {
        $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`password_request_date` = :requestDate, `tacos_users`.`password_request_token` = :requestToken WHERE `tacos_users`.`id` = :userId AND `tacos_users`.`enabled` = 1');
        $stmt->execute([
            'requestDate'  => $user->getRequestDate(),
            'requestToken' => $user->getRequestToken(),
            'userId'       => $user->getId()
        ]);
    }

    /**
     * Unset User password request
     *
     * @param User
     */
    public function unsetUserPasswordRequest(User $user): void {
        $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`password_request_date` = NULL, `tacos_users`.`password_request_token` = NULL WHERE `tacos_users`.`id` = :id AND `tacos_users`.`enabled` = 1');
        $stmt->execute([
            'id' => $user->getId()
        ]);
    }

    /**
     * Unset Users password requests
     *
     * @param int $lifetime
     */
    public function unsetUsersPasswordRequests(int $lifetime): void {
        $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`password_request_date` = NULL, `tacos_users`.`password_request_token` = NULL WHERE UNIX_TIMESTAMP(`tacos_users`.`password_request_date`) < :lifetime');
        $stmt->execute([
            'lifetime' => $lifetime
        ]);
    }



    /**
     * Insert User
     *
     * @param User $user
     * @return bool
     */
    public function insert(User $user) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_users` (`id`, `username`, `name`, `email`, `password`, `enabled`, `registration_date`, `role_id`, `last_login`, `password_request_token`, `password_request_date`) VALUES (NULL, :username, :name, :email, :password, :enabled, :registrationDate, :role, NULL, NULL, NULL)');
            $res = $stmt->execute([
                'username' => $user->getUsername(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'enabled' => '1',
                'registrationDate' => $user->getRegistrationDate(),
                'role' => $user->getRole()
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update User profile (name, username, email)
     *
     * @param User $user
     * @return bool
     */
    public function updateUserProfile(User $user) {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`name` = :name, `tacos_users`.`username` = :username, `tacos_users`.`email` = :email WHERE `tacos_users`.`id` = :id AND `tacos_users`.`enabled` = 1');
            $res = $stmt->execute([
                'name' => $user->getName(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'id' => $user->getId()
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update User
     *
     * @param User $user
     * @return bool
     */
    public function updateUser(User $user) {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`name` = :name, `tacos_users`.`username` = :username, `tacos_users`.`email` = :email, `tacos_users`.`enabled` = :enabled, `tacos_users`.`role_id` = :roleId WHERE `tacos_users`.`id` = :id');
            $res = $stmt->execute([
                'name' => $user->getName(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'enabled' => $user->getEnabled(),
                'roleId' => $user->getRole(),
                'id' => $user->getId()
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update User password hash
     *
     * @param User
     */
    public function updatePasswordHash(User $user): void {
        $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`password` = :password WHERE `tacos_users`.`id` = :id AND `tacos_users`.`enabled` = 1');
        $stmt->execute([
            'password' => $user->getPassword(),
            'id' => $user->getId()
        ]);
    }

    /**
     * Update User last login
     *
     * @param User
     */
    public function updateUserLastLogin(User $user): void {
        $stmt = $this->pdo->prepare('UPDATE `tacos_users` SET `tacos_users`.`last_login` = :lastLogin WHERE `tacos_users`.`id` = :id AND `tacos_users`.`enabled` = 1');
        $stmt->execute([
            'lastLogin' => $user->getLastLogin(),
            'id' => $user->getId()
        ]);
    }



    /**
     * Creates User object
     *
     * @param array $row
     * @return Entity\User
     */
    private function buildEntity(array $row) {
        $user = new User();
        $user->setId($row['id']);
        $user->setUsername($row['username']);
        $user->setName($row['name']);
        $user->setEmail($row['email']);
        $user->setPassword($row['password']);
        $user->setEnabled($row['enabled']);
        $user->setRegistrationDate(isset($row['registration_date']) ? $row['registration_date'] : null);
        $user->setRole($row['role_id']);
        $user->setLastLogin(isset($row['last_login']) ? $row['last_login'] : null);
        $user->setRequestToken(isset($row['password_request_token']) ? $row['password_request_token'] : null);
        $user->setRequestDate(isset($row['password_request_date']) ? $row['password_request_date'] : null);

        return $user;
    }
}
