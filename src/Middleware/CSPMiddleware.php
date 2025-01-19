<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final class CSPMiddleware implements MiddlewareInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $nonce = base64_encode(random_bytes(20));

        $twig = $this->container->get(Twig::class);
        //$twig->offsetSet('nonce', $nonce);
        $twig->getEnvironment()->addGlobal('nonce', $nonce);

        $policy = "default-src 'none'; script-src 'nonce-" . $nonce . "'; connect-src 'self'; img-src 'self' data:; style-src 'self'; font-src 'self'; base-uri 'self'; form-action 'self'; manifest-src 'self'; style-src-attr 'unsafe-inline';";
        $response = $handler->handle($request);

        $response = $response
            ->withHeader('X-Powered-By', '')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block;')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Content-Security-Policy', $policy)
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubdomains;');

        return $response;
    }
}
