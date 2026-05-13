<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\LoginAttemptsRepository;
use App\Repository\UserRepository;
use App\Security\AuthResult;
use Psr\Log\LoggerInterface;

use DateTimeImmutable;

final class AuthService
{
    private LoginAttemptsRepository $loginAttemptsRepository;
    private UserRepository $userRepository;
    private LoggerInterface $logger;
    private array $options;

    public function __construct(LoginAttemptsRepository $loginAttemptsRepository, UserRepository $userRepository, LoggerInterface $logger, array $options) {
        $this->loginAttemptsRepository = $loginAttemptsRepository;
        $this->userRepository = $userRepository;
        $this->logger = $logger;
        $this->options = [
            'maxAttempts' => max(1, (int)($options['maxAttempts'] ?? 5)),
            'blockDelay'  => max(1, (int)($options['blockDelay']  ?? 300)),
        ];
    }

    /**
     * Authenticate User
     *
     * @param string $identifier
     * @param string $password
     * @return AuthResult
     */
    public function authenticate(string $identifier, string $password): AuthResult {

        if ($identifier === '' || $password === '') {
            return AuthResult::INVALID_CREDENTIALS;
        }

        $user = $this->userRepository->findOneByIdentifier($identifier);
        $trackingId = $user ? $user->getId() : crc32(mb_strtolower($identifier));

        if ($this->isBlocked($trackingId)) {
            return AuthResult::BLOCKED;
        }

        if ($user) {

            if (password_verify($password, $user->getPassword())) {
                $options = array(
                    'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
                    'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
                    'threads' => PASSWORD_ARGON2_DEFAULT_THREADS
                );
                if (password_needs_rehash($user->getPassword(), PASSWORD_ARGON2ID, $options)) {
                    $user->setPassword(password_hash($password, PASSWORD_ARGON2ID, $options));
                    $this->userRepository->updatePasswordHash($user);
                }

                $lastLogin = new DateTimeImmutable();
                $user->setLastLogin($lastLogin->format('Y-m-d H:i:s'));
                $this->userRepository->updateUserLastLogin($user);
                $this->userRepository->unsetUserPasswordRequest($user);
                $this->loginAttemptsRepository->remove($trackingId);

                session_regenerate_id(true);
                $_SESSION['auth'] = array(
                    'isLoggedIn'  => true,
                    'app'         => 'tacos',
                    'userId'      => $user->getId(),
                    'lastLogin'   => $lastLogin->format('Y-m-d H:i:s'),
                );

                return AuthResult::SUCCESS;
            }
        }

        $this->loginAttemptsRepository->insert($trackingId);
        $attempts = $this->loginAttemptsRepository->findByTrackingId($trackingId);

        $this->logger->warning(
            '[AuthService] Invalid credentials',
            [
                'tracking_id' => $trackingId,
                'attempts'    => $attempts?->getAttempts(),
            ]
        );

        if ($attempts && $attempts->getAttempts() >= $this->options['maxAttempts']) {
            $blockedUntil = (new DateTimeImmutable())->modify('+' . $this->options['blockDelay'] . ' seconds');
            $this->loginAttemptsRepository->block($trackingId, $blockedUntil);
            $this->logger->warning(
                '[AuthService] User has been blocked',
                [
                    'tracking_id' => $trackingId,
                    'blocked_until'   => $blockedUntil->format('Y-m-d H:i:s'),
                ]
            );
            return AuthResult::BLOCKED;
        }

        return AuthResult::INVALID_CREDENTIALS;
    }

    /**
     * Check if auth is blocked
     *
     * @param int $trackingId
     * @return bool
     */
    public function isBlocked(int $trackingId): bool {
        $attempts = $this->loginAttemptsRepository->findByTrackingId($trackingId);

        if (! $attempts) {
            return false;
        }

        $blockedUntil = $attempts->getBlockedUntil();

        if ($blockedUntil === null) {
            return false;
        }

        $now = new DateTimeImmutable();

        if ($blockedUntil <= $now) {
            $this->loginAttemptsRepository->remove($trackingId);
            $this->logger->info(
                '[AuthService] User has been unblocked',
                [
                    'tracking_id' => $trackingId,
                    'blocked_until'   => $blockedUntil->format('Y-m-d H:i:s'),
                ]
            );
            return false;
        }

        return true;
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public function isAuthenticated(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        return !empty($_SESSION['auth']['isLoggedIn']);
    }

    /**
     * Logout User
     *
     */
    public function logout(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION = [];
        }
    }
}
