<?php
// El controlador de login guarda el nombre de usuario en esta clave.
$sesionIniciada = isset($_SESSION['usuario_id']);
$nombre = $_SESSION['usuario'] ?? ($nombre ?? 'Jugador');

if (function_exists('mb_substr')) {
    $inicial = mb_strtoupper(mb_substr($nombre, 0, 1));
} else {
    $inicial = preg_match('/^./u', $nombre, $coincidencia)
            ? strtoupper($coincidencia[0])
            : '';
}

$paginaActiva = $_GET['controller'] ?? 'inicio';

function claseActiva($pagina, $paginaActiva)
{
    return $pagina === $paginaActiva
            ? ' class="activo" aria-current="page"'
            : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preguntados (Provisional)</title>

    <link rel="stylesheet"
          href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="/css/header.css">
    <link rel="stylesheet" href="/css/login.css">
</head>
<body<?= $paginaActiva === 'login' ? ' class="pagina-login"' : '' ?>>

<header class="barra-superior">
    <div class="barra-contenido">

        <a class="logo"
           href="/index.php?controller=inicio&amp;method=show"
           aria-label="Preguntados: ir al inicio">
            <span class="logo-icono" aria-hidden="true">?</span>
            <span class="logo-texto">Preguntados</span>
        </a>

        <nav class="barra-nav" aria-label="Navegación principal">
            <a href="/index.php?controller=inicio&amp;method=show"
                    <?= claseActiva('inicio', $paginaActiva) ?>>
                Inicio
            </a>

            <a href="/index.php?controller=ranking&amp;method=show"
                    <?= claseActiva('ranking', $paginaActiva) ?>>
                Ranking
            </a>

            <a href="/index.php?controller=perfil&amp;method=show"
                    <?= claseActiva('perfil', $paginaActiva) ?>>
                Mi perfil
            </a>
        </nav>

        <div class="acciones-usuario">
            <details class="usuario">
                <summary>
                    <span class="avatar" aria-hidden="true">
                        <?= htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <span class="usuario-nombre">
                        <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <svg class="usuario-flecha"
                         viewBox="0 0 24 24"
                         aria-hidden="true">
                        <path d="M6 9l6 6 6-6"/>
                    </svg>
                </summary>

                <div class="usuario-menu">
                    <a href="/login/logout">Cerrar sesión</a>
                </div>
            </details>

            <a class="boton-login"
               href="index.php?controller=login&amp;method=show">
                Iniciar sesión
            </a>
        </div>
    </div>
</header>

