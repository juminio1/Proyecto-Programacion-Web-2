<?php

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Los archivos existentes, como CSS e imágenes, se sirven normalmente.
if ($ruta !== '/' && is_file(__DIR__ . $ruta)) {
    return false;
}

$partes = explode('/', trim($ruta, '/'));

$_GET['controller'] = $_GET['controller']
    ?? ($partes[0] !== '' ? $partes[0] : 'inicio');

$_GET['method'] = $_GET['method']
    ?? ($partes[1] ?? 'show');

require __DIR__ . '/index.php';