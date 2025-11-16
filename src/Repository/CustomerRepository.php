<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use App\Helper\SqlHelper;
use PDO;

final class CustomerRepository
{
    private $pdo;
    private $sqlHelper;

    public function __construct(PDO $pdo, SqlHelper $sqlHelper) {
        $this->pdo = $pdo;
        $this->sqlHelper = $sqlHelper;
    }

    /**
     * Find Customer by id
     *
     * @param int $id
     * @return Customer or false
     */
    public function find(int $id) {
        $stmt = $this->pdo->prepare('SELECT `tacos_customers`.* FROM `tacos_customers` WHERE `tacos_customers`.`id` = ? LIMIT 1');
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
     * Find Customer by id and by Teamleader id
     *
     * @param int $id
     * @return Customer or false
     */
    public function findOneByCustomerIdAndTeamleaderId(int $customerId, int $teamleaderId) {
        $sql  = 'SELECT `tacos_customers`.* ';
        $sql .= 'FROM `tacos_customers` ';
        $sql .= 'INNER JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` ';
        $sql .= 'INNER JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_customers_teams`.`team_id` AND `tacos_users_teams`.`user_id` = :teamleaderId AND `tacos_users_teams`.`teamlead` = 1 ';
        $sql .= 'WHERE `tacos_customers`.`id` = :customerId ';
        $sql .= 'LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'customerId' => $customerId,
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
     * Find Customers
     *
     * @return array of Customers
     */
    public function findAll() {
        $stmt = $this->pdo->prepare('SELECT `tacos_customers`.* FROM `tacos_customers` ORDER BY `tacos_customers`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Find Customers with Teams count and Projects count
     *
     * @return array of Customers with Teams count and Projects count
     */
    public function findAllCustomersWithTeamsCountAndProjectsCount() {
        $sql  = "SELECT `tacos_customers`.`id`, `tacos_customers`.`name`, `tacos_customers`.`color`, `tacos_customers`.`number`, `tacos_customers`.`visible`, ";
        $sql .= "COUNT(DISTINCT `tacos_customers_teams`.`team_id` ) AS teams, ";
        $sql .= "COUNT(DISTINCT `tacos_projects`.`id`) AS projects ";
        $sql .= "FROM `tacos_customers` ";
        $sql .= "LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` ";
        $sql .= "LEFT JOIN `tacos_teams` ON `tacos_teams`.`id` = `tacos_customers_teams`.`team_id` ";
        $sql .= "LEFT JOIN `tacos_projects` ON `tacos_projects`.`customer_id` = `tacos_customers`.`id` ";
        $sql .= "GROUP BY `tacos_customers`.`id`, `tacos_customers`.`name`, `tacos_customers`.`color`, `tacos_customers`.`number`, `tacos_customers`.`visible` ";
        $sql .= "ORDER BY `tacos_customers`.`name`";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }


    /**
     * Find Customers with Teams count and Projects count by User id
     *
     * @return array of Customers with Teams count and Projects count
     */
    public function findCustomersWithTeamsCountAndProjectsCountByUserId(int $userId) {
        $sql  = "SELECT `tacos_customers`.`id`, `tacos_customers`.`name`, `tacos_customers`.`color`, `tacos_customers`.`number`, `tacos_customers`.`visible`, ";
        $sql .= "COUNT(DISTINCT `tacos_users_teams`.`team_id` ) AS teams, ";
        $sql .= "COUNT(DISTINCT `tacos_projects_teams`.`project_id`) AS projects ";
        $sql .= "FROM `tacos_customers` ";
        // Customer teams
        $sql .= "LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` ";
        // Teams to which the user belongs
        $sql .= "LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_customers_teams`.`team_id` AND `tacos_users_teams`.`user_id` = :userId ";
        // Projects to which these teams belong
        $sql .= "LEFT JOIN `tacos_projects_teams` ON `tacos_projects_teams`.`team_id` = `tacos_users_teams`.`team_id` ";
        $sql .= "LEFT JOIN `tacos_projects` ON `tacos_projects`.`id` = `tacos_projects_teams`.`project_id` AND `tacos_projects`.`customer_id` = `tacos_customers`.`id` ";

        $sql .= "WHERE `tacos_users_teams`.`user_id` IS NOT NULL ";
        $sql .= "GROUP BY `tacos_customers`.`id`, `tacos_customers`.`name`, `tacos_customers`.`color`, `tacos_customers`.`number`, `tacos_customers`.`visible` ";
        $sql .= "ORDER BY `tacos_customers`.`name`";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Find Visible Customers
     *
     * @return array of Customers
     */
    public function findAllVisibleCustomers() {
        $stmt = $this->pdo->prepare('SELECT `tacos_customers`.* FROM `tacos_customers` WHERE `tacos_customers`.`visible` = 1 ORDER BY `tacos_customers`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Find All Customers in Teams. NOTE: unused
     *
     * @param array $teamsIds
     * @return array of Customers
     */
    public function findAllCustomersInTeams($teamsIds) {
        $customers = array();

        if (count($teamsIds) > 0) {
            [$teamsIN, $pdoParams] = $this->sqlHelper->buildInClause($teamsIds, 'teamsId', '`tacos_customers_teams`.`team_id`');

            $sql  = "SELECT `tacos_customers`.* FROM `tacos_customers` ";
            $sql .= "LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` ";
            $sql .= "WHERE {$teamsIN} ";
            $sql .= "GROUP BY `tacos_customers`.`id` ";
            $sql .= "ORDER BY `tacos_customers`.`name` ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($pdoParams);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $customers[$row['id']] = $this->buildEntity($row);
            }
        }

        return $customers;
    }

    /**
     * Find All visible Customers in Teams. NOTE: unused
     *
     * @param array $teamsIds
     * @return array of Customers
     */
    public function findAllVisibleCustomersInTeams($teamsIds) {
        $customers = array();

        if (count($teamsIds) > 0) {
            [$teamsIN, $pdoParams] = $this->sqlHelper->buildInClause($teamsIds, 'teamsId', '`tacos_customers_teams`.`team_id`');

            $sql  = "SELECT `tacos_customers`.* FROM `tacos_customers` ";
            $sql .= "LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` ";
            $sql .= "WHERE {$teamsIN} ";
            $sql .= "AND `tacos_customers`.`visible` = 1 ";
            $sql .= "GROUP BY `tacos_customers`.`id` ";
            $sql .= "ORDER BY `tacos_customers`.`name` ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($pdoParams);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $customers[$row['id']] = $this->buildEntity($row);
            }
        }

        return $customers;
    }

    /**
     * Find All Customers have teams. NOTE: unused
     *
     * @return array of Customers
     */
    public function findAllCustomersHaveTeams() {
        $stmt = $this->pdo->prepare('SELECT `tacos_customers`.* FROM `tacos_customers` LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` WHERE `tacos_customers_teams`.`team_id` IS NOT NULL GROUP BY `tacos_customers`.`id` ORDER BY `tacos_customers`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Find All visible Customers have teams
     *
     * @return array of Customers
     */
    public function findAllVisibleCustomersHaveTeams() {
        $stmt = $this->pdo->prepare('SELECT `tacos_customers`.* FROM `tacos_customers` LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` WHERE `tacos_customers_teams`.`team_id` IS NOT NULL AND `tacos_customers`.`visible` = 1 GROUP BY `tacos_customers`.`id` ORDER BY `tacos_customers`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Find All Customers not in a team
     *
     * @return array of Customers
     */
    public function findAllCustomersNotInTeam() {
        $stmt = $this->pdo->prepare('SELECT `tacos_customers`.* FROM `tacos_customers` LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` WHERE `tacos_customers_teams`.`team_id` IS NULL GROUP BY `tacos_customers`.`id` ORDER BY `tacos_customers`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Find All Visible Customers not in a team
     *
     * @return array of Customers
     */
    public function findAllVisibleCustomersNotInTeam() {
        $stmt = $this->pdo->prepare('SELECT `tacos_customers`.* FROM `tacos_customers` LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` WHERE `tacos_customers_teams`.`team_id` IS NULL AND `tacos_customers`.`visible` = 1 GROUP BY `tacos_customers`.`id` ORDER BY `tacos_customers`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Find All Customers by user Id
     *
     * @param int $userId
     * @return array of Customers
     */
    public function findAllCustomersByUserId(int $userId) {
        $sql  = 'SELECT `tacos_customers`.* ';
        $sql .= 'FROM `tacos_customers` ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_customers_teams`.`team_id` ';
        $sql .= 'LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` ';
        $sql .= 'WHERE `tacos_users`.`id` = :userId ';
        $sql .= 'GROUP BY `tacos_customers`.`id` ORDER BY `tacos_customers`.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
        ]);
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Find All Customers by user Id
     *
     * @param int $userId
     * @return array of Customers
     */
    public function findAllVisibleCustomersByUserId(int $userId) {
        $sql  = 'SELECT `tacos_customers`.* ';
        $sql .= 'FROM `tacos_customers` ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ON `tacos_users_teams`.`team_id` = `tacos_customers_teams`.`team_id` ';
        $sql .= 'LEFT JOIN `tacos_users` ON `tacos_users`.`id` = `tacos_users_teams`.`user_id` ';
        $sql .= 'WHERE `tacos_users`.`id` = :userId ';
        $sql .= 'AND `tacos_customers`.`visible` = 1 ';
        $sql .= 'GROUP BY `tacos_customers`.`id` ORDER BY `tacos_customers`.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId' => $userId,
        ]);
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Insert Customer
     *
     * @param Customer $customer
     * @return lastInsertId or false
     */
    public function insert(Customer $customer) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_customers` (`id`, `name`, `color`, `number`, `comment`, `visible`, `created_at`) VALUES (NULL, :name, :color, :number, :comment, :visible, :createdAt)');
            $res = $stmt->execute([
                'name' => $customer->getName(),
                'color' => $customer->getColor(),
                'number' => $customer->getNumber(),
                'comment' => $customer->getComment(),
                'visible' => $customer->getVisible(),
                'createdAt' => $customer->getCreatedAt()
            ]);
            return $this->pdo->lastInsertId();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update Customer
     * @param Customer $customer
     * @return bool
     */
    public function updateCustomer(Customer $customer) {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_customers` SET `tacos_customers`.`name` = :name, `tacos_customers`.`color` = :color, `tacos_customers`.`number` = :number, `tacos_customers`.`comment` = :comment, `tacos_customers`.`visible` = :visible WHERE `tacos_customers`.`id` = :id');
            $res = $stmt->execute([
                'name' => $customer->getName(),
                'color' => $customer->getColor(),
                'number' => $customer->getNumber(),
                'comment' => $customer->getComment(),
                'visible' => $customer->getVisible(),
                'id' => $customer->getId()

            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }




    /**
     * Insert Teams
     *
     * @param int $customerId
     * @param array $data
     * @return bool
     */
    public function insertTeams(int $customerId, $data) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_customers_teams` (`customer_id`, `team_id`) VALUES (:customer_id, :team_id)');
            foreach ($data as $teamId) {
                $stmt->execute([
                    'customer_id' => $customerId,
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
     * @param int $customerId
     * @param array $data
     * @return bool
     */
    public function updateTeams(int $customerId, $data) {
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_customers_teams` WHERE `tacos_customers_teams`.`customer_id` = :customer_id');
        $stmt->execute([
            'customer_id' => $customerId
        ]);
        if (count($data) > 0) {
            return $this->insertTeams($customerId, $data);
        }
        return true;
    }



    /**
     * Creates Customer object
     *
     * @param array $row
     * @return Entity\Customer
     */
    protected function buildEntity(array $row) {
        $customer = new Customer();
        $customer->setId($row['id']);
        $customer->setName($row['name']);
        $customer->setColor($row['color']);
        $customer->setNumber(isset($row['number']) ? $row['number'] : null);
        $customer->setComment(isset($row['comment']) ? $row['comment'] : null);
        $customer->setVisible($row['visible']);
        $customer->setCreatedAt(isset($row['created_at']) ? $row['created_at'] : null);

        return $customer;
    }
}
