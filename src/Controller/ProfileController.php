<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\ControllerHelper;
use App\Security\SessionFingerprint;
use App\Service\AuthService;
use App\Service\FlashMessageService;
use App\Service\TeamService;
use App\Service\UserService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Slim\Views\Twig;

final class ProfileController
{
    private Twig $twig;
    private FlashMessageService $flash;
    private AuthService $authService;
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
     * @param TeamService $teamService
     * @param UserService $userService
     * @param ControllerHelper $helper
     * @param array<string, int> $options
     * @param array<string, string|int> $translations
     */
    public function __construct(Twig $twig, FlashMessageService $flash, AuthService $authService, TeamService $teamService, UserService $userService, ControllerHelper $helper, array $options, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->authService = $authService;
        $this->teamService = $teamService;
        $this->userService = $userService;
        $this->helper = $helper;
        $this->options = $options;
        $this->translations = $translations;
    }

    /**
     * Show the profile form of the current user
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
    public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $role = $this->userService->findRole($currentUser->getRole());
        $teams = $this->teamService->findAllTeamsWithTeamleadByUserId($currentUser->getId());

        return $this->twig->render($response, 'profile.html.twig', [
            'form' => [
                'loginMinLength'   => $this->options['loginMinLength'],
                'pwdMinLength'     => $this->options['pwdMinLength'],
            ],
            'user' => [
                'name'             => $currentUser->getName(),
                'username'         => $currentUser->getUsername(),
                'email'            => $currentUser->getEmail(),
                'role'             => $this->translations[strtolower($role->getName())] ?? $role->getName(),
                'roleId'           => $currentUser->getRole(),
                'lastLogin'        => $currentUser->getLastLogin(),
                'registrationDate' => $currentUser->getRegistrationDate(),
                'teams'            => $teams,
            ],
            'flashMsgSuccess'      => $this->flash->getFirst('success'),
            'flashMsgError'        => $this->flash->getFirst('error'),
        ]);
    }

    /**
     * Update the profile of the current user (logs out after a password change)
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array<string, string> $args Route arguments
     * @return ResponseInterface
     */
    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $data = (array) $request->getParsedBody();
        $fingerprintBefore = SessionFingerprint::forUser($currentUser);
        $errors = $this->userService->updateUserProfile($currentUser, $data);

        // Password changed: log out and ask to log in again (other sessions are already invalidated)
        if ($errors === '' && SessionFingerprint::forUser($currentUser) !== $fingerprintBefore) {
            $this->authService->logout();
            $this->flash->add('change_password', $this->translations['form_message_password_changed']);
            return $this->helper->redirect($request, $response, 'login');
        }

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_update']);
        }
        else {
            $this->flash->add('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'profile');
    }
}
