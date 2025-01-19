<?php

// Example environment

return function (array $settings): array {
    $lifetime = 60*60*12;

    // Error reporting
    error_reporting(E_ALL);                 // Should be set to 0 in production
    ini_set('display_errors', '1');         // Should be set to 0 in production
    ini_set('display_startup_errors', '1'); // Should be set to 0 in production

    // Session lifetime
    ini_set('session.gc_maxlifetime', $lifetime);

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
    $settings['session']['lifetime'] = $lifetime;

    // ...

    return $settings;
};
