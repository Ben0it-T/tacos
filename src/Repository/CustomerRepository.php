<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use PDO;

final class CustomerRepository
{
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Find Customer by id
     *
     * @param int $id
     * @return Customer or false
     */
    public function find(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_customers` WHERE `tacos_customers`.`id` = ?');
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
     * Find Customers
     *
     * @return array of Customers
     */
    public function findAll() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_customers` ORDER BY `tacos_customers`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Find Visible Customers
     *
     * @return array of Customers
     */
    public function findAllVisibleCustomers() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_customers` WHERE `tacos_customers`.`visible` = 1 ORDER BY `tacos_customers`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Find All Customers in Teams
     *
     * @param array $teamsIds
     * @return array of Customers
     */
    public function findAllCustomersInTeams($teamsIds) {
        $customers = array();
        if (count($teamsIds) > 0) {
            $in = str_repeat('?,', count($teamsIds) - 1) . '?';
            $stmt = $this->pdo->prepare("SELECT `tacos_customers`.* FROM `tacos_customers` LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` WHERE `tacos_customers_teams`.`team_id` IN ($in) GROUP BY `tacos_customers`.`id` ORDER BY `tacos_customers`.`name` ASC");
            $stmt->execute($teamsIds);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $customers[$row['id']] = $this->buildEntity($row);
            }
        }

        return $customers;
    }

    /**
     * Find All visible Customers in Teams
     *
     * @param array $teamsIds
     * @return array of Customers
     */
    public function findAllVisibleCustomersInTeams($teamsIds) {
        $customers = array();
        if (count($teamsIds) > 0) {
            $in = str_repeat('?,', count($teamsIds) - 1) . '?';
            $stmt = $this->pdo->prepare("SELECT `tacos_customers`.* FROM `tacos_customers` LEFT JOIN `tacos_customers_teams` ON `tacos_customers_teams`.`customer_id` = `tacos_customers`.`id` WHERE `tacos_customers_teams`.`team_id` IN ($in) AND `tacos_customers`.`visible` = 1 GROUP BY `tacos_customers`.`id` ORDER BY `tacos_customers`.`name` ASC");
            $stmt->execute($teamsIds);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $customers[$row['id']] = $this->buildEntity($row);
            }
        }

        return $customers;
    }

    /**
     * Find All Customers have teams
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
     * Get number of teams for customer
     *
     * @param int $customerId
     * @return int number of Teams
     */
    public function getNbOfTeamsForCustomer(int $customerId) {
        $stmt = $this->pdo->prepare('SELECT count(*) as cnt FROM `tacos_customers_teams` WHERE `tacos_customers_teams`.`customer_id` = :customerId');
        $stmt->execute([
            'customerId' => $customerId,
        ]);
        return $stmt->fetchColumn();
    }

    /**
     * Get Customer Teams
     *
     * @param int customerId
     * @return array list of Teams
     */
    public function getTeamsForCustomer(int $customerId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_customers_teams`.`team_id` as teamId, `tacos_teams`.`name` as name FROM `tacos_customers_teams` LEFT JOIN `tacos_teams` ON `tacos_teams`.`id` = `tacos_customers_teams`.`team_id` WHERE `tacos_customers_teams`.`customer_id` = :customerId ORDER BY name');
        $stmt->execute([
            'customerId' => $customerId,
        ]);
        return $stmt->fetchAll();
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
