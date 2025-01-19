<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Role;
use PDO;

final class RoleRepository
{
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Find Role by id
     *
     * @param int $id
     * @return Role or false
     */
    public function find(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_roles` WHERE `tacos_roles`.`id` = ?');
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
     * Find all Roles
     *
     * @return array of Roles
     */
    public function findAll() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_roles`');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $roles = array();
        foreach ($rows as $row) {
            $roles[$row['id']] = $this->buildEntity($row);
        }

        return $roles;
    }


    /**
     * Creates Role object
     *
     * @param array $row
     * @return Entity\Role
     */
    protected function buildEntity(array $row) {
        $role = new Role();
        $role->setId($row['id']);
        $role->setName($row['name']);

        return $role;
    }
}
