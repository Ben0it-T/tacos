<?php
declare(strict_types=1);

use App\Helper\RoundingHelper;
use App\Helper\ControllerHelper;

use App\Controller\ActivitiesController;
use App\Controller\CustomersController;
use App\Controller\DashboardController;
use App\Controller\LoginController;
use App\Controller\PasswordResetController;
use App\Controller\ProfileController;
use App\Controller\ProjectsController;
use App\Controller\ReportsController;
use App\Controller\TagsController;
use App\Controller\TeamsController;
use App\Controller\TimesheetsController;
use App\Controller\UsersController;
use App\Controller\XhrController;

use App\Service\ActivityService;
use App\Service\AuthService;
use App\Service\CustomerService;
use App\Service\FlashMessageService;
use App\Service\PasswordRequestService;
use App\Service\ProjectService;
use App\Service\TagService;
use App\Service\TeamService;
use App\Service\TimesheetService;
use App\Service\UserService;

use Psr\Container\ContainerInterface;

use Slim\Views\Twig;

return [
    ActivitiesController::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['theme'] ?? [];

        return new ActivitiesController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(ActivityService::class),
            $c->get(ProjectService::class),
            $c->get(TeamService::class),
            $c->get(ControllerHelper::class),
            [
                'colorChoices' => (string)($settings['colorChoices'] ?? ''),
            ],
            $c->get('translations')
        );
    },

    CustomersController::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['theme'] ?? [];

        return new CustomersController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(CustomerService::class),
            $c->get(ProjectService::class),
            $c->get(TeamService::class),
            $c->get(ControllerHelper::class),
            [
                'colorChoices' => (string)($settings['colorChoices'] ?? ''),
            ],
            $c->get('translations')
        );
    },

    DashboardController::class => function (ContainerInterface $c) {
        return new DashboardController(
            $c->get(Twig::class),
            $c->get(TimesheetService::class),
            $c->get(ControllerHelper::class)
        );
    },

    LoginController::class => function (ContainerInterface $c) {
        return new LoginController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(AuthService::class),
            $c->get(ControllerHelper::class),
            $c->get('translations')
        );
    },

    PasswordResetController::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['auth'] ?? [];

        return new PasswordResetController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(PasswordRequestService::class),
            $c->get(ControllerHelper::class),
            [
                'pwdMinLength'            => max(1, (int)($settings['pwdMinLength'] ?? 16)),
                'pwdRequestRetryLifetime' => max(1, (int)($settings['pwdRequestRetryLifetime'] ?? 3600)),
            ],
            $c->get('translations')
        );
    },

    ProfileController::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['auth'] ?? [];

        return new ProfileController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(TeamService::class),
            $c->get(UserService::class),
            $c->get(ControllerHelper::class),
            [
                'loginMinLength' => max(1, (int)($settings['loginMinLength'] ?? 5)),
                'pwdMinLength'   => max(1, (int)($settings['pwdMinLength'] ?? 16)),
            ],
            $c->get('translations')
        );
    },

    ProjectsController::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['theme'] ?? [];

        return new ProjectsController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(ActivityService::class),
            $c->get(CustomerService::class),
            $c->get(ProjectService::class),
            $c->get(TeamService::class),
            $c->get(TimesheetService::class),
            $c->get(UserService::class),
            $c->get(ControllerHelper::class),
            [
                'colorChoices' => (string)($settings['colorChoices'] ?? ''),
            ],
            $c->get('translations')
        );
    },

    ReportsController::class => function (ContainerInterface $c) {
        return new ReportsController(
            $c->get(Twig::class),
            $c->get(TimesheetService::class),
            $c->get(ControllerHelper::class),
            $c->get('translations')
        );
    },

    TagsController::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['theme'] ?? [];

        return new TagsController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(TagService::class),
            $c->get(ControllerHelper::class),
            [
                'colorChoices' => (string)($settings['colorChoices'] ?? ''),
            ],
            $c->get('translations')
        );
    },

    TeamsController::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['theme'] ?? [];

        return new TeamsController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(CustomerService::class),
            $c->get(ProjectService::class),
            $c->get(TeamService::class),
            $c->get(UserService::class),
            $c->get(ControllerHelper::class),
            [
                'colorChoices' => (string)($settings['colorChoices'] ?? ''),
            ],
            $c->get('translations')
        );
    },

    TimesheetsController::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['timesheet'] ?? [];

        $restart  = $settings['restart'] ?? [];
        $rounding = $settings['rounding'] ?? [];

        $modes    = ['floor','ceil','closest','none'];

        $startModeCandidate = (string)($rounding['start']['mode'] ?? 'floor');
        $startMode = in_array($startModeCandidate, $modes, true) ? $startModeCandidate : 'floor';

        $endModeCandidate = (string)($rounding['end']['mode'] ?? 'ceil');
        $endMode = in_array($endModeCandidate, $modes, true) ? $endModeCandidate : 'ceil';


        return new TimesheetsController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(ActivityService::class),
            $c->get(CustomerService::class),
            $c->get(ProjectService::class),
            $c->get(TagService::class),
            $c->get(TeamService::class),
            $c->get(TimesheetService::class),
            $c->get(UserService::class),
            $c->get(RoundingHelper::class),
            $c->get(ControllerHelper::class),
            [
                'restart' => [
                    'active'   => (bool)($restart['active'] ?? false),
                    'interval' => max(1, (int)($restart['interval'] ?? 3)),
                ],
                'rounding' => [
                    'active' => (bool)($rounding['active'] ?? false),
                    'start' => [
                        'mode' => $startMode,
                        'minutes' => max(1, (int)($rounding['start']['minutes'] ?? 5)),
                    ],
                    'end' => [
                        'mode' => $endMode,
                        'minutes' => max(1, (int)($rounding['end']['minutes'] ?? 5)),
                    ],
                ],
            ],
            $c->get('translations')
        );
    },

    UsersController::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['auth'] ?? [];

        return new UsersController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(CustomerService::class),
            $c->get(ProjectService::class),
            $c->get(TeamService::class),
            $c->get(UserService::class),
            $c->get(ControllerHelper::class),
            [
                'loginMinLength' => max(1, (int)($settings['loginMinLength'] ?? 5)),
                'pwdMinLength'   => max(1, (int)($settings['pwdMinLength'] ?? 16)),
            ],
            $c->get('translations')
        );
    },

    XhrController::class => function (ContainerInterface $c) {
        return new XhrController(
            $c->get(ActivityService::class),
            $c->get(ProjectService::class),
            $c->get(UserService::class),
            $c->get(ControllerHelper::class)
        );
    },

];
