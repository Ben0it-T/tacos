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
    public function find(int $id): Customer|false {
        $sql = 'SELECT c.* FROM `tacos_customers` c WHERE c.`id` = :id LIMIT 1';
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
     * Find Customer by id and by User id
     *
     * @param int $id
     * @param int $userId
     * @return Customer or false
     */
    public function findOneByIdAndUserId(int $customerId, int $userId): Customer|false {
        $sql  = 'SELECT c.* ';
        $sql .= 'FROM `tacos_customers` c ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = ct.`team_id` AND ut.`user_id` = :userId ';
        $sql .= 'WHERE c.`id` = :customerId AND (ut.`user_id` IS NOT NULL OR ct.`team_id` IS NULL) ';
        $sql .= 'LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'customerId' => $customerId,
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
     * Find Customer by id and by Teamleader id
     * Note : accepts customers without a team
     *
     * @param int $id
     * @param int $teamleaderId
     * @return Customer or false
     */
    public function findOneByIdAndTeamleaderId(int $customerId, int $teamleaderId): Customer|false {
        $sql  = 'SELECT c.* ';
        $sql .= 'FROM `tacos_customers` c ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = ct.`team_id` AND ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';
        $sql .= 'WHERE c.`id` = :customerId AND (ut.`user_id` IS NOT NULL OR ct.`team_id` IS NULL) ';
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
     * Find Customer by id by id user id is teamleader
     * Note : requires teamlead on at least one team
     *
     * @param int $id
     * @param int $teamleaderId
     * @return Customer or false
     */
    public function findOneByIdAndTeamleaderIdStrict(int $customerId, int $teamleaderId): Customer|false {
        $sql  = 'SELECT c.* ';
        $sql .= 'FROM `tacos_customers` c ';
        $sql .= 'JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'JOIN `tacos_users_teams` ut ON ut.`team_id` = ct.`team_id` AND ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';
        $sql .= 'WHERE c.`id` = :customerId ';
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
     * Find All Customers
     *
     * @param ?int $visible
     * @return array of Customer entities
     */
    public function findAll(?int $visible = null): array {
        $sql  = 'SELECT c.* FROM `tacos_customers` c ';
        if (!is_null($visible)) {
            $sql .= 'WHERE c.`visible` = :visible ';
        }
        $sql .= 'ORDER BY c.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $params = array();
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }
        $stmt->execute($params);
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
     * @param ?int $visible
     * @return array of Customer entities
     */
    public function findAllByUserId(int $userId, ?int $visible = null): array {
        $sql  = 'SELECT DISTINCT c.* ';
        $sql .= 'FROM `tacos_customers` c ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = ct.`team_id` AND ut.`user_id` = :userId ';
        $sql .= 'WHERE (ut.`user_id` IS NOT NULL OR ct.`team_id` IS NULL) ';
        if (!is_null($visible)) {
            $sql .= 'AND c.`visible` = :visible ';
        }
        $sql .= 'ORDER BY c.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $params = ['userId' => $userId];
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Find All Customers by Teamleader Id
     *
     * @param int $teamleaderId
     * @param ?int $visible
     * @return array of Customer entities
     */
    public function findAllByTeamleaderId(int $teamleaderId, ?int $visible = null): array {
        $sql  = 'SELECT DISTINCT c.* ';
        $sql .= 'FROM `tacos_customers` c ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = ct.`team_id` AND ut.`user_id` = :teamleaderId AND ut.`teamlead` = 1 ';
        $sql .= 'WHERE (ut.`user_id` IS NOT NULL OR ct.`team_id` IS NULL) ';
        if (!is_null($visible)) {
            $sql .= 'AND c.`visible` = :visible ';
        }
        $sql .= 'ORDER BY c.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $params = ['teamleaderId' => $teamleaderId];
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $customers = array();
        foreach ($rows as $row) {
            $customers[$row['id']] = $this->buildEntity($row);
        }

        return $customers;
    }

    /**
     * Find All Customers by team Id
     *
     * @param int $teamId
     * @param ?int $visible
     * @return array of Customer entities
     */
    public function findAllByTeamId(int $teamId, ?int $visible = null): array {
        $sql  = 'SELECT c.* ';
        $sql .= 'FROM `tacos_customers` c ';
        $sql .= 'LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ';
        $sql .= 'WHERE (ct.`team_id` = :teamId OR ct.`team_id` IS NULL) ';
        if (!is_null($visible)) {
            $sql .= 'AND c.`visible` = :visible ';
        }
        $sql .= 'ORDER BY c.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $params = ['teamId' => $teamId];
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }

        $stmt->execute($params);
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
    public function findAllCustomersWithTeamsCountAndProjectsCount(): array {
        $sql  = "SELECT c.`id`, c.`name`, c.`color`, c.`number`, c.`visible`, ";
        $sql .= "COUNT(DISTINCT ct.`team_id` ) AS teamsCount, ";
        $sql .= "COUNT(DISTINCT p.`id`) AS projectsCount ";
        $sql .= "FROM `tacos_customers` c ";
        $sql .= "LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ";
        $sql .= "LEFT JOIN `tacos_projects` p ON p.`customer_id` = c.`id` ";
        $sql .= "GROUP BY c.`id`, c.`name`, c.`color`, c.`number`, c.`visible` ";
        $sql .= "ORDER BY c.`name`";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Find Customers with Teams count and Projects count by User id
     *
     * Visibility rules:
     *  - customer without teams => visible to all
     *  - customer with team     => visible only if user is member of at least one customer team
     *
     * @param int $userId
     * @return array of Customers with Teams count and Projects count
     */
    public function findAllCustomersWithTeamsCountAndProjectsCountByUserId(int $userId): array {
        $sql  = "SELECT c.`id`, c.`name`, c.`color`, c.`number`, c.`visible`, ";
        $sql .= "COUNT(DISTINCT ct.`team_id`) AS teamsCount, ";
        $sql .= "COUNT(DISTINCT p.`id`) AS projectsCount ";
        $sql .= "FROM `tacos_customers` c ";

        // customer teams + whether current user is member of one of them
        $sql .= "LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ";
        $sql .= "LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = ct.`team_id` AND ut.`user_id` = :userId1 ";

        // projets of the customer
        $sql .= "LEFT JOIN `tacos_projects` p ON p.`customer_id` = c.`id` ";

        // project teams + whether current user is member of one of them
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utp ON utp.`team_id` = pt.`team_id` AND utp.`user_id` = :userId2 ';

        $sql .= 'WHERE (ct.`team_id` IS NULL OR ut.`user_id` IS NOT NULL) ';  // customer: either customer has no team OR user is member of a customer team

        $sql .= "GROUP BY c.`id`, c.`name`, c.`color`, c.`number`, c.`visible` ";
        $sql .= "ORDER BY c.`name`";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'userId1' => $userId,
            'userId2' => $userId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Find Customers with Teams count and Projects count by Teamleader id
     *
     * Visibility rules:
     *  - customer without teams => visible to all
     *  - customer with team     => visible only if user is teamlead of at least one customer team
     *
     * @param int $teamleaderId
     * @return array of Customers with Teams count and Projects count
     */
    public function findAllCustomersWithTeamsCountAndProjectsCountByTeamleaderId(int $teamleaderId): array {
        $sql  = "SELECT c.`id`, c.`name`, c.`color`, c.`number`, c.`visible`, ";
        $sql .= "COUNT(DISTINCT ct.`team_id`) AS teamsCount, ";
        $sql .= "COUNT(DISTINCT p.`id`) AS projectsCount ";
        $sql .= "FROM `tacos_customers` c ";

        // customer teams + whether current teamleader is member of one of them
        $sql .= "LEFT JOIN `tacos_customers_teams` ct ON ct.`customer_id` = c.`id` ";
        $sql .= "LEFT JOIN `tacos_users_teams` ut ON ut.`team_id` = ct.`team_id` AND ut.`user_id` = :teamleaderId1 AND ut.`teamlead` = 1 ";

        // projets of the customer
        $sql .= "LEFT JOIN `tacos_projects` p ON p.`customer_id` = c.`id` ";

        // project teams + whether current teamleader is member of one of them
        $sql .= 'LEFT JOIN `tacos_projects_teams` pt ON pt.`project_id` = p.`id` ';
        $sql .= 'LEFT JOIN `tacos_users_teams` utp ON utp.`team_id` = pt.`team_id` AND utp.`user_id` = :teamleaderId2 AND utp.`teamlead` = 1 ';

        $sql .= 'WHERE (ct.`team_id` IS NULL OR ut.`user_id` IS NOT NULL) ';  // customer: either customer has no team OR user is teamlead of a customer team

        $sql .= "GROUP BY c.`id`, c.`name`, c.`color`, c.`number`, c.`visible` ";
        $sql .= "ORDER BY c.`name`";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'teamleaderId1' => $teamleaderId,
            'teamleaderId2' => $teamleaderId
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
