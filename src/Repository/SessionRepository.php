<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class SessionRepository
{
    private $pdo;
    private int $lifetime;

    public function __construct(PDO $pdo, $lifetime) {
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

    public function _open() {
        $this->_gc($this->lifetime);
        return true;
    }

    public function _close() {
        //$this->pdo = null;
        return true;
    }

    public function _read($id) {
        $id = preg_replace('/[^a-zA-Z0-9,\-]/', '', $id);
        $stmt = $this->pdo->prepare('SELECT `data` FROM `tacos_sessions` WHERE `tacos_sessions`.`id` = ?');
        $stmt->execute([$id]);
        $res = $stmt->fetch();

        if ($res) {
            if (empty($res['data'])) {
                return ""; // Return an empty string
            } else {
                return $res['data'];
            }
        } else {
            return ""; // Return an empty string
        }
    }

    public function _write($id, $data) {
        $id = preg_replace('/[^a-zA-Z0-9,\-]/', '', $id);
        $time = time(); // Create time stamp
        $stmt = $this->pdo->prepare('REPLACE INTO `tacos_sessions` (`tacos_sessions`.`id`, `tacos_sessions`.`data`, `tacos_sessions`.`time`) VALUES (?, ?, ?)');
        $stmt->execute([$id, $data, $time]);

        return true;
    }

    public function _destroy($id) {
        $id = preg_replace('/[^a-zA-Z0-9,\-]/', '', $id);
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_sessions` WHERE `tacos_sessions`.`id` = ?');
        $stmt->execute([$id]);
        return true;
    }

    public function _gc($lifetime) {
        $expire = time() - intval($lifetime);
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_sessions` WHERE `tacos_sessions`.`time` < ?');
        $stmt->execute([$expire]);

        $stmt = $this->pdo->query('DELETE FROM `tacos_sessions` WHERE `tacos_sessions`.`data` = ""');

        return true;
    }

}
