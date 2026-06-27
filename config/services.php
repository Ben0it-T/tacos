<?php
declare(strict_types=1);

use App\Helper\RoundingHelper;
use App\Helper\ValidationHelper;

use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use App\Repository\LoginAttemptsRepository;
use App\Repository\ProjectRepository;
use App\Repository\RoleRepository;
use App\Repository\TagRepository;
use App\Repository\TeamRepository;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;

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

use App\Session\SessionStoreInterface;

use PHPMailer\PHPMailer\PHPMailer;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    ActivityService::class => function (ContainerInterface $c) {
        return new ActivityService(
            $c->get(ActivityRepository::class),
            $c->get(ValidationHelper::class),
            $c->get(LoggerInterface::class),
            $c->get('translations')
        );
    },

    AuthService::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['auth']['loginAttempts'] ?? [
            'maxAttempts' => 5,
            'blockDelay'  => 300,
        ];

        return new AuthService(
            $c->get(LoginAttemptsRepository::class),
            $c->get(UserRepository::class),
            $c->get(LoggerInterface::class),
            [
                'maxAttempts' => $settings['maxAttempts'],
                'blockDelay'  => $settings['blockDelay'],
            ]
        );
    },

    CustomerService::class => function (ContainerInterface $c) {
        return new CustomerService(
            $c->get(CustomerRepository::class),
            $c->get(ValidationHelper::class),
            $c->get(LoggerInterface::class),
            $c->get('translations')
        );
    },

    FlashMessageService::class => function (ContainerInterface $c) {
        return new FlashMessageService(
            $c->get(SessionStoreInterface::class)
        );
    },

    PasswordRequestService::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['auth'];

        return new PasswordRequestService(
            $c->get(UserRepository::class),
            $c->get(PHPMailer::class),
            $c->get(LoggerInterface::class),
            [
              'pwdRequestRetryLifetime' => max(1, (int)($settings['pwdRequestRetryLifetime'] ?? 3600)),
              'pwdRequestTokenLifetime' => max(1, (int)($settings['pwdRequestTokenLifetime'] ?? 86400)),
              'pwdRequestSalt'          => (string)$settings['pwdRequestSalt'],
              'pwdMinLength'            => max(1, (int)($settings['pwdMinLength'] ?? 16)),
            ],
            $c->get('translations')
        );
    },

    ProjectService::class => function (ContainerInterface $c) {
        return new ProjectService(
            $c->get(ActivityRepository::class),
            $c->get(ProjectRepository::class),
            $c->get(ValidationHelper::class),
            $c->get(LoggerInterface::class),
            $c->get('translations')
        );
    },

    TagService::class => function (ContainerInterface $c) {
        return new TagService(
            $c->get(TagRepository::class),
            $c->get(ValidationHelper::class),
            $c->get(LoggerInterface::class),
            $c->get('translations')
        );
    },

    TeamService::class => function (ContainerInterface $c) {
        return new TeamService(
            $c->get(TeamRepository::class),
            $c->get(ValidationHelper::class),
            $c->get(LoggerInterface::class),
            $c->get('translations')
        );
    },

    TimesheetService::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['timesheet'] ?? [];
        $rounding = $settings['rounding'] ?? [];
        $modes    = ['floor','ceil','closest','none'];

        $startModeCandidate = (string)($rounding['start']['mode'] ?? 'floor');
        $startMode = in_array($startModeCandidate, $modes, true) ? $startModeCandidate : 'floor';

        $endModeCandidate = (string)($rounding['end']['mode'] ?? 'ceil');
        $endMode = in_array($endModeCandidate, $modes, true) ? $endModeCandidate : 'ceil';

        return new TimesheetService(
            $c->get(ActivityRepository::class),
            $c->get(TagRepository::class),
            $c->get(TimesheetRepository::class),
            $c->get(RoundingHelper::class),
            $c->get(ValidationHelper::class),
            $c->get(LoggerInterface::class),
            [
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

    UserService::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['auth'] ?? [];

        return new UserService(
            $c->get(RoleRepository::class),
            $c->get(UserRepository::class),
            $c->get(ValidationHelper::class),
            $c->get(LoggerInterface::class),
            [
                'loginMinLength' => max(1, (int)($settings['loginMinLength'] ?? 5)),
                'pwdMinLength'   => max(1, (int)($settings['pwdMinLength'] ?? 16)),
            ],
            $c->get('translations')
        );
    },

];
