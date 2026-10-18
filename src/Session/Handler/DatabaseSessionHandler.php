<?php
declare(strict_types=1);

namespace App\Session\Handler;

use PDO;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /**
     * @param PDO $pdo
     * @param int $lifetime Session lifetime in seconds (used by gc() on open and by validateId())
     */
    public function __construct(private PDO $pdo, private int $lifetime) {
        //
    }

    /**
     * Open the session; also removes empty and expired sessions
     *
     * @param string $savePath Unused (sessions are stored in the database)
     * @param string $sessionName Unused
     * @return bool
     */
    public function open(string $savePath, string $sessionName): bool
    {
        $this->gc($this->lifetime);
        return true;
    }

    /**
     * Close the session (nothing to release)
     *
     * @return bool
     */
    public function close(): bool
    {
        // Nothing to do, PDO is managed by the container
        return true;
    }

    /**
     * Read session data
     *
     * @param string $id Session id
     * @return string Serialized session data, or '' if the session does not exist or is empty
     */
    public function read(string $id): string {
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

    /**
     * Write session data and refresh its timestamp (insert or update)
     *
     * @param string $id Session id
     * @param string $data Serialized session data
     * @return bool
     */
    public function write(string $id, string $data): bool {
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

    /**
     * Delete a session
     *
     * @param string $id Session id
     * @return bool
     */
    public function destroy(string $id): bool {
        $id = preg_replace('/[^a-zA-Z0-9,\-]/', '', $id);
        $stmt = $this->pdo->prepare('DELETE FROM `tacos_sessions` WHERE `tacos_sessions`.`id` = :id');
        $res = $stmt->execute([
            'id' => $id
        ]);
        return $res;
    }

    /**
     * Remove empty sessions and sessions older than $max_lifetime
     *
     * @param int $max_lifetime Maximum lifetime in seconds
     * @return int Number of expired sessions removed
     */
    public function gc(int $max_lifetime): int {
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

    /**
     * Strict mode: accept only an existing, non-expired session id
     * (PHP generates a new id when this returns false)
     *
     * @param string $id
     * @return bool
     */
    public function validateId(string $id): bool
    {
        if (preg_match('/^[a-zA-Z0-9,\-]+$/', $id) !== 1) {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT 1 FROM `tacos_sessions` WHERE `tacos_sessions`.`id` = :id AND `tacos_sessions`.`time` >= :expire LIMIT 1');
        $stmt->execute([
            'id'     => $id,
            'expire' => time() - $this->lifetime,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Refresh the session timestamp when its data did not change (lazy write)
     *
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function updateTimestamp(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare('UPDATE `tacos_sessions` SET `tacos_sessions`.`time` = :time WHERE `tacos_sessions`.`id` = :id');

        return $stmt->execute([
            'id'   => $id,
            'time' => time(),
        ]);
    }
}
