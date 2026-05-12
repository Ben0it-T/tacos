<?php
declare(strict_types=1);

namespace App\Session;

use SessionHandlerInterface;

final class SessionManager
{
    public function __construct(private SessionHandlerInterface $handler, private array $options) {
        //
    }

    public function start(): void {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        session_set_save_handler($this->handler, true);
        session_start($this->options);

        $this->refreshSessionCookie();
    }

    public function close(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    private function refreshSessionCookie(): void {
        $cookieParams = session_get_cookie_params();
        setcookie(
            session_name(),
            session_id(),
            [
                'expires'  => time() + ($this->options['cookie_lifetime'] ?? 0),
                'path'     => $cookieParams['path'],
                'domain'   => $cookieParams['domain'],
                'secure'   => $this->options['cookie_secure'] ?? true,
                'httponly' => $cookieParams['httponly'],
                'samesite' => $this->options['cookie_samesite'] ?? 'Strict',
            ]
        );
    }
}
