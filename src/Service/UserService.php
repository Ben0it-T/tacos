<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Role;
use App\Entity\User;
use App\Helper\ValidationHelper;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;

final class UserService
{
    private RoleRepository $roleRepository;
    private UserRepository $userRepository;
    private ValidationHelper $validationHelper;
    private LoggerInterface $logger;
    private array $options;
    private array $translations;

    public function __construct(RoleRepository $roleRepository, UserRepository $userRepository, ValidationHelper $validationHelper, LoggerInterface $logger, array $options, array $translations) {
        $this->roleRepository = $roleRepository;
        $this->userRepository = $userRepository;
        $this->validationHelper = $validationHelper;
        $this->logger = $logger;
        $this->options = $options;
        $this->translations = $translations;
    }

    /**
     * Find User by id
     *
     * @param int $id
     * @return User or false
     */
    public function findUser(int $id): User|false {
        return $this->userRepository->find($id);
    }

    /**
     * Find User by username
     *
     * @param string $username
     * @return User or false
     */
    public function findUserByUsername(string $username): User|false {
        return $this->userRepository->findOneByUsername($username);
    }

    /**
     * Find all Users
     *
     * @param ?int $enabled
     * @return array of User entities
     */
    public function findAllUsers(?int $enabled = null): array {
        return $this->userRepository->findAll($enabled);
    }

    /**
     * Find all Users in Teams
     *
     * @param array $teamsIds
     * @param ?int $enabled
     * @return array of User entities
     */
    public function findAllUsersInTeams(array $teamsIds, ?int $enabled = null): array {
        return $this->userRepository->findAllUsersInTeams($teamsIds, $enabled);
    }



    /**
     * Find all Users by Team Id
     *
     * @param $teamId
     * @param $enabled
     * @return array of Users
     */
    public function findAllUsersByTeamId(int $teamId, ?int $enabled = null): array {
        return $this->userRepository->findAllUsersByTeamId($teamId, $enabled);
    }

    /**
     * Find all Teamleaders by Team Id
     *
     * @param $teamId
     * @param $enabled
     * @return array of Teamleaders
     */
    public function findAllTeamleadersByTeamId(int $teamId, ?int $enabled = null): array {
        return $this->userRepository->findAllTeamleadersByTeamId($teamId, $enabled);
    }

    /**
     * Find all Users with Teams count
     *
     * @return array of Users with Role and Teams count
     */
    public function findAllUsersWithTeamCount(): array {
        return $this->userRepository->findAllUsersWithTeamCount();
    }

    /**
     * Find Role
     *
     * @param int $id
     * @return Role or false
     */
    public function findRole(int $id): Role|false {
        return $this->roleRepository->find($id);
    }

    /**
     * Find Roles
     *
     * @return array of all Roles
     */
    public function findAllRoles(): array {
        return $this->roleRepository->findAll();
    }



    /**
     * hash password
     *
     * @param string $password
     * @return string
     */
    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost'   => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads'     => PASSWORD_ARGON2_DEFAULT_THREADS,
        ]);
    }


    /**
     * Create new User
     *
     * @param array $data
     * @return string $errorMsg
     */
    public function createUser(array $data): string {
        $errorMsg = "";
        $name = $this->validationHelper->sanitizeName($data['user_create_form_name']);
        $username = $this->validationHelper->sanitizeUsername($data['user_create_form_username']);
        $email = $this->validationHelper->sanitizeEmail($data['user_create_form_email']);
        $role = intval($data['user_create_form_role']);

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= $this->translations['form_error_name'] . "\n";
        }

        // Validate username
        $minLength = $this->options['loginMinLength'];
        if (!$this->validationHelper->validateUsername($username, $minLength)) {
            $errorMsg .= str_replace("%minLength%", sprintf("%d", $minLength), $this->translations['form_error_username_length']) . "\n";
        } else if ($this->userRepository->isUsernameExists($username)) {
            $errorMsg .= $this->translations['form_error_username'] . "\n";
        }

        // Validate email
        if (!$this->validationHelper->validateEmail($email)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_email'], $this->translations['form_error_format']) . "\n";
        }
        else if ($this->userRepository->isEmailExists($email)) {
            $errorMsg .= $this->translations['form_error_email'] . "\n";
        }

        // Validate role
        if (!$this->validationHelper->validateRole($role)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_role'], $this->translations['form_error_format']) . "\n";
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setName($name);
        $user->setEmail($email);
        $user->setRegistrationDate((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        $user->setRole($role);
        $user->setPassword($this->hashPassword(bin2hex(random_bytes(16))));

        $lastInsertId = $this->userRepository->insert($user);

        if (!$lastInsertId) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[UserService] User '".$user->getName()."' created",
            [
                'id'   => $lastInsertId,
                'name' => $user->getName(),
            ]
        );

        return '';
    }

    /**
     * Update user profile
     *
     * @param User $user
     * @return string $errorMsg
     */
    public function updateUserProfile(User $user, array $data): string {
        $errorMsg = "";
        $name = $this->validationHelper->sanitizeName($data['user_profile_form_name']);
        $username = $this->validationHelper->sanitizeUsername($data['user_profile_form_username']);
        $email = $this->validationHelper->sanitizeEmail($data['user_profile_form_email']);
        $password1 = $data['user_profile_form_password1'] ?? '';
        $password2 = $data['user_profile_form_password2'] ?? '';

        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= $this->translations['form_error_name'] . "\n";
        }

        // Validate username
        $minLength = $this->options['loginMinLength'];
        if (!$this->validationHelper->validateUsername($username, $minLength)) {
            $errorMsg .= str_replace("%minLength%", sprintf("%d", $minLength), $this->translations['form_error_username_length']) . "\n";
        }
        else if ($this->userRepository->isUsernameExists($username, $user->getId())) {
            $errorMsg .= $this->translations['form_error_username'] . "\n";
        }

        // Validate email
        if (!$this->validationHelper->validateEmail($email)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_email'], $this->translations['form_error_format']) . "\n";
        }
        else if ($this->userRepository->isEmailExists($email, $user->getId())) {
            $errorMsg .= $this->translations['form_error_email'] . "\n";
        }

        if ($password1 !== "") {
            $minLength  = $this->options['pwdMinLength'];
            if (!$this->validationHelper->validatePassword($password1, $minLength)) {
                $errorMsg .= str_replace("%minLength%", sprintf("%d", $minLength), $this->translations['form_error_password_strength']) . "\n";
            }
            else if (strcmp($password1, $password2) !== 0) {
                $errorMsg .= $this->translations['form_error_password_not_egal'] . "\n";
            }
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        $user->setName($name);
        $user->setUsername($username);
        $user->setEmail($email);

        if (!$this->userRepository->updateUserProfile($user)) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[UserService] User '".$user->getName()."': profile updated",
            [
                'id'   => $user->getId(),
                'name' => $user->getName(),
            ]
        );

        if ($password1 !== "") {
            $user->setPassword($this->hashPassword($password1));
            if (!$this->userRepository->updatePasswordHash($user)) {
                return $this->translations['error_occurred'];
            }

            $this->logger->info(
                "[UserService] User '".$user->getName()."': password updated",
                [
                    'id'   => $user->getId(),
                    'name' => $user->getName(),
                ]
            );
        }

        return '';
    }

    /**
     * Update user
     *
     * @param User $user
     * @param array $data
     * @return string $errorMsg
     */
    public function updateUser(User $user, array $data): string {
        $errorMsg = "";
        $name = $this->validationHelper->sanitizeName($data['user_edit_form_name']);
        $username = $this->validationHelper->sanitizeUsername($data['user_edit_form_username']);
        $email = $this->validationHelper->sanitizeEmail($data['user_edit_form_email']);
        $role = (int) $data['user_edit_form_role'];
        $status = (int) $data['user_edit_form_status'];
        $password1 = $data['user_edit_form_password1'] ?? '';
        $password2 = $data['user_edit_form_password2'] ?? '';


        // Validate name
        if (!$this->validationHelper->validateName($name)) {
            $errorMsg .= $this->translations['form_error_name'] . "\n";
        }

        // Validate username
        $minLength = $this->options['loginMinLength'];
        if (!$this->validationHelper->validateUsername($username, $minLength)) {
            $errorMsg .= str_replace("%minLength%", sprintf("%d", $minLength), $this->translations['form_error_username_length']) . "\n";
        } else if ($this->userRepository->isUsernameExists($username, $user->getId())) {
            $errorMsg .= $this->translations['form_error_username'] . "\n";
        }

        // Validate email
        if (!$this->validationHelper->validateEmail($email)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_email'], $this->translations['form_error_format']) . "\n";
        }
        else if ($this->userRepository->isEmailExists($email, $user->getId())) {
            $errorMsg .= $this->translations['form_error_email'] . "\n";
        }

        // Validate role
        if (!$this->validationHelper->validateRole($role)) {
            $errorMsg .= str_replace("%fieldName%", $this->translations['form_label_role'], $this->translations['form_error_format']) . "\n";
        }

        if ($password1 !== "") {
            $minLength  = $this->options['pwdMinLength'];
            if (!$this->validationHelper->validatePassword($password1, $minLength)) {
                $errorMsg .= str_replace("%minLength%", sprintf("%d", $minLength), $this->translations['form_error_password_strength']) . "\n";
            }
            else if (strcmp($password1, $password2) !== 0) {
                $errorMsg .= $this->translations['form_error_password_not_egal'] . "\n";
            }
        }

        if ($errorMsg !== '') {
            return $errorMsg;
        }

        $user->setName($name);
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRole($role);
        $user->setEnabled($status);

        if (!$this->userRepository->updateUser($user)) {
            return $this->translations['error_occurred'];
        }

        $this->logger->info(
            "[UserService] User '".$user->getName()."' updated",
            [
                'id'   => $user->getId(),
                'name' => $user->getName(),
            ]
        );

        if ($password1 !== "") {
            $user->setPassword($this->hashPassword($password1));
            if (!$this->userRepository->updatePasswordHash($user)) {
                return $this->translations['error_occurred'];
            }

            $this->logger->info(
                "[UserService] User '".$user->getName()."': password updated",
                [
                    'id'   => $user->getId(),
                    'name' => $user->getName(),
                ]
            );
        }

        return '';
    }

}
