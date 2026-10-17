<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preguntados (Provisional) </title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;600;700;800;900&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
            crossorigin="anonymous">
    <link rel="stylesheet" href="/css/header.css">
    <link rel="stylesheet" href="/css/footer.css">
</head>
<body>

<header class="encabezado">
    <div class="encabezado-contenido">
        <a class="marca" href="/index.php?controller=inicio&amp;method=show"
           aria-label="Preguntados, ir al inicio">
            <span class="marca-icono" aria-hidden="true">?</span>
            <span class="marca-nombre">Preguntados</span>
        </a>

        <nav class="acciones-header" aria-label="Navegación principal">
            <a class="enlace-inicio" href="/index.php?controller=inicio&amp;method=show">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="m3 10 9-7 9 7M5 9v11h5v-6h4v6h5V9"/>
                </svg>
                <span>Inicio</span>
            </a>

            <a class="btn boton-login" href="/index.php?controller=login&amp;method=show">
                <span>Iniciar sesión</span>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M14 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5M4 12h11m-4-4 4 4-4 4"/>
                </svg>
            </a>
        </nav>
    </div>
</header>
