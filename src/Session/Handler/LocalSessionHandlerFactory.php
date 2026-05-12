<?php
declare(strict_types=1);

namespace App\Session\Handler;

use SessionHandler;

final class LocalSessionHandlerFactory
{

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
