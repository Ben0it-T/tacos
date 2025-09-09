<?php
declare(strict_types=1);

use App\Middleware\CSPMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\SessionMiddleware;
use App\Middleware\TwigCsrfMiddleware;

use DI\ContainerBuilder;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

use Slim\Csrf\Guard;
use Slim\Factory\AppFactory;
use Slim\Flash\Messages;
use Slim\Views\Twig;

require __DIR__ . '/../vendor/autoload.php';

// Create container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    'settings' => fn () => require __DIR__ . '//settings.php',

    'translations' => function(ContainerInterface $container) {
        $settings = $container->get('settings')['lang'];
        $translations = require sprintf("%s/%s.php", $settings['path'], 'en_US');

        // Overwrite default lang with local lang
        $langFile = sprintf("%s/%s.php", $settings['path'], $settings['default']);
        if (file_exists($langFile)) {
            $localLang = require $langFile;
            $translations  = array_merge($translations, $localLang);
        }

        return $translations;
    },

    'flash' => function () {
        $storage = [];
        return new Messages($storage);
    },

    PDO::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['db'];
        $dsn = "mysql:host=" . $settings['host'] . ";port=" . $settings['port'] . ";dbname=" . $settings['dbname'] . ";charset=" . $settings['charset'];
        $pdo = new PDO($dsn, $settings['user'], $settings['pass'], $settings['options']);

        return $pdo;
    },

    Twig::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['twig'];
        $cache = is_bool($settings['cache']) === true ? (bool)$settings['cache'] : sprintf("%s", $settings['cache']);
        $twig  = Twig::create(sprintf("%s", $settings['templates']), [
            'cache' => $cache,
            'charset' => 'UTF-8',
            'strict_variables' => true,
            'autoescape' => 'html',
        ]);

        // Twig global vars
        $basepath = is_null($container->get('settings')['app']['basepath']) ? '/' : sprintf("%s", $container->get('settings')['app']['basepath']);
        $twig->getEnvironment()->addGlobal('basePath', $basepath);
        $twig->getEnvironment()->addGlobal('trans', $container->get('translations'));

        return $twig;
    },

    PHPMailer::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['mailer'];
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

    LoggerInterface::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['logger'];
        $filename = sprintf("%s/app.log", $settings['path']);
        $maxFiles = intval($settings['maxFiles']);
        $level = $settings['level'];

        $logger = new Logger('app');
        $rotatingFileHandler = new RotatingFileHandler($filename, $maxFiles, $level, true, 0777);
        $rotatingFileHandler->setFormatter(new LineFormatter(null, null, false, true));
        $logger->pushHandler($rotatingFileHandler);

        return $logger;
    },

    // TODO: ExceptionMiddleware
]);

// Build container
$container = $containerBuilder->build();

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();
$responseFactory = $app->getResponseFactory();

// BasePath
$basepath = is_null($container->get('settings')['app']['basepath']) ? '' : sprintf("%s", $container->get('settings')['app']['basepath']);
if (!empty($basepath)) $app->setBasePath($basepath);

// Register Middleware on Container
$container->set('csrf', fn () => new Guard($responseFactory, '_csrf'));

// Register Middleware
$app->add(TwigCsrfMiddleware::class);
$app->add(PermissionMiddleware::class);
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$app->add('csrf');
$app->add(SessionMiddleware::class);
// Error Handling Middleware
$settings = $container->get('settings')['error'];
$errorMiddleware = $app->addErrorMiddleware((bool)$settings['displayErrorDetails'], (bool)$settings['logErrors'], (bool)$settings['logErrorDetails']);
// TODO: not Found Handler
$app->add(CSPMiddleware::class);
// ----------


// Get routes
require __DIR__ . '//routes.php';

return $app;
