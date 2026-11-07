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
    return $pagina === $paginaActiva ? ' class="activo" aria-current="page"' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preguntados (Provisional)</title>

    <script>
        (function () {
            try {
                var tema = localStorage.getItem('tema');
                if (!tema) {
                    tema = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'oscuro' : 'claro';
                }
                if (tema === 'oscuro') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    document.documentElement.classList.add('dark-mode');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                    document.documentElement.classList.remove('dark-mode');
                }
            } catch (e) {}
        })();
    </script>

    <link rel="stylesheet"
          href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="/css/header.css">
    <link rel="stylesheet" href="/css/login.css">
    <link rel="stylesheet" href="/css/dark-mode.css">
    <script src="/js/dark-mode.js" defer></script>
</head>
<body<?= $paginaActiva === 'login' ? ' class="pagina-login"' : '' ?>>

<header class="barra-superior">
    <div class="barra-contenido">

        <a class="logo" href="/">
            <span class="logo-icono" aria-hidden="true">?</span>
            <span class="logo-texto">Preguntados</span>
        </a>

        <nav class="barra-nav" aria-label="Navegación principal">
            <a href="/inicio/show"<?= claseActiva('inicio', $paginaActiva) ?>>Inicio</a>
            <a href="/ranking/show"<?= claseActiva('ranking', $paginaActiva) ?>>Ranking</a>
            <a href="/perfil/show"<?= claseActiva('perfil', $paginaActiva) ?>>Mi perfil</a>
        </nav>

        <div class="acciones-usuario">
            <button type="button"
                    id="boton-modo-oscuro"
                    class="boton-tema"
                    role="switch"
                    aria-checked="false"
                    aria-label="Cambiar a modo oscuro"
                    title="Cambiar a modo oscuro">
                <span class="boton-tema-icono" aria-hidden="true">
                    <!-- Icono Sol (se muestra en modo oscuro) -->
                    <svg class="icono-sol" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5"></circle>
                        <line x1="12" y1="1" x2="12" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="23"></line>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                        <line x1="1" y1="12" x2="3" y2="12"></line>
                        <line x1="21" y1="12" x2="23" y2="12"></line>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                    <!-- Icono Luna (se muestra en modo claro) -->
                    <svg class="icono-luna" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" stroke="none">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </span>
                <span class="boton-tema-texto">Modo oscuro</span>
            </button>

            <?php if ($sesionIniciada): ?>
            <details class="usuario">
                <summary>
                    <span class="avatar" aria-hidden="true"><?= htmlspecialchars($inicial) ?></span>
                    <span class="usuario-nombre"><?= htmlspecialchars($nombre) ?></span>
                    <svg class="usuario-flecha" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </summary>

                <div class="usuario-menu">
                    <form method="post" action="/login/logout">
                        <button type="submit" class="usuario-boton-salir">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </details>
            <?php endif; ?>

            <?php if (empty($_SESSION['usuario_id'])): ?>
                <a class="boton-login"
                   href="/login/show">
                    Iniciar sesión
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>
