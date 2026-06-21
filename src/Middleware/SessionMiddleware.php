<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionManager $sessionManager) {
        //
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $this->sessionManager->start();

        $request = $request->withAttribute('session', $_SESSION);

        $response = $handler->handle($request);

        $this->sessionManager->close();

        return $response;
    }
}
