<?php
declare(strict_types=1);

namespace App\Session\Handler;

use SessionHandler;

final class LocalSessionHandlerFactory
{
    /**
     * Create the native (file) session handler and configure its storage
     *
     * @param array<string, mixed> $settings Session settings (path: storage directory, lifetime: gc max lifetime in seconds)
     * @return SessionHandler
     */
    public static function create(array $settings): SessionHandler {
        if (!empty($settings['path'])) {
            ini_set('session.save_path', realpath($settings['path']));
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '1');
        }

        if (!empty($settings['lifetime'])) {
            ini_set('session.gc_maxlifetime', (string) $settings['lifetime']);
        }

        return new SessionHandler();
    }
}
