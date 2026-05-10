<?php
declare(strict_types=1);

namespace App\Security;

enum AuthResult: string
{
    case SUCCESS = 'success';
    case INVALID_CREDENTIALS = 'invalid_credentials';
    case BLOCKED = 'blocked';
}
