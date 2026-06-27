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

use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;

use App\Session\SessionStoreInterface;

use App\Service\UserService;


use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

use PHPMailer\PHPMailer\PHPMailer;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use Slim\Csrf\Guard;
use Slim\Views\Twig;

return [
    // Application settings
    'settings' => fn() => require __DIR__ . '/settings.php',

    //
    // Infra
    //

    'translations' => function (ContainerInterface $c) {
        $settings = $c->get('settings')['lang'];
        $translations = require sprintf("%s/%s.php", $settings['path'], 'en_US');

        // Overwrite default lang with local lang
        $langFile = sprintf('%s/%s.php', $settings['path'], $settings['default']);
        if (file_exists($langFile)) {
            $localLang = require $langFile;
            $translations  = array_merge($translations, $localLang);
        }

        return $translations;
    },

    PDO::class => function (ContainerInterface $c) {
        $db = $c->get('settings')['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['dbname'],
            $db['charset']
        );
        return new PDO($dsn, $db['user'], $db['pass'], $db['options']);
    },

    Twig::class => function (ContainerInterface $c) {
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
    },

    PHPMailer::class => function (ContainerInterface $c) {
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
    },

    LoggerInterface::class => function (ContainerInterface $c) {
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
    },

    //
    // Helpers
    //

    ControllerHelper::class => function (ContainerInterface $c) {
        return new ControllerHelper(
            $c->get(UserService::class),
            $c->get(SessionStoreInterface::class),
        );
    },

    RoundingHelper::class => function () {
        return new RoundingHelper();
    },

    SqlHelper::class => function () {
        return new SqlHelper();
    },

    ValidationHelper::class => function () {
        return new ValidationHelper();
    },


    //
    // Middlewares
    //

    CSPMiddleware::class => function (ContainerInterface $c) {
        return new CSPMiddleware(
            $c->get(Twig::class)
        );
    },

    PermissionMiddleware::class => function (ContainerInterface $c) {
        return new PermissionMiddleware(
            $c->get(TimesheetRepository::class),
            $c->get(UserRepository::class),
            $c->get(Twig::class)
        );
    },

    TwigCsrfMiddleware::class => function (ContainerInterface $c) {
        return new TwigCsrfMiddleware(
            $c->get(Guard::class), // 'csrf'
            $c->get(Twig::class)
        );
    },




];
