<?php
declare(strict_types=1);

namespace App\Session\Handler;

use PDO;
use SessionHandlerInterface;

final class DatabaseSessionHandler implements SessionHandlerInterface
{
    public function __construct(private PDO $pdo, private int $lifetime) {
        //
    }

    public function open($savePath, $sessionName): bool
    {
        $this->gc($this->lifetime);
        return true;
    }

    public function close(): bool
    {
        // Nothing to do, PDO is managed by the container
        return true;
    }

    public function read($id): string|false {
        $id = preg_replace('/[^a-zA-Z0-9,\-]/', '', $id);
        $stmt = $this->pdo->prepare('SELECT `data` FROM `tacos_sessions` WHERE `tacos_sessions`.`id` = :id LIMIT 1');
        $stmt->execute([
            'id' => $id
        ]);
        $res = $stmt->fetch();

        if ($res) {
            if (empty($res['data'])) {
                return ""; // Return an empty string
            } else {
                return (string) $res['data'];
            }
        } else {
            return ""; // Return an empty string
        }
    }

    public function write($id, $data): bool {
        $id = preg_replace('/[^a-zA-Z0-9,\-]/', '', $id);
        $sql  = 'INSERT INTO `tacos_sessions` (`tacos_sessions`.`id`, `tacos_sessions`.`data`, `tacos_sessions`.`time`)  VALUES (:id, :data, :time) ';
        $sql .= 'ON DUPLICATE KEY UPDATE `tacos_sessions`.`data` = VALUES(data), `tacos_sessions`.`time` = VALUES(time)';
        $stmt = $this->pdo->prepare($sql);
        $res = $stmt->execute([
            'id' => $id,
            'data' => $data,
            'time' => time()
        ]);

        return $res;
    }

    public function destroy($id): bool {
        $id = preg_replace('/[^a-zA-Z0-9,\-]/', '', $id);
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_sessions` WHERE `tacos_sessions`.`id` = :id');
        $res = $stmt->execute([
            'id' => $id
        ]);
        return $res;
    }

    public function gc($max_lifetime): int|false {
        // Remove empty
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_sessions` WHERE `tacos_sessions`.`data` = "" OR `tacos_sessions`.`data` IS NULL');
        $stmt->execute();

        // Remove expired
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_sessions` WHERE `tacos_sessions`.`time` < :expire');
        $stmt->execute([
            'expire' => time() - intval($max_lifetime)
        ]);

        return $stmt->rowCount();
    }
}
