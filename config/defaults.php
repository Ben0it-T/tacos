<?php

// Application default settings
$settings = [];
$lifetime = 60*60*24;

// Error reporting
error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Session lifetime
ini_set('session.gc_maxlifetime', $lifetime);

// Timezone
date_default_timezone_set('Europe/Paris');

// App settings
$settings['app'] = [
    'basepath' => '',
    'domain'   => '',
];

// Error handler
$settings['error'] = [
    'displayErrorDetails' => false,
    'logErrors'           => false,
    'logErrorDetails'     => false,
];

// Logger settings
$settings['logger'] = [
    'path'     => dirname(__DIR__) . '/var/logs',
    'maxFiles' => 7,
    'level'    => Psr\Log\LogLevel::ERROR,
];

// Twig settings
$settings['twig'] = [
    'templates' => dirname(__DIR__) . '/templates',
    'cache'     => dirname(__DIR__) . '/var/cache',
];

// Mail settings
$settings['mailer'] = [
    'mode' => 'mail',
    'charset'   => 'UTF-8',
    'from_name' => 'Tacos',
];

// Database settings
$settings['db'] = [
    'host' => 'localhost',
    'port' => 3306,
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ],
];

// Session settings
$settings['session'] = [
    'handler'  => 'files',
    'name'     => 'tacos_sid',
    'lifetime' => $lifetime,
];

// Language settings
$settings['lang'] = [
    'default'  => 'en_US',
    'path'     => dirname(__DIR__) . '/lang',
];

// Auth setings
$settings['auth'] = [
    'loginMinLength' => 5,
    'pwdMinLength'   => 12,
    'pwdRequestSalt' => '|----unique-phrase----|',
    'pwdRequestRetryLifetime' => 3600,
    'pwdRequestTokenLifetime' => 86400,
];

// Theme
$settings['theme'] = [
    'colorChoices' => 'Blue|#0079bf,Green|#70b500,Orange|#ff9f1a,Red|#eb5a46,Yellow|#f2d600,Purple|#c377e0,Pink|#ff78cb,Sky|#00c2e0,Lime|#51e898,Light Gray|#c4c9cc,Business Blue|#42548E',
];

return $settings;
