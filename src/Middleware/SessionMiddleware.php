<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Repository\SessionRepository;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Flash\Messages;

final class SessionMiddleware implements MiddlewareInterface
{

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $settings = $this->container->get('settings')['session'];
        $pdo = $this->container->get(PDO::class);
        $lifetime = intval($settings['lifetime']);
        $name     = sprintf("%s", $settings['name']);
        $domain   = sprintf("%s", $this->container->get('settings')['app']['domain']);

        // db storage
        if ($settings['handler'] == 'db') {
            $session = new SessionRepository($pdo, $lifetime);
        }

        // local file storage
        if ($settings['handler'] == 'local' && isset($settings['path'])) {
            ini_set('session.save_path', realpath($settings['path']));
            ini_set('session.gc_probability', 1);
            ini_set('session.gc_divisor', 1);
        }

        // session start
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'name' => $name,
                'use_strict_mode' => true,
                'use_cookies' => 1,
                'use_only_cookies' => 1,
                'cookie_lifetime' => $lifetime,
                'cookie_path' => '/',
                'cookie_domain' => $domain,
                'cookie_secure' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict'
            ]);
            $this->container->get('flash')->__construct($_SESSION);
            $request = $request->withAttribute('session', $_SESSION);
        }

        $arr_cookie_options = array (
            'expires' => time() + $lifetime,
            'path' => '/',
            'domain' => $domain,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        );
        setcookie(session_name(), session_id(), $arr_cookie_options);


        $response = $handler->handle($request);

        session_write_close();

        return $response;
    }
}
