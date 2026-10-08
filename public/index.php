<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Localiza o código da aplicação. Em desenvolvimento ele fica logo acima de public/.
// Na hospedagem compartilhada (cPanel), o conteúdo de public/ vai para public_html/
// e o restante para ~/mostraqui-app, fora da pasta pública.
$base = is_file(__DIR__.'/../vendor/autoload.php') ? __DIR__.'/..' : __DIR__.'/../mostraqui-app';

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
