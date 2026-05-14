<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\LoginAttempts;
use Psr\Log\LoggerInterface;

use DateTimeImmutable;
use PDO;


final class LoginAttemptsRepository
{
    private PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, LoggerInterface $logger) {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    /**
     * Find login attempts by trackingId
     *
     * @param int $trackingId
     * @return LoginAttempts entity or false
     */
    public function findByTrackingId(int $trackingId): LoginAttempts|false {
        $sql = 'SELECT l.* FROM `tacos_login_attempts` l WHERE l.`tracking_id` = :trackingId LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'trackingId' => $trackingId
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
     * Insert login attempts
     *
     * @param int $trackingId
     * @return bool
     */
    public function insert(int $trackingId): bool {
        try {
            $sql  = 'INSERT INTO `tacos_login_attempts` (`tracking_id`, `attempts`, `first_attempt_at`) VALUES (:trackingId, 1, NOW())';
            $sql .= 'ON DUPLICATE KEY UPDATE attempts = attempts + 1';

            $stmt = $this->pdo->prepare($sql);
            $res = $stmt->execute([
                'trackingId' => $trackingId
            ]);

            if (!$res) {
                $this->logger->error(
                    '[LoginAttemptsRepository] Failed to insert login attempt (execute returned false)',
                    [
                        'trackingId'=> $trackingId,
                        'errorInfo' => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[LoginAttemptsRepository] Failed to insert login attempt (exception)',
                [
                    'tracking_id'       => $trackingId,
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
     * Block login attempts
     *
     * @param int $trackingId
     * @param DateTimeImmutable $blockedUntil
     * @return bool
     */
    public function block(int $trackingId, DateTimeImmutable $blockedUntil): bool
    {
        try {
            $sql = 'UPDATE `tacos_login_attempts` SET `tacos_login_attempts`.`blocked_until` = :blockedUntil WHERE `tacos_login_attempts`.`tracking_id` = :trackingId';

            $stmt = $this->pdo->prepare($sql);
            $res = $stmt->execute([
                'trackingId'   => $trackingId,
                'blockedUntil' => $blockedUntil->format('Y-m-d H:i:s'),
            ]);

            if (!$res) {
                $this->logger->error(
                    '[LoginAttemptsRepository] Failed to block login attempts (execute returned false)',
                    [
                        'trackingId'   => $trackingId,
                        'blockedUntil' => $blockedUntil->format('Y-m-d H:i:s'),
                        'errorInfo'    => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[LoginAttemptsRepository] Failed to block login attempts',
                [
                    'trackingId'        => $trackingId,
                    'blockedUntil'      => $blockedUntil->format('Y-m-d H:i:s'),
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
     * Remove login attempts
     *
     * @param int $trackingId
     * @return bool
     */
    public function remove(int $trackingId): bool {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM `tacos_login_attempts` WHERE `tacos_login_attempts`.`tracking_id` = :trackingId');
            $res = $stmt->execute([
                'trackingId' => $trackingId
            ]);

            if (!$res) {
                $this->logger->error(
                    '[LoginAttemptsRepository] Failed to remove login attempts (execute returned false)',
                    [
                        'trackingId' => $trackingId,
                        'errorInfo'  => $stmt->errorInfo(),
                    ]
                );
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            $this->logger->error(
                '[LoginAttemptsRepository] Failed to remove login attempts (exception)',
                [
                    'trackingId'       => $trackingId,
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
     * Creates LoginAttempts object
     *
     * @param array $row
     * @return Entity\LoginAttempts
     */
    private function buildEntity(array $row): LoginAttempts {
        $entity = new LoginAttempts();
        $entity->setTrackingId((int) $row['tracking_id']);
        $entity->setAttempts((int) $row['attempts']);
        $entity->setFirstAttemptAt(new DateTimeImmutable($row['first_attempt_at']));
        $entity->setBlockedUntil(
            $row['blocked_until'] !== null
                ? new DateTimeImmutable($row['blocked_until'])
                : null
        );
        return $entity;
    }
}
