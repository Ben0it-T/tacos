<?php
declare(strict_types=1);

namespace App\Security;

enum ResetPasswordResult: string{
    case INVALID_TOKEN = 'invalid_token';
    case INVALID_PASSWORD = 'invalid_password';
    case PASSWORD_MISMATCH = 'password_mismatch';
    case SUCCESS = 'success';
    case ERROR = 'error';
}
