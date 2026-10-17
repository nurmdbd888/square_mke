<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firm
    |--------------------------------------------------------------------------
    |
    | Printed on every page, invoice, challan and report. From M1 these become
    | editable settings stored in the database; these values are the defaults.
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
