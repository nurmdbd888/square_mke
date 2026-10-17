<?php

/*
| Fallback front controller, only for hosts where cPanel refuses a document
| root outside public_html. Copy everything from ~/mke/app/public into the
| subdomain's document root, then replace index.php with this file.
| See DEPLOY.md, "If cPanel will not accept mke/app/public".
*/

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appRoot = null;

for ($dir = __DIR__; $dir !== dirname($dir); $dir = dirname($dir)) {
    if (is_file($dir.'/mke/app/artisan')) {
        $appRoot = $dir.'/mke/app';
        break;
    }
}

if ($appRoot === null) {
    http_response_code(500);
    exit('Square Distributor Manager: the folder mke/app was not found in your home folder.');
}

require $appRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';

$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
