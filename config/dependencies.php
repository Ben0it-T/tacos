<?php
declare(strict_types=1);

use App\Middleware\CSPMiddleware;

use DI\Container;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

use PHPMailer\PHPMailer\PHPMailer;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use Slim\Flash\Messages;
use Slim\Views\Twig;

return function (ContainerInterface $container): void {

    // Translations
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

    // Flash messages
    $container->set('flash', function () {
        $storage = [];
        return new Messages($storage);
    });

    // PDO
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

    // Twig
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

    // PHPMailer
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

    // Logger
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
    // Middlewares
    //
    $container->set(CSPMiddleware::class, function (ContainerInterface $c) {
    return new CSPMiddleware(
        $c->get(Twig::class)
    );
});

};
