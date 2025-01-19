<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Routing\RouteContext;

final class PasswordRequestService
{
    private $container;
    private $userRepository;
    private $logger;
    private $mailer;

    public function __construct(ContainerInterface $container, UserRepository $userRepository, LoggerInterface $logger, PHPMailer $mailer) {
        $this->container = $container;
        $this->userRepository = $userRepository;
        $this->logger = $logger;
        $this->mailer = $mailer;

        $this->unsetPasswordRequests();
    }

    /**
     * Create password request
     *
     * @param string $identifier
     */
    public function newPasswordRequest($identifier, $request): void {
        if (!empty($identifier)) {
            $user = $this->userRepository->findOneByIdentifier($identifier);
            if ($user) {
                if (!empty($user->getEmail())) {
                    $settings = $this->container->get('settings')['auth'];
                    $pwdRequestTS = is_null($user->getRequestDate()) ? 0 : strtotime($user->getRequestDate());

                    if (time()-$pwdRequestTS > intval($settings['pwdRequestRetryLifetime'])) {
                        // token
                        $cryptographically_strong = true;
                        $random_bytes = openssl_random_pseudo_bytes(16, $cryptographically_strong);
                        $key = bin2hex($random_bytes);

                        $user->setRequestToken(hash('sha256', $settings['pwdRequestSalt'] . $key));
                        $user->setRequestDate(date("Y-m-d H:i:s"));
                        $this->userRepository->setUserPasswordRequest($user);
                        $this->logger->info("PasswordRequest - Token has been set for user " . $user->getId() . ".");

                        // email
                        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
                        $translations = $this->container->get('translations');
                        $resetLink = $routeParser->fullUrlFor($request->getUri(), 'change_password', array('key' => $key));
                        $body = str_replace("%userName%", $user->getName(), $translations['email_reset_body']);
                        $body = str_replace("%resetLink%", $resetLink, $body);
                        $this->mailer->addAddress($user->getEmail(), $user->getName());
                        $this->mailer->Subject = $translations['email_reset_subject'];
                        $this->mailer->Body = nl2br($body);

                        if ($this->mailer->send()) {
                            $this->logger->info("PasswordRequest - Mail has been set for user " . $user->getId() . ".");
                        }
                        else {
                            $this->logger->error('PasswordRequest - Mailer Error: ' . $this->mailer->ErrorInfo);
                        }
                    }
                }
            }
        }
    }

    /**
     * Unset Users password requests
     *
     */
    public function unsetPasswordRequests(): void {
        $settings = $this->container->get('settings')['auth'];
        $tokenLifetime = intval($settings['pwdRequestTokenLifetime']);
        $lifetime = time()-$tokenLifetime;
        $this->userRepository->unsetUsersPasswordRequests($lifetime);
    }

    /**
     * Set new user password
     *
     * @param string $token
     * @param string $password
     * @return bool
     */
    public function setUserPassword(string $token, string $password) {
        if ($this->validateToken($token) && $this->validatePasswordStrength($password)) {
            $settings = $this->container->get('settings')['auth'];
            $requestToken = hash('sha256', $settings['pwdRequestSalt'] . $token);
            $tokenLifetime = intval(time() - $settings['pwdRequestTokenLifetime']);

            $user = $this->userRepository->findOneByToken($requestToken, $tokenLifetime);
            if ($user) {
                $options = array(
                    'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
                    'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
                    'threads' => PASSWORD_ARGON2_DEFAULT_THREADS
                );
                $user->setPassword(password_hash($password, PASSWORD_ARGON2ID, $options));
                $this->userRepository->updatePasswordHash($user);
                $this->userRepository->unsetUserPasswordRequest($user);
                $this->logger->info("PasswordRequest - Password has been updated for user " . $user->getId() . ".");

                return true;
            }
        }
        return false;
    }

    /**
     * Validate token
     *
     * @param string $token
     * @return bool
     */
    public function validateToken(string $token) {
        $settings = $this->container->get('settings')['auth'];
        $requestToken = hash('sha256', $settings['pwdRequestSalt'] . $token);
        $tokenLifetime = intval(time() - $settings['pwdRequestTokenLifetime']);

        return $this->userRepository->isTokenExists($requestToken, $tokenLifetime);
    }

    /**
     * Validate password strength
     *
     * @param string $password
     * @return boo
     */
    public function validatePasswordStrength(string $password) {
        $minLength    = intval($this->container->get('settings')['auth']['pwdMinLength']);
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
