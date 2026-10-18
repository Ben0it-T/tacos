<?php
declare(strict_types=1);

use App\Middleware\CSPMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\SessionMiddleware;
use App\Middleware\TwigCsrfMiddleware;

use Slim\App;
use Slim\Csrf\Guard;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;
use Slim\Views\Twig;

return function (App $app): void {

    $container = $app->getContainer();

    // Register Middlewares
    $app->add(TwigCsrfMiddleware::class);
    $app->add(PermissionMiddleware::class);
    $app->addRoutingMiddleware();
    $app->addBodyParsingMiddleware();
    $app->add(Guard::class);
    $app->add(SessionMiddleware::class);
    $app->add(CSPMiddleware::class);

    // Error Handling Middleware
    $settings = $container->get('settings')['error'];
    $errorMiddleware = $app->addErrorMiddleware(
        (bool)$settings['displayErrorDetails'],
        (bool)$settings['logErrors'],
        (bool)$settings['logErrorDetails']);

    // Not Found Handler
    $errorMiddleware->setErrorHandler(
        HttpNotFoundException::class,
        function ($request, Throwable $exception) use ($container) {
            $twig = $container->get(Twig::class);
            $response = new Response();
            return $twig->render($response->withStatus(404), 'not-found.html.twig');
        }
    );

    // Method Not Allowed Handler: redirect to the timesheets
    // (e.g. a GET on a POST-only route: logout, stop, restart)
    $errorMiddleware->setErrorHandler(
        HttpMethodNotAllowedException::class,
        function ($request, Throwable $exception) use ($app) {
            $url = $app->getRouteCollector()->getRouteParser()->urlFor('timesheets');
            return (new Response())->withStatus(302)->withHeader('Location', $url);
        }
    );
};
