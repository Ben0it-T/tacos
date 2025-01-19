<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final class TwigCsrfMiddleware implements MiddlewareInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $twig = $this->container->get(Twig::class);
        $csrf  = $this->container->get('csrf');

        $csrfNameKey = $csrf->getTokenNameKey();
        $csrfValueKey = $csrf->getTokenValueKey();
        $csrfName = $request->getAttribute($csrfNameKey);
        $csrfValue = $request->getAttribute($csrfValueKey);

        $twig->getEnvironment()->addGlobal('csrf', array(
            'nameKey' => $csrfNameKey,
            'valueKey' => $csrfValueKey,
            'name' => $csrfName,
            'value' => $csrfValue,
        ));

        return $handler->handle($request);
    }
}
