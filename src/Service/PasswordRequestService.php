<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use App\Security\ResetPasswordResult;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

final class PasswordRequestService
{
    private UserRepository $userRepository;
    private PHPMailer $mailer;
    private LoggerInterface $logger;
    private array $options;
    private array $translations;

    public function __construct(UserRepository $userRepository, PHPMailer $mailer, LoggerInterface $logger, array $options, array $translations) {
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->options = $options;
        $this->translations = $translations;
    }

    /**
     * Create password request
     *
     * @param string $identifier
     */
    public function newPasswordRequest(string $identifier, string $resetLinkBase): void {
        $this->unsetPasswordRequests(); // Todo: cron

        if ($identifier !== '') {
            $user = $this->userRepository->findOneByIdentifier($identifier);
            if ($user) {
                if (!empty($user->getEmail())) {
                    $pwdRequestTS = is_null($user->getRequestDate()) ? 0 : strtotime($user->getRequestDate());

                    if (time()-$pwdRequestTS > intval($this->options['pwdRequestRetryLifetime'])) {
                        $key = bin2hex(random_bytes(16));
                        $user->setRequestToken(hash('sha256', $this->options['pwdRequestSalt'] . $key));
                        $user->setRequestDate((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
                        if ($this->userRepository->setUserPasswordRequest($user)) {
                            $this->logger->info(
                                "[PasswordRequestService] Token set for user '".$user->getId()."'",
                                [
                                    'userId'   => $user->getId()
                                ]
                            );

                            // email - Todo: move to factory
                            $resetLink = rtrim($resetLinkBase, '/') . '/' . urlencode($key);
                            $body = str_replace("%userName%", $user->getName(), $this->translations['email_reset_body']);
                            $body = str_replace("%resetLink%", $resetLink, $body);
                            $this->mailer->clearAllRecipients();
                            $this->mailer->clearAttachments();
                            $this->mailer->clearReplyTos();
                            $this->mailer->addAddress($user->getEmail(), $user->getName());
                            $this->mailer->Subject = $this->translations['email_reset_subject'];
                            $this->mailer->Body = nl2br($body);

                            if ($this->mailer->send()) {
                                $this->logger->info(
                                    "[PasswordRequestService] Reset email sent for user '".$user->getId()."'",
                                    [
                                        'userId'   => $user->getId()
                                    ]
                                );
                            }
                            else {
                                $this->logger->error(
                                    "[PasswordRequestService] Reset email failed for user '".$user->getId()."'",
                                    [
                                        'userId'   => $user->getId(),
                                        'errorInfo'=> $this->mailer->ErrorInfo,
                                    ]
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Update user password from token
     *
     * @param string $token
     * @param string $password1
     * @param string $password2
     * @return ResetPasswordResult
     */
    public function updatePasswordFromToken(string $token, string $password1, string $password2): ResetPasswordResult {
        if (!$this->validateToken($token)) {
            return ResetPasswordResult::INVALID_TOKEN;
        }

        if (!$this->validatePasswordStrength($password1)) {
            return ResetPasswordResult::INVALID_PASSWORD;
        }
        else if (strcmp($password1, $password2) !== 0) {
            return ResetPasswordResult::PASSWORD_MISMATCH;
        }

        if ($this->setUserPassword($token, $password1)) {
            return ResetPasswordResult::SUCCESS;
        }

        return ResetPasswordResult::ERROR;
    }

    /**
     * Validate token
     *
     * @param string $token
     * @return bool
     */
    public function validateToken(string $token): bool {
        $requestToken = hash('sha256', $this->options['pwdRequestSalt'] . $token);

        return $this->userRepository->isTokenExists($requestToken, intval($this->options['pwdRequestTokenLifetime']));
    }

    /**
     * Unset Users password requests
     *
     */
    private function unsetPasswordRequests(): void {
        $this->userRepository->unsetUsersPasswordRequests(intval($this->options['pwdRequestTokenLifetime']));
    }

    /**
     * Set new user password
     *
     * @param string $token
     * @param string $password
     * @return bool
     */
    private function setUserPassword(string $token, string $password): bool {
        if ($this->validateToken($token) && $this->validatePasswordStrength($password)) {
            $requestToken = hash('sha256', $this->options['pwdRequestSalt'] . $token);
            $user = $this->userRepository->findOneByToken($requestToken, intval($this->options['pwdRequestTokenLifetime']));
            if ($user) {
                $options = array(
                    'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
                    'time_cost'   => PASSWORD_ARGON2_DEFAULT_TIME_COST,
                    'threads'     => PASSWORD_ARGON2_DEFAULT_THREADS
                );
                $user->setPassword(password_hash($password, PASSWORD_ARGON2ID, $options));

                if (!$this->userRepository->updatePasswordHash($user)) {
                    return false;
                }

                $this->logger->info(
                    "[PasswordRequestService] Password updated for user '".$user->getId()."'",
                    [
                        'userId'   => $user->getId()
                    ]
                );

                $this->userRepository->unsetUserPasswordRequest($user);

                return true;
            }
        }
        return false;
    }

    /**
     * Validate password strength
     *
     * @param string $password
     * @return bool
     */
    private function validatePasswordStrength(string $password): bool {
        $minLength    = intval($this->options['pwdMinLength']);
        $length       = mb_strlen($password) < $minLength ? false : true;
        $uppercase    = preg_match('/[A-Z]/', $password);
        $lowercase    = preg_match('/[a-z]/', $password);
        $number       = preg_match('/[0-9]/', $password);
        $specialChars = preg_match('/[^\w]/', $password);

        if (!$uppercase || !$lowercase || !$number || !$specialChars || !$length) {
            return false;
        }
        return true;
    }

}
