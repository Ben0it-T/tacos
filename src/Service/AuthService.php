<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;

final class AuthService
{
    private $userRepository;

    public function __construct(UserRepository $userRepository) {
        $this->userRepository = $userRepository;
    }

    /**
     * Auth User
     *
     * @param string $identifier
     * @param string $password
     * @return bool
     */
    public function authUser($identifier, $password) {
        if (!empty($identifier) && !empty($password)) {
            $user = $this->userRepository->findOneByIdentifier($identifier);
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

                    $lastLogin = date("Y-m-d H:i:s");
                    $user->setLastLogin($lastLogin);
                    $this->userRepository->updateUserLastLogin($user);
                    $this->userRepository->unsetUserPasswordRequest($user);

                    session_unset();
                    session_regenerate_id();
                    $_SESSION['auth'] = array(
                        'isLoggedIn'  => true,
                        'app'         => 'tacos',
                        'userId'      => $user->getId(),
                        'lastLogin'   => $lastLogin,
                    );

                    return true;
                }
            }
        }

        return false;
    }

}
