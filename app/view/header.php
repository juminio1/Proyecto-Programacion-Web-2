<?php
$nombre  = $nombre ?? ($_SESSION['nombre'] ?? 'Jugador');
if (function_exists('mb_substr')) {
    $inicial = mb_strtoupper(mb_substr($nombre, 0, 1));
} else {
    // Sin la extensión mbstring: toma el primer carácter (UTF-8) con una regex
    $inicial = preg_match('/^./u', $nombre, $coincidencia) ? strtoupper($coincidencia[0]) : '';
}

/* Página activa según la URL */
$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (strpos($ruta, '/ranking') === 0) {
    $paginaActiva = 'ranking';
} elseif (strpos($ruta, '/perfil') === 0) {
    $paginaActiva = 'perfil';
} else {
    $paginaActiva = 'inicio';
}

function claseActiva($pagina, $paginaActiva)
{
    return $pagina === $paginaActiva ? ' class="activo" aria-current="page"' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preguntados (Provisional) </title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="/css/header.css">
</head>
<body>

<header class="barra-superior">
    <div class="barra-contenido">

        <a class="logo" href="/config.ini/index.php?controller=inicio&amp;method=show">
            <span class="logo-icono" aria-hidden="true">?</span>
            <span class="logo-texto">Preguntados</span>
        </a>

        <nav class="barra-nav" aria-label="Navegación principal">
            <a href="/config.ini/index.php?controller=inicio&amp;method=show"<?= claseActiva('inicio', $paginaActiva) ?>>Inicio</a>
            <a href="/ranking/show"<?= claseActiva('ranking', $paginaActiva) ?>>Ranking</a>
            <a href="/perfil/show"<?= claseActiva('perfil', $paginaActiva) ?>>Mi perfil</a>
        </nav>

        <details class="usuario">
            <summary>
                <span class="avatar" aria-hidden="true"><?= htmlspecialchars($inicial) ?></span>
                <span class="usuario-nombre"><?= htmlspecialchars($nombre) ?></span>
                <svg class="usuario-flecha" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </summary>

            <div class="usuario-menu">
                <a href="/login/logout">Cerrar sesión</a>
            </div>
        </details>

    </div>
</header>