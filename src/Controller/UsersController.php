<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Helper\ControllerHelper;
use App\Security\SessionFingerprint;
use App\Service\AuthService;
use App\Service\CustomerService;
use App\Service\FlashMessageService;
use App\Service\ProjectService;
use App\Service\TeamService;
use App\Service\UserService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Views\Twig;

final class UsersController
{
    private Twig $twig;
    private FlashMessageService $flash;
    private AuthService $authService;
    private CustomerService $customerService;
    private ProjectService $projectService;
    private TeamService $teamService;
    private UserService $userService;
    private ControllerHelper $helper;
    /** @var array<string, int> */
    private array $options;
    /** @var array<string, string|int> */
    private array $translations;

    /**
     * @param Twig $twig
     * @param FlashMessageService $flash
     * @param AuthService $authService
     * @param CustomerService $customerService
     * @param ProjectService $projectService
     * @param TeamService $teamService
     * @param UserService $userService
     * @param ControllerHelper $helper
     * @param array<string, int> $options
     * @param array<string, string|int> $translations
     */
    public function __construct(Twig $twig, FlashMessageService $flash, AuthService $authService, CustomerService $customerService, ProjectService $projectService, TeamService $teamService, UserService $userService, ControllerHelper $helper, array $options, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->authService = $authService;
        $this->customerService = $customerService;
        $this->projectService = $projectService;
        $this->teamService = $teamService;
        $this->userService = $userService;
        $this->helper = $helper;
        $this->options = $options;
        $this->translations = $translations;
    }

    /**
     * List users
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $users = $this->userService->findAllUsersWithTeamCount();
        $users = $this->addUserInfos($request, $users);

        return $this->twig->render($response, 'users.html.twig', [
            'form' => [
                'loginMinLength' => $this->options['loginMinLength'],
                'pwdMinLength'   => $this->options['pwdMinLength'],
            ],
            'users'           => $users,
            'flashMsgSuccess' => $this->flash->getFirst('success'),
            'flashMsgError'   => $this->flash->getFirst('error'),
        ]);
    }

    /**
     * Create a user
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
    public function createAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->userService->createUser($data);

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_create_user']);
        }
        else {
            $this->flash->add('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'users');
    }

    /**
     * Show user details
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
    public function userDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->userService->findUserByUsername($args['username']);

        if (!$user) {
            return $this->helper->redirect($request, $response, 'users');
        }

        $role      = $this->userService->findRole($user->getRole());
        $teams     = $this->teamService->findAllTeamsWithTeamleadByUserId($user->getId());
        $customers = $this->customerService->findAllByUserId($user->getId());
        $projects  = $this->projectService->findAllProjectsWithCustomerByUserId($user->getId());

        return $this->twig->render($response, 'user-details.html.twig', [
            'user'      => $this->mapUserForView($user, $role, $teams),
            'customers' => $customers,
            'projects'  => $projects,
        ]);
    }

    /**
     * Show the user edit form
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->userService->findUserByUsername($args['username']);

        if (!$user) {
            return $this->helper->redirect($request, $response, 'users');
        }

        $role  = $this->userService->findRole($user->getRole());
        $teams = $this->teamService->findAllTeamsWithTeamleadByUserId($user->getId());

        return $this->twig->render($response, 'user-edit.html.twig', [
            'form' => [
                'loginMinLength' => $this->options['loginMinLength'],
                'pwdMinLength'   => $this->options['pwdMinLength'],
            ],
            'user'            => $this->mapUserForView($user, $role, $teams),
            'flashMsgSuccess' => $this->flash->getFirst('success'),
            'flashMsgError'   => $this->flash->getFirst('error'),
        ]);
    }

    /**
     * Update a user (logs out if the admin changed their own password)
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $username = (string)($args['username'] ?? "");
        $user = $this->userService->findUserByUsername($username);

        if (!$user) {
            return $this->helper->redirect($request, $response, 'users');
        }

        $data = (array) $request->getParsedBody();

        $currentUser = $this->helper->getCurrentUser();
        $isOwnAccount = $currentUser !== null && $currentUser->getId() === $user->getId();
        $fingerprintBefore = SessionFingerprint::forUser($user);

        $errors = $this->userService->updateUser($user, $data);

        // Own password changed: log out and ask to log in again
        if ($errors === '' && $isOwnAccount && SessionFingerprint::forUser($user) !== $fingerprintBefore) {
            $this->authService->logout();
            $this->flash->add('change_password', $this->translations['form_message_password_changed']);
            return $this->helper->redirect($request, $response, 'login');
        }

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_update']);
            return $this->helper->redirect($request, $response, 'users');
        }

        $this->flash->add('error', $errors);
        return $this->helper->redirect($request, $response, 'users_edit', ['username' => $username]);
    }

    // Helpers
    /**
     * Translate the role, cast the status and add edit and view links to each user row
     *
     * @param ServerRequestInterface $request
     * @param array<int, array<string, mixed>> $users User rows
     * @return array<int, array<string, mixed>> User rows with 'editLink' and 'viewLink'
     */
    private function addUserInfos(ServerRequestInterface $request, array $users): array
    {
        foreach ($users as &$user) {
            if (!isset($user['username'])) {
                continue;
            }
            $username = trim($user['username']);
            $roleKey  = strtolower($user['role']);

            $user['role']     = $this->translations[$roleKey] ?? $roleKey;
            $user['enable']   = (bool) $user['enable'];
            $user['editLink'] = $this->helper->getUrlFor($request, 'users_edit', ['username' => $username]);
            $user['viewLink'] = $this->helper->getUrlFor($request, 'users_details', ['username' => $username]);
        }
        unset($user);

        return $users;
    }

    /**
     * Map a user, their role and teams to the data used by the details and edit views
     *
     * @param User $user
     * @param Role $role
     * @param array<int, array<string, mixed>> $teams Team rows (id, name, color, teamlead)
     * @return array{name: string, username: string, email: string, role: string|int, roleId: int, lastLogin: ?string, registrationDate: ?string, status: int, teams: array<int, array<string, mixed>>}
     */
    private function mapUserForView(User $user, Role $role, array $teams): array
    {
        $roleKey = strtolower($role->getName());

        return [
            'name'             => $user->getName(),
            'username'         => $user->getUsername(),
            'email'            => $user->getEmail(),
            'role'             => $this->translations[$roleKey] ?? $role->getName(),
            'roleId'           => $user->getRole(),
            'lastLogin'        => $user->getLastLogin(),
            'registrationDate' => $user->getRegistrationDate(),
            'status'           => $user->getEnabled(),
            'teams'            => $teams,
        ];
    }
}
