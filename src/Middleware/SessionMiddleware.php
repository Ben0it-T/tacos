<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Session\SessionManager;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Flash\Messages;



final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionManager $sessionManager, private ContainerInterface $container) {
        //
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $this->sessionManager->start();

        // Messages::class
        $this->container->set('flash', function () {
            return new Messages($_SESSION);
        });

        $request = $request->withAttribute('session', $_SESSION);

        $response = $handler->handle($request);

        $this->sessionManager->close();

        return $response;
    }
}
