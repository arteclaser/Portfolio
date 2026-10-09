<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Localiza o código da aplicação. Em desenvolvimento ele fica logo acima de public/.
// Na hospedagem compartilhada (cPanel), o conteúdo de public/ vai para a pasta pública do
// domínio e o restante para ~/mostraqui-app, fora dela. A implantação automática ajusta a
// linha abaixo conforme a pasta pública (ex.: ../mostraqui-app ou ../../mostraqui-app).
$appPath = __DIR__.'/../mostraqui-app';
$base = is_file(__DIR__.'/../vendor/autoload.php') ? __DIR__.'/..' : $appPath;

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
