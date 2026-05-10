<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Csrf\Guard;
use Slim\Views\Twig;

final class TwigCsrfMiddleware implements MiddlewareInterface
{
    private Guard $csrf;
    private Twig $twig;

    public function __construct(Guard $csrf, Twig $twig)
    {
        $this->csrf = $csrf;
        $this->twig = $twig;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $csrfNameKey  = $this->csrf->getTokenNameKey();
        $csrfValueKey = $this->csrf->getTokenValueKey();

        $csrfName  = $request->getAttribute($csrfNameKey);
        $csrfValue = $request->getAttribute($csrfValueKey);

        $this->twig->getEnvironment()->addGlobal('csrf', array(
            'nameKey'  => $csrfNameKey,
            'valueKey' => $csrfValueKey,
            'name'     => $csrfName,
            'value'    => $csrfValue,
        ));

        return $handler->handle($request);
    }
}
