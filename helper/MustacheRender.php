<?php

require_once __DIR__ . "/../vendor/mustache/Mustache/Autoloader.php";
Mustache_Autoloader::register(__DIR__ . "/../vendor/mustache");

class MustacheRender
{
    private $mustache;

    public function __construct()
    {
        $this->mustache = new Mustache_Engine([
            "loader" => new Mustache_Loader_FilesystemLoader(
                dirname(__DIR__) . "/app/view",
                ["extension" => ".mustache"]
            )
        ]);
    }

    public function renderiza($view, $data = [])
    {
        $contexto = $this->contextoEncabezado();
        $contenido = $this->mustache->render($view . "View", $data);

        echo $this->mustache->render("headerView", $contexto);
        echo $contenido;
        echo $this->mustache->render("footerView", $contexto);
    }

    private function contextoEncabezado(): array
    {
        $sesionIniciada = (int) ($_SESSION['usuario_id'] ?? 0) > 0;
        $esEditor = $sesionIniciada && ($_SESSION['rol'] ?? null) === 'editor';
        $esAdmin = $sesionIniciada && ($_SESSION['rol'] ?? null) === 'admin';
        $esJugador = $sesionIniciada && !$esEditor && !$esAdmin;
        $nombre = (string) ($_SESSION['usuario'] ?? 'Jugador');

        if (function_exists('mb_substr')) {
            $inicial = mb_strtoupper(mb_substr($nombre, 0, 1, 'UTF-8'), 'UTF-8');
        } else {
            $inicial = preg_match('/^./u', $nombre, $coincidencia)
                ? strtoupper($coincidencia[0])
                : '';
        }

        $ruta = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $segmentos = explode('/', trim($ruta, '/'));
        $pagina = $_GET['controller'] ?? $segmentos[0];
        $pagina = is_string($pagina) ? strtolower($pagina) : '';
        if ($pagina === '' || $pagina === 'index.php') {
            $pagina = 'inicio';
        }

        return [
            'sesionIniciada' => $sesionIniciada,
            'esEditor' => $esEditor,
            'esAdmin' => $esAdmin,
            'esJugador' => $esJugador,
            'nombre' => $nombre,
            'inicial' => $inicial,
            'enlaceLogo' => $esAdmin ? '/admin/show' : ($esEditor ? '/editor/show' : '/'),
            'enlaceInicio' => $esAdmin ? '/admin/show' : ($esEditor ? '/editor/show' : '/inicio/show'),
            'paginaLogin' => $pagina === 'login',
            'editorActivo' => $pagina === 'editor',
            'adminActivo' => $pagina === 'admin',
            'inicioActivo' => $pagina === 'inicio',
            'rankingActivo' => $pagina === 'ranking',
            'perfilActivo' => $esJugador && $pagina === 'perfil',
        ];
    }
}
