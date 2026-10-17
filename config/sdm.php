<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firm
    |--------------------------------------------------------------------------
    |
    | Printed on every page, invoice, challan and report. These are only the
    | defaults: the owner changes them in Settings (settings table).
    |
    */

    'firm_name' => env('SDM_FIRM_NAME', 'M/s Khaza Enterprise'),

    'distributor_code' => env('SDM_DISTRIBUTOR_CODE', '4831'),

    'supported_locales' => [
        'en' => 'English',
        'bn' => 'বাংলা',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance page
    |--------------------------------------------------------------------------
    */

    'maintenance' => [
        'enabled' => (bool) env('MAINTENANCE_ENABLED', true),
        'token' => (string) env('MAINTENANCE_TOKEN', ''),
        'token_min_length' => 20,
        'session_minutes' => 120,
        // Lets the secret phrase alone reset an owner's password. Turn it off again after use.
        'owner_recovery' => (bool) env('MAINTENANCE_OWNER_RECOVERY', false),
        'max_attempts' => 5,
        'decay_seconds' => 600,
        // Must not depend on the database: the page has to work before migrations.
        'throttle_store' => env('MAINTENANCE_THROTTLE_STORE', 'file'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cron heartbeat
    |--------------------------------------------------------------------------
    */

    'heartbeat' => [
        'fresh_minutes' => 3,
        'keep_days' => 7,
    ],

    'min_php' => '8.4.0',

    'required_extensions' => [
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'iconv',
        'intl', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'session',
        'simplexml', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
    ],

];
