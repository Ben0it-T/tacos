<?php

// Example environment

return function (array $settings): array {

    // Error reporting
    error_reporting(E_ALL);                 // Should be set to 0 in production
    ini_set('display_errors', '1');         // Should be set to 0 in production
    ini_set('display_startup_errors', '1'); // Should be set to 0 in production

    // App settings
    $settings['app']['basepath'] = '/yourbasepath';
    $settings['app']['domain']   = '.yourdomain.com';  // leading dot for compatibility or use subdomain

    // Error handler
    $settings['error']['displayErrorDetails'] = true; // Should be set to false in production
    $settings['error']['logErrors']           = true; // Parameter is passed to the default ErrorHandler
    $settings['error']['logErrorDetails']     = true; // Display error details in error log

    // Logger
    $settings['logger']['maxFiles'] = 1;
    $settings['logger']['level'] = Psr\Log\LogLevel::DEBUG;

    // Mailer
    $settings['mailer']['from_addr'] = 'no-reply@domain.com';
    $settings['mailer']['mode'] = 'smtp';
        // 'mail'     - sending a message using PHP's mail() function.
        // 'sendmail' - sending a message using a local sendmail binary
        // 'smtp'     - ssending a message through SMTP server.
    $settings['mailer']['smtp_host']   = 'localhost';
    $settings['mailer']['smtp_port']   = 465;
    $settings['mailer']['smtp_auth']   = true;
    $settings['mailer']['smtp_user']   = 'yourname@yourdomain.com';
    $settings['mailer']['smtp_pass']   = 'yourpassword';
    $settings['mailer']['smtp_secure'] = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        // ENCRYPTION_SMTPS    - port 465
        // ENCRYPTION_STARTTLS - port 587
    $settings['mailer']['smtp_debug'] = PHPMailer\PHPMailer\SMTP::DEBUG_OFF; // 'DEBUG_SERVER' || 'DEBUG_OFF'

    // Database
    $settings['db']['dbname'] = 'dbname';
    $settings['db']['user']   = 'username';
    $settings['db']['pass']   = 'password';

    // Session
    // 'files'  - file storage
    // 'local'  - local file storage
    // 'db'     - db storage
    $settings['session']['handler'] = 'local';
    $settings['session']['path'] = dirname(__DIR__) . '/var/sessions';
    $settings['session']['lifetime'] = 60 * 60 * 12;
    $settings['session']['cookie_secure'] = true;    // Set to false if using HTTP in local dev
    $settings['session']['cookie_samesite'] = 'Lax';

    // Auth settings
    $settings['auth']['loginMinLength'] = 7;
    $settings['auth']['pwdMinLength'] = 16;
    $settings['auth']['pwdRequestSalt'] = '|----set-your-unique-phrase----|';
    $settings['auth']['loginAttempts']['maxAttempts'] = 3;
    $settings['auth']['loginAttempts']['blockDelay'] = 900; // seconds

    // Timesheet
    // rounding mode : ceil, floor, closest, none
    $settings['timesheet']['rounding']['start']['mode'] = 'floor';
    $settings['timesheet']['rounding']['start']['minutes'] = 5;
    $settings['timesheet']['rounding']['end']['mode'] = 'ceil';
    $settings['timesheet']['rounding']['end']['minutes'] = 5;


    // ...

    return $settings;
};
