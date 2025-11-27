<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Helper\ValidationHelper;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class UserService
{
    private $container;
    private $roleRepository;
    private $userRepository;
    private $validationHelper;
    private $logger;

    public function __construct(ContainerInterface $container, RoleRepository $roleRepository, UserRepository $userRepository, ValidationHelper $validationHelper, LoggerInterface $logger) {
        $this->container = $container;
        $this->roleRepository = $roleRepository;
        $this->userRepository = $userRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
    }

    /**
     * Find User by id
     *
     * @param int $id
     * @return User or false
     */
    public function findUser(int $id) {
        return $this->userRepository->find($id);
    }

    /**
     * Find User by username
     *
     * @param string $username
     * @return User or false
     */
    public function findUserByUsername($username) {
        return $this->userRepository->findOneByUsername($username);
    }

    /**
     * Find Users
     *
     * @return array of all Users
     */
    public function findAllUsers() {
        return $this->userRepository->findAll();
    }

    /**
     * Find all enabled Users
     *
     * @return array of all Users
     */
    public function findAllUsersEnabled() {
        return $this->userRepository->findAllEnabled();
    }

    /**
     * Find all enabled Users in Teams
     *
     * @param array list of teamsIds
     * @return array of Users
     */
    public function findAllEnabledUsersInTeams($teamsIds) {
        return $this->userRepository->findAllEnabledUsersInTeams($teamsIds);
    }



    /**
     * Find all Users by Team Id
     *
     * @param $teamId
     * @param $enabled
     * @return array of Users
     */
    public function findAllUsersByTeamId(int $teamId, ?int $enabled = null) {
        return $this->userRepository->findAllUsersByTeamId($teamId, $enabled);
    }

    /**
     * Find all Teamleaders by Team Id
     *
     * @param $teamId
     * @param $enabled
     * @return array of Teamleaders
     */
    public function findAllTeamleadersByTeamId(int $teamId, ?int $enabled = null) {
        return $this->userRepository->findAllTeamleadersByTeamId($teamId, $enabled);
    }

    /**
     * Find all Users with Teams count
     *
     * @return array of Users
     */
    public function findAllUsersWithTeamCount() {
        return $this->userRepository->findAllUsersWithTeamCount();
    }

    /**
     * Find Role
     *
     * @param int $id
     * @return Role or false
     */
    public function findRole(int $id) {
        return $this->roleRepository->find($id);
    }

    /**
     * Find Roles
     *
     * @return array of all Roles
     */
    public function findAllRoles() {
        return $this->roleRepository->findAll();
    }



    /**
     * Get number of teams for user
     *
     * @param int $userId
     * @return int number of teams
     */
    public function getNbOfTeamsForUser(int $userId) {
        return $this->userRepository->getNbOfTeamsForUser($userId);
    }

    /**
     * Get User Teams
     *
     * @param int $userId
     * @return array of all user Teams
     */
    public function getTeamsForUser(int $userId) {
        return $this->userRepository->getTeamsForUser($userId);
    }



    /**
     * Create new User
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createUser($data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";

        $name = $this->validationHelper->sanitizeName($data['user_create_form_name']);
        $username = $this->validationHelper->sanitizeUsername($data['user_create_form_username']);
        $email = $this->validationHelper->sanitizeEmail($data['user_create_form_email']);
        $role = intval($data['user_create_form_role']);

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $validation = false;
            $errorMsg .= $translations['form_error_name'] . "\n";
        }

        // Validate username
        $minLength = $this->container->get('settings')['auth']['loginMinLength'];
        if (!$this->validationHelper->validateUsename($username, $minLength)) {
            $validation = false;
            $errorMsg .= str_replace("%minLength%", sprintf("%d", $minLength), $translations['form_error_username_length']) . "\n";
        } else if ($this->userRepository->isUsernameExists($username)) {
            $validation = false;
            $errorMsg .= $translations['form_error_username'] . "\n";
        }

        // Validate email
        if (!$this->validationHelper->validateEmail($email)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_email'], $translations['form_error_format']) . "\n";
        }
        else if ($this->userRepository->isEmailExists($email)) {
            $validation = false;
            $errorMsg .= $translations['form_error_email'] . "\n";
        }

        // Validate role
        if (!$this->validationHelper->validateRole($role)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_role'], $translations['form_error_format']) . "\n";
        }

        if ($validation) {
            $cryptographically_strong = true;
            $random_bytes = openssl_random_pseudo_bytes(16, $cryptographically_strong);
            $password = bin2hex($random_bytes);
            $options = array(
                'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
                'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
                'threads' => PASSWORD_ARGON2_DEFAULT_THREADS
            );

            $user = new User();
            $user->setUsername($username);
            $user->setName($name);
            $user->setEmail($email);
            $user->setRegistrationDate(date("Y-m-d H:i:s"));
            $user->setRole($role);
            $user->setPassword(password_hash($password, PASSWORD_ARGON2ID, $options));

            $this->userRepository->insert($user);
            $this->logger->info("UserService - User '" . $user->getUsername() . "' created.");
        }

        return $errorMsg;
    }

    /**
     * Update user profile
     *
     * @param User $user
     * @return string $errorMsg
     */
    public function updateUserProfile(User $user, $data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";

        $name = $this->validationHelper->sanitizeName($data['user_profile_form_name']);
        $username = $this->validationHelper->sanitizeUsername($data['user_profile_form_username']);
        $email = $this->validationHelper->sanitizeEmail($data['user_profile_form_email']);
        $password1 = $data['user_profile_form_password1'];
        $password2 = $data['user_profile_form_password2'];

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $validation = false;
            $errorMsg .= $translations['form_error_name'] . "\n";
        }

        // Validate username
        $minLength = $this->container->get('settings')['auth']['loginMinLength'];
        if (!$this->validationHelper->validateUsename($username, $minLength)) {
            $validation = false;
            $errorMsg .= str_replace("%minLength%", sprintf("%d", $minLength), $translations['form_error_username_length']) . "\n";
        }
        else if ($this->userRepository->isUsernameExists($username, $user->getId())) {
            $validation = false;
            $errorMsg .= $translations['form_error_username'] . "\n";
        }

        // Validate email
        if (!$this->validationHelper->validateEmail($email)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_email'], $translations['form_error_format']) . "\n";
        }
        else if ($this->userRepository->isEmailExists($email, $user->getId())) {
            $validation = false;
            $errorMsg .= $translations['form_error_email'] . "\n";
        }

        if ($password1 !== "") {
            $minLength  = $this->container->get('settings')['auth']['pwdMinLength'];
            if (!$this->validationHelper->validatePassword($password1, $minLength)) {
                $validation = false;
                $errorMsg .= str_replace("%minLength%", sprintf("%d", $minLength), $translations['form_error_password_strength']);
            }
            else if (strcmp($password1, $password2) !== 0) {
                $validation = false;
                $errorMsg .= $translations['form_error_password_not_egal'];
            }
        }

        if ($validation) {
            $user->setName($name);
            $user->setUsername($username);
            $user->setEmail($email);
            $this->userRepository->updateUserProfile($user);

            if ($password1 !== "") {
                $options = array(
                    'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
                    'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
                    'threads' => PASSWORD_ARGON2_DEFAULT_THREADS
                );
                $user->setPassword(password_hash($password1, PASSWORD_ARGON2ID, $options));
                $this->userRepository->updatePasswordHash($user);
            }

            $this->logger->info("UserService - User " . $user->getId() . " profile updated.");
        }

        return $errorMsg;
    }

    /**
     * Update user
     *
     * @param User $user
     * @param array $data
     * @return string $errorMsg
     */
    public function updateUser(User $user, $data) {
        $translations = $this->container->get('translations');
        $validation = true;
        $errorMsg = "";

        $name = $this->validationHelper->sanitizeName($data['user_edit_form_name']);
        $username = $this->validationHelper->sanitizeUsername($data['user_edit_form_username']);
        $email = $this->validationHelper->sanitizeEmail($data['user_edit_form_email']);
        $role = intval($data['user_edit_form_role']);
        $status = intval($data['user_edit_form_status']);
        $password1 = $data['user_edit_form_password1'];
        $password2 = $data['user_edit_form_password2'];


        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $validation = false;
            $errorMsg .= $translations['form_error_name'] . "\n";
        }

        // Validate username
        $minLength = $this->container->get('settings')['auth']['loginMinLength'];
        if (!$this->validationHelper->validateUsename($username, $minLength)) {
            $validation = false;
            $errorMsg .= str_replace("%minLength%", sprintf("%d", $minLength), $translations['form_error_username_length']) . "\n";
        } else if ($this->userRepository->isUsernameExists($username, $user->getId())) {
            $validation = false;
            $errorMsg .= $translations['form_error_username'] . "\n";
        }

        // Validate email
        if (!$this->validationHelper->validateEmail($email)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_email'], $translations['form_error_format']) . "\n";
        }
        else if ($this->userRepository->isEmailExists($email, $user->getId())) {
            $validation = false;
            $errorMsg .= $translations['form_error_email'] . "\n";
        }

        // Validate role
        if (!$this->validationHelper->validateRole($role)) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_role'], $translations['form_error_format']) . "\n";
        }

        // Validate status
        if ($status !== 0 && $status !== 01 ) {
            $validation = false;
            $errorMsg .= str_replace("%fieldName%", $translations['form_label_status'], $translations['form_error_format']) . "\n";
        }

        if ($password1 !== "") {
            $minLength  = $this->container->get('settings')['auth']['pwdMinLength'];
            if (!$this->validationHelper->validatePassword($password1, $minLength)) {
                $validation = false;
                $errorMsg .= str_replace("%minLength%", sprintf("%d", $minLength), $translations['form_error_password_strength']);
            }
            else if (strcmp($password1, $password2) !== 0) {
                $validation = false;
                $errorMsg .= $translations['form_error_password_not_egal'];
            }
        }

        if ($validation) {
            $user->setName($name);
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setRole($role);
            $user->setEnabled($status);
            $this->userRepository->updateUser($user);

            if ($password1 !== "") {
                $options = array(
                    'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
                    'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
                    'threads' => PASSWORD_ARGON2_DEFAULT_THREADS
                );
                $user->setPassword(password_hash($password1, PASSWORD_ARGON2ID, $options));
                $this->userRepository->updatePasswordHash($user);
            }

            $this->logger->info("UserService - User " . $user->getId() . " updated.");
        }

        return $errorMsg;
    }

}
