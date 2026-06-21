<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\ControllerHelper;
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
    private TeamService $teamService;
    private UserService $userService;
    private ControllerHelper $helper;
    private array $options;
    private array $translations;


    public function __construct(Twig $twig, FlashMessageService $flash, TeamService $teamService, UserService $userService, ControllerHelper $helper, array $options, array $translations)
    {
        $this->twig = $twig;
        $this->flash = $flash;
        $this->teamService = $teamService;
        $this->userService = $userService;
        $this->helper = $helper;
        $this->options = $options;
        $this->translations = $translations;
    }

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

    public function editAction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $currentUser = $this->helper->getCurrentUser();
        if (!$currentUser) {
            return $this->helper->redirect($request, $response, 'login');
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->userService->updateUserProfile($currentUser, $data);

        if ($errors === '') {
            $this->flash->add('success', $this->translations['form_success_update']);
        }
        else {
            $this->flash->add('error', $errors);
        }

        return $this->helper->redirect($request, $response, 'profile');
    }
}
