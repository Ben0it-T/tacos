<?php
declare(strict_types=1);

use App\Helper\ControllerHelper;
use App\Helper\RoundingHelper;
use App\Helper\SqlHelper;
use App\Helper\ValidationHelper;

use App\Middleware\CSPMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\TwigCsrfMiddleware;
use App\Middleware\SessionMiddleware;

use App\Session\SessionManager;
use App\Session\SessionStoreInterface;
use App\Session\Handler\LocalSessionHandlerFactory;
use App\Session\Handler\DatabaseSessionHandler;
use App\Session\Storage\PhpSession;

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

use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use App\Repository\LoginAttemptsRepository;
use App\Repository\ProjectRepository;
use App\Repository\RoleRepository;
use App\Repository\TagRepository;
use App\Repository\TeamRepository;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;

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
use App\Controller\UsersController;
use App\Controller\TimesheetsController;
use App\Controller\XhrController;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

use PHPMailer\PHPMailer\PHPMailer;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use Slim\Csrf\Guard;
use Slim\Views\Twig;

return function (ContainerInterface $container): void {

    //
    // Controllers
    //

    $container->set(ActivitiesController::class, function (ContainerInterface $c) {
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
    });

    $container->set(CustomersController::class, function (ContainerInterface $c) {
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
    });

    $container->set(DashboardController::class, function (ContainerInterface $c) {
        return new DashboardController(
            $c->get(Twig::class),
            $c->get(TimesheetService::class),
            $c->get(ControllerHelper::class)
        );
    });

    $container->set(LoginController::class, function (ContainerInterface $c) {
        return new LoginController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(AuthService::class),
            $c->get(ControllerHelper::class),
            $c->get('translations')
        );
    });

    $container->set(PasswordResetController::class, function (ContainerInterface $c) {
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
    });

    $container->set(ProfileController::class, function (ContainerInterface $c) {
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
    });

    $container->set(ProjectsController::class, function (ContainerInterface $c) {
        $settings = $c->get('settings')['theme'] ?? [];

        return new ProjectsController(
            $c->get(Twig::class),
            $c->get(FlashMessageService::class),
            $c->get(ActivityService::class),
            $c->get(CustomerService::class),
            $c->get(ProjectService::class),
            $c->get(TagService::class),
            $c->get(TeamService::class),
            $c->get(TimesheetService::class),
            $c->get(UserService::class),
            $c->get(ControllerHelper::class),
            [
                'colorChoices' => (string)($settings['colorChoices'] ?? ''),
            ],
            $c->get('translations')
        );
    });

    $container->set(ReportsController::class, function (ContainerInterface $c) {
        return new ReportsController(
            $c->get(Twig::class),
            $c->get(TimesheetService::class),
            $c->get(ControllerHelper::class),
            $c->get('translations')
        );
    });

    $container->set(TagsController::class, function (ContainerInterface $c) {
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
    });

    $container->set(TeamsController::class, function (ContainerInterface $c) {
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
    });

    $container->set(UsersController::class, function (ContainerInterface $c) {
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
    });

    $container->set(XhrController::class, function (ContainerInterface $c) {
        return new XhrController(
            $c->get(ActivityService::class),
            $c->get(ProjectService::class),
            $c->get(UserService::class),
            $c->get(ControllerHelper::class)
        );
    });

    $container->set(TimesheetsController::class, function (ContainerInterface $c) {
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
    });

};
