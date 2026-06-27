<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Build DI container
$container = (new ContainerBuilder())
    ->addDefinitions(__DIR__ . '/container.php')
    ->addDefinitions(__DIR__ . '/repositories.php')
    ->addDefinitions(__DIR__ . '/services.php')
    ->addDefinitions(__DIR__ . '/controllers.php')
    ->build();

// Create App
AppFactory::setContainer($container);
$app = AppFactory::create();

// Bridge
$container->set(
    ResponseFactoryInterface::class,
    $app->getResponseFactory()
);

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
