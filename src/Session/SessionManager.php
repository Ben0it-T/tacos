<?php
declare(strict_types=1);

namespace App\Session;

use SessionHandlerInterface;

final class SessionManager
{
    /**
     * @param SessionHandlerInterface $handler
     * @param array<string, mixed> $options session_start() options (name, cookie_lifetime, cookie_secure, cookie_samesite...)
     */
    public function __construct(private SessionHandlerInterface $handler, private array $options) {
        //
    }

    /**
     * Start the session (if not already started) with the configured handler and options,
     * then refresh the session cookie
     *
     * @return void
     */
    public function start(): void {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        session_set_save_handler($this->handler, true);
        session_start($this->options);

        $this->refreshSessionCookie();
    }

    /**
     * Write and close the active session
     *
     * @return void
     */
    public function close(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Send the session cookie again with a renewed expiration (sliding lifetime)
     *
     * @return void
     */
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
