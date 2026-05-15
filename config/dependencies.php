<?php
declare(strict_types=1);

use App\Helper\SqlHelper;
use App\Helper\ValidationHelper;

use App\Middleware\CSPMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\TwigCsrfMiddleware;
use App\Middleware\SessionMiddleware;

use App\Session\SessionManager;
use App\Session\Handler\LocalSessionHandlerFactory;
use App\Session\Handler\DatabaseSessionHandler;

use App\Service\ActivityService;
use App\Service\AuthService;
use App\Service\CustomerService;
use App\Service\PasswordRequestService;

use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use App\Repository\LoginAttemptsRepository;
use App\Repository\ProjectRepository;
use App\Repository\RoleRepository;
use App\Repository\TagRepository;
use App\Repository\TeamRepository;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;

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
    // Infra
    //

    $container->set('translations', function (ContainerInterface $c) {
        $settings = $c->get('settings')['lang'];
        $translations = require sprintf("%s/%s.php", $settings['path'], 'en_US');

        // Overwrite default lang with local lang
        $langFile = sprintf('%s/%s.php', $settings['path'], $settings['default']);
        if (file_exists($langFile)) {
            $localLang = require $langFile;
            $translations  = array_merge($translations, $localLang);
        }

        return $translations;
    });

    $container->set(PDO::class, function (ContainerInterface $c) {
        $db = $c->get('settings')['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['dbname'],
            $db['charset']
        );
        return new PDO($dsn, $db['user'], $db['pass'], $db['options']);
    });

    $container->set(Twig::class, function (ContainerInterface $c) {
        $settings = $c->get('settings')['twig'];
        $cache = is_bool($settings['cache']) === true ? (bool)$settings['cache'] : sprintf("%s", $settings['cache']);
        $twig  = Twig::create(sprintf("%s", $settings['templates']), [
            'cache' => $cache,
            'charset' => 'UTF-8',
            'strict_variables' => true,
            'autoescape' => 'html',
        ]);

        // Twig global vars
        $basepath = is_null($c->get('settings')['app']['basepath']) ? '/' : sprintf("%s", $c->get('settings')['app']['basepath']);
        $twig->getEnvironment()->addGlobal('basePath', $basepath);
        $twig->getEnvironment()->addGlobal('trans', $c->get('translations'));

        return $twig;
    });

    $container->set(PHPMailer::class, function (ContainerInterface $c) {
        $settings = $c->get('settings')['mailer'];
        $mailer = new PHPMailer();
        if ($settings['mode'] == 'smtp') {
            $mailer->isSMTP();
            $mailer->SMTPDebug = $settings['smtp_debug'];
            $mailer->SMTPAuth = (bool)$settings['smtp_auth'];
            $mailer->Host = sprintf("%s", $settings['smtp_host']);
            $mailer->Port = intval($settings['smtp_port']);
            $mailer->Username = sprintf("%s", $settings['smtp_user']);
            $mailer->Password = sprintf("%s", $settings['smtp_pass']);
            $mailer->SMTPSecure = $settings['smtp_secure'];
        } else if ($settings['mode'] == 'sendmail') {
            $mailer->isSendmail();
        }
        $mailer->setFrom(sprintf("%s", $settings['from_addr']), sprintf("%s", $settings['from_name']));
        $mailer->CharSet = sprintf("%s", $settings['charset']);
        $mailer->Encoding = 'base64';
        $mailer->isHTML(true);

        return $mailer;
    });

    $container->set(LoggerInterface::class, function (ContainerInterface $c) {
        $settings = $c->get('settings')['logger'];

        $handler = new RotatingFileHandler(
            $settings['path'] . '/app.log',
            $settings['maxFiles'],
            $settings['level'],
            true,
            0640
        );
        $handler->setFormatter(new LineFormatter(null, null, false, true));

        $logger = new Logger('app');
        $logger->pushHandler($handler);

        return $logger;
    });

    //
    // Helpers
    //

    $container->set(SqlHelper::class, function () {
        return new SqlHelper();
    });

    $container->set(ValidationHelper::class, function () {
        return new ValidationHelper();
    });

    //
    // Middlewares
    //

    $container->set(CSPMiddleware::class, function (ContainerInterface $c) {
        return new CSPMiddleware(
            $c->get(Twig::class)
        );
    });

    $container->set(PermissionMiddleware::class, function (ContainerInterface $c) {
        return new PermissionMiddleware(
            $c->get(TimesheetRepository::class),
            $c->get(UserRepository::class),
            $c->get(Twig::class)
        );
    });

    $container->set(TwigCsrfMiddleware::class, function (ContainerInterface $c) {
        return new TwigCsrfMiddleware(
            $c->get(Guard::class), // 'csrf'
            $c->get(Twig::class)
        );
    });

    //
    // Session
    //

    $container->set(SessionManager::class, function (ContainerInterface $c) {
        $settings = $c->get('settings');

        return new SessionManager(
            $c->get(\SessionHandlerInterface::class),
            [
                'name'             => $settings['session']['name'],
                'use_strict_mode'  => true,
                'use_cookies'      => 1,
                'use_only_cookies' => 1,
                'cookie_lifetime'  => (int) $settings['session']['lifetime'],
                'cookie_path'      => '/',
                'cookie_domain'    => $settings['app']['domain'],
                'cookie_secure'    => $settings['session']['cookie_secure']   ?? true,
                'cookie_httponly'  => true,
                'cookie_samesite'  => $settings['session']['cookie_samesite'] ?? 'Strict',
            ]
        );
    });

    $container->set(\SessionHandlerInterface::class, function (ContainerInterface $c) {
        $settings = $c->get('settings')['session'];

        return match ($settings['handler']) {
            'db' => new DatabaseSessionHandler(
                $c->get(PDO::class),
                (int) $settings['lifetime']
            ),
            'local' => LocalSessionHandlerFactory::create($settings),
            default => new SessionHandler(), // stockage fichiers PHP natif
        };
    });

    $container->set(SessionMiddleware::class, function (ContainerInterface $c) {
        return new SessionMiddleware(
            $c->get(SessionManager::class),
            $c
        );
    });

    //
    // Services
    //

    $container->set(ActivityService::class, function (ContainerInterface $c) {
        return new ActivityService(
            $c->get(ActivityRepository::class),
            $c->get(ValidationHelper::class),
            $c->get(LoggerInterface::class),
            $c->get('translations')
        );
    });

    $container->set(AuthService::class, function (ContainerInterface $c) {
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
    });

    $container->set(CustomerService::class, function (ContainerInterface $c) {
        return new CustomerService(
            $c->get(CustomerRepository::class),
            $c->get(ValidationHelper::class),
            $c->get(LoggerInterface::class),
            $c->get('translations')
        );
    });

    $container->set(PasswordRequestService::class, function (ContainerInterface $c) {
        $settings = $c->get('settings')['auth'];

        return new PasswordRequestService(
            $c->get(UserRepository::class),
            $c->get(PHPMailer::class),
            $c->get(LoggerInterface::class),
            [
              'pwdRequestRetryLifetime' => max(1, (int)($settings['pwdRequestRetryLifetime'] ?? 300)),
              'pwdRequestTokenLifetime' => max(1, (int)($settings['pwdRequestTokenLifetime'] ?? 3600)),
              'pwdRequestSalt'          => (string)$settings['pwdRequestSalt'],
              'pwdMinLength'            => max(1, (int)($settings['pwdMinLength'] ?? 16)),
            ],
            $c->get('translations')
        );
    });

    //
    // Repositories
    //

    $container->set(ActivityRepository::class, function (ContainerInterface $c) {
        return new ActivityRepository(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    });

    $container->set(CustomerRepository::class, function (ContainerInterface $c) {
        return new CustomerRepository(
            $c->get(PDO::class),
            $c->get(SqlHelper::class),
            $c->get(LoggerInterface::class)
        );
    });

    $container->set(LoginAttemptsRepository::class, function (ContainerInterface $c) {
        return new LoginAttemptsRepository(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    });

    $container->set(ProjectRepository::class, function (ContainerInterface $c) {
        return new ProjectRepository(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    });

    $container->set(RoleRepository::class, function (ContainerInterface $c) {
        return new RoleRepository(
            $c->get(PDO::class)
        );
    });

    $container->set(TagRepository::class, function (ContainerInterface $c) {
        return new TagRepository(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    });

    $container->set(TeamRepository::class, function (ContainerInterface $c) {
        return new TeamRepository(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    });

    $container->set(TimesheetRepository::class, function (ContainerInterface $c) {
        return new TimesheetRepository(
            $c->get(PDO::class),
            $c->get(SqlHelper::class),
            $c->get(LoggerInterface::class)
        );
    });

    $container->set(UserRepository::class, function (ContainerInterface $c) {
        return new UserRepository(
            $c->get(PDO::class),
            $c->get(SqlHelper::class),
            $c->get(LoggerInterface::class)
        );
    });



};
