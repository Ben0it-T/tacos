<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Build DI container
$container = (new ContainerBuilder())
    ->addDefinitions(__DIR__ . '/container.php')
    ->addDefinitions(__DIR__ . '/repositories.php')
    ->addDefinitions(__DIR__ . '/services.php')
    ->build();

// Register dependencies
(require __DIR__ . '/dependencies.php')($container);

// Create App
AppFactory::setContainer($container);
$app = AppFactory::create();

// BasePath
$basepath = $container->get('settings')['app']['basepath'] ?? '';
if (!empty($basepath)) {
    $app->setBasePath($basepath);
}

// Register middleware
(require __DIR__ . '/middleware.php')($app);

// Register routes
(require __DIR__ . '/routes.php')($app);

return $app;
