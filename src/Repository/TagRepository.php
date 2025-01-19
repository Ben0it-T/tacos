<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use PDO;

final class TagRepository
{
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Find Tag by id
     *
     * @param int $id
     * @return Tag or false
     */
    public function find(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_tags` WHERE `tacos_tags`.`id` = ?');
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
     * Find All Tag
     *
     * @return array of Tag
     */
    public function findAll() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_tags` ORDER BY `tacos_tags`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $tags = array();
        foreach ($rows as $row) {
            $tags[$row['id']] = $this->buildEntity($row);
        }

        return $tags;
    }

    /**
     * Find All Visible Tag
     *
     * @return array of Tag
     */
    public function findAllVisibleTags() {
        $stmt = $this->pdo->prepare('SELECT * FROM `tacos_tags` WHERE `tacos_tags`.`visible` = 1 ORDER BY `tacos_tags`.`name` ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $tags = array();
        foreach ($rows as $row) {
            $tags[$row['id']] = $this->buildEntity($row);
        }

        return $tags;
    }

    /**
     * Find All Tags by timesheet id
     *
     * @param int $timesheetId
     * @return array of Tags
     */
    public function findAllTagsByTimesheetId(int $timesheetId) {
        $stmt = $this->pdo->prepare('SELECT `tacos_tags`.* FROM `tacos_tags` LEFT JOIN `tacos_timesheet_tags` ON `tacos_timesheet_tags`.`tag_id` = `tacos_tags`.`id` WHERE `tacos_timesheet_tags`.`timesheet_id` = :timesheetId ORDER BY `tacos_tags`.`name` ASC');
        $stmt->execute([
            'timesheetId' => $timesheetId,
        ]);
        $rows = $stmt->fetchAll();

        $tags = array();
        foreach ($rows as $row) {
            $tags[$row['id']] = $this->buildEntity($row);
        }

        return $tags;
    }

    /**
     * Check if name exists
     *
     * @param string $name
     * @param int $id
     * @return bool
     */
    public function isNameExists(string $name, int $id = 0) {
        $stmt = $this->pdo->prepare('SELECT count(*) as cnt FROM `tacos_tags` WHERE `tacos_tags`.`name` = :name AND `tacos_tags`.`id` != :id');
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
     * Insert Tag
     *
     * @param Tag $tag
     * @return lastInsertId or false
     */
    public function insert(Tag $tag) {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_tags` (`id`, `name`, `color`, `visible`) VALUES (NULL, :name, :color, :visible)');
            $res = $stmt->execute([
                'name' => $tag->getName(),
                'color' => $tag->getColor(),
                'visible' => $tag->getVisible()
            ]);
            return $this->pdo->lastInsertId();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update Tag
     *
     * @param Tag $tag
     * @return bool
     */
    public function update(Tag $tag) {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_tags` SET `tacos_tags`.`name` = :name, `tacos_tags`.`color` = :color, `tacos_tags`.`visible` = :visible WHERE `tacos_tags`.`id` = :id');
            $res = $stmt->execute([
                'name' => $tag->getName(),
                'color' => $tag->getColor(),
                'visible' => $tag->getVisible(),
                'id' => $tag->getId()
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Creates Tag object
     *
     * @param array $row
     * @return Entity\Tag
     */
    protected function buildEntity(array $row) {
        $tag = new Tag();
        $tag->setId($row['id']);
        $tag->setName($row['name']);
        $tag->setColor($row['color']);
        $tag->setVisible($row['visible']);

        return $tag;
    }

}
