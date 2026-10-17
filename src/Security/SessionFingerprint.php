<?php
declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

final class SessionFingerprint
{
    /**
     * Fingerprint of the user's password hash
     * Stored in session: any password change invalidates all existing sessions
     *
     * @param User $user
     * @return string
     */
    public static function forUser(User $user): string
    {
        return hash('sha256', (string) $user->getPassword());
    }
}
