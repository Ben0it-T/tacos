<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class SessionRepository
{
    private PDO $pdo;
    private int $lifetime;

    public function __construct(PDO $pdo, int $lifetime) {
        $this->pdo = $pdo;
        $this->lifetime = $lifetime;

        // Set handler to overide SESSION
        session_set_save_handler(
            array($this, "_open"),
            array($this, "_close"),
            array($this, "_read"),
            array($this, "_write"),
            array($this, "_destroy"),
            array($this, "_gc")
        );
    }

    public function _open(): bool {
        $this->_gc($this->lifetime);
        return true;
    }

    public function _close(): bool {
        //$this->pdo = null;
        return true;
    }

    public function _read($id): string {
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

    public function _write($id, $data): bool {
        $id = preg_replace('/[^a-zA-Z0-9,\-]/', '', $id);
        $time = time(); // Create time stamp
        $sql  = 'INSERT INTO `tacos_sessions` (`tacos_sessions`.`id`, `tacos_sessions`.`data`, `tacos_sessions`.`time`)  VALUES (:id, :data, :time) ';
        $sql .= 'ON DUPLICATE KEY UPDATE `tacos_sessions`.`data` = VALUES(data), `tacos_sessions`.`time` = VALUES(time)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'data' => $data,
            'time' => $time
        ]);

        return true;
    }

    public function _destroy($id): bool {
        $id = preg_replace('/[^a-zA-Z0-9,\-]/', '', $id);
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_sessions` WHERE `tacos_sessions`.`id` = :id');
        $stmt->execute([
            'id' => $id
        ]);
        return true;
    }

    public function _gc($lifetime): bool {
        $expire = time() - intval($lifetime);
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_sessions` WHERE `tacos_sessions`.`time` < :expire');
        $stmt->execute([
            'expire' => $expire
        ]);

        $stmt = $this->pdo->prepare('DELETE FROM `tacos_sessions` WHERE `tacos_sessions`.`data` = "" OR `tacos_sessions`.`data` IS NULL');
        $stmt->execute();

        return true;
    }
}
