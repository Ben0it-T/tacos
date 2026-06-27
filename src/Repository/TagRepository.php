<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Psr\Log\LoggerInterface;

use PDO;

final class TagRepository
{
    private PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, LoggerInterface $logger) {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    /**
     * Find Tag by id
     *
     * @param int $id
     * @return Tag entity or false
     */
    public function find(int $id): Tag|false {
        $stmt = $this->pdo->prepare('SELECT t.* FROM `tacos_tags` t WHERE t.`id` = :id LIMIT 1');
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
     * Find All Tag
     *
     * @param ?int $visible
     * @return array of Tag entities
     */
    public function findAll(?int $visible = null): array {
        $sql  = 'SELECT t.* FROM `tacos_tags` t ';
        if (!is_null($visible)) {
            $sql .= 'WHERE t.`visible` = :visible ';
        }
        $sql .= 'ORDER BY t.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $params = array();
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }
        $stmt->execute($params);
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
     * @param int  $timesheetId
     * @param ?int $visible
     * @return array of Tag entities
     */
    public function findAllByTimesheetId(int $timesheetId, ?int $visible = null): array {
        $sql  = 'SELECT t.* ';
        $sql .= 'FROM `tacos_tags` t ';
        $sql .= 'JOIN `tacos_timesheet_tags` tt ON tt.`tag_id` = t.`id` ';
        $sql .= 'WHERE tt.`timesheet_id` = :timesheetId ';
        if (!is_null($visible)) {
            $sql .= 'AND t.`visible` = :visible ';
        }
        $sql .= 'ORDER BY t.`name` ASC';

        $stmt = $this->pdo->prepare($sql);
        $params = ['timesheetId' => $timesheetId,];
        if (!is_null($visible)) {
            $params['visible'] = $visible;
        }
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $tags = array();
        foreach ($rows as $row) {
            $tags[$row['id']] = $this->buildEntity($row);
        }

        return $tags;
    }

    /**
     * Find All Tag ids by timesheet id
     *
     * @param int $timesheetId
     * @return array of Tag ids
     */
    public function findAllTagIdsByTimesheetId(int $timesheetId): array {
        $sql  = 'SELECT t.`id` ';
        $sql .= 'FROM `tacos_tags` t ';
        $sql .= 'JOIN `tacos_timesheet_tags` tt ON tt.`tag_id` = t.`id` ';
        $sql .= 'WHERE tt.`timesheet_id` = :timesheetId';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'timesheetId' => $timesheetId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $rows;
    }

    /**
     * Check if name exists
     *
     * @param string $name
     * @param int $id
     * @return bool
     */
    public function isNameExists(string $name, int $id = 0): bool {
        $sql  = 'SELECT count(*) as cnt ';
        $sql .= 'FROM `tacos_tags` t ';
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
     * Insert Tag
     *
     * @param Tag $tag
     * @return string|false Last insert ID on success, false on failure
     */
    public function insert(Tag $tag): string|false {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO `tacos_tags` (`id`, `name`, `color`, `visible`) VALUES (NULL, :name, :color, :visible)');
            $res = $stmt->execute([
                'name'    => $tag->getName(),
                'color'   => $tag->getColor(),
                'visible' => $tag->getVisible()
            ]);

            if (!$res) {
                $this->logger->error(
                    '[TagRepository] Failed to insert tag (execute returned false)',
                    [
                        'name'      => $tag->getName(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return $this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            $this->logger->error(
                '[TagRepository] Failed to insert tag (exception)',
                [
                    'name'              => $tag->getName(),
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
     * Update Tag
     *
     * @param Tag $tag
     * @return bool
     */
    public function update(Tag $tag): bool {
        try {
            $stmt = $this->pdo->prepare('UPDATE `tacos_tags` SET `tacos_tags`.`name` = :name, `tacos_tags`.`color` = :color, `tacos_tags`.`visible` = :visible WHERE `tacos_tags`.`id` = :id');
            $res = $stmt->execute([
                'name'    => $tag->getName(),
                'color'   => $tag->getColor(),
                'visible' => $tag->getVisible(),
                'id'      => $tag->getId()
            ]);

            if (!$res) {
                $this->logger->error(
                    '[TagRepository] Failed to update tag (execute returned false)',
                    [
                        'id'        => $tag->getId(),
                        'name'      => $tag->getName(),
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[TagRepository] Failed to update tag (exception)',
                [
                    'id'                => $tag->getId(),
                    'name'              => $tag->getName(),
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
     * Creates Tag object
     *
     * @param array<string, mixed> $row
     * @return Tag
     */
    protected function buildEntity(array $row): Tag {
        $tag = new Tag();
        $tag->setId($row['id']);
        $tag->setName($row['name']);
        $tag->setColor($row['color']);
        $tag->setVisible((int) $row['visible']);

        return $tag;
    }
}
