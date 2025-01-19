<?php

// Get environment
if (!file_exists(__DIR__ . '/env.php')) {
    copy(__DIR__ . '/env.php.dist', __DIR__ . '/env.php');
}
$env = require __DIR__ . '/env.php';

// Load default settings
$settings = require __DIR__ . '/defaults.php';

// Overwrite default settings with environment specific local settings
$configFile = __DIR__ . sprintf('/local.%s.php', $env);
if (file_exists($configFile)) {
    $localSettings = require $configFile;
    if (is_callable($localSettings)) {
        $settings = $localSettings($settings);
    }
}

return $settings;
