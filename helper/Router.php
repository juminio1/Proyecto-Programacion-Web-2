<?php

use app\model\enums\RolUsuario;

class Router
{
    private $config;
    private $defaultController;
    private $defaultMethod;
    private $obtenerRol;

    public function __construct($config, $defaultController, $defaultMethod, callable $obtenerRol)
    {
        $this->config = $config;
        $this->defaultController = $defaultController;
        $this->defaultMethod = $defaultMethod;
        $this->obtenerRol = $obtenerRol;
    }

    public function dispatch($controllerName, $method)
    {
        $controllerName = is_string($controllerName) ? strtolower($controllerName) : '';
        $method = is_string($method) ? strtolower($method) : '';
        $factoryMethod = $this->getFactoryMethodName($controllerName);

        if (!is_callable([$this->config, $factoryMethod])) {
            $controllerName = $this->defaultController;
            $factoryMethod = $this->getFactoryMethodName($controllerName);
        }

        $this->autorizar($controllerName, $method);

        $controller = $this->config->{$factoryMethod}();
        if ($method === '' || $method[0] === '_' || !is_callable([$controller, $method])) {
            $method = $this->defaultMethod;
        }

        $controller->{$method}();
    }

    private function getFactoryMethodName($controllerName)
    {
        return 'get' . ucfirst($controllerName) . 'Controller';
    }

    private function autorizar(string $controller, string $method): void
    {
        if ($controller === 'login' && $method === 'logout' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            // El logout puede limpiar la sesión aunque la base de datos no responda.
            return;
        }

        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
        $rol = $idUsuario > 0 ? ($this->obtenerRol)($idUsuario) : null;

        if ($idUsuario > 0 && !in_array($rol, [RolUsuario::JUGADOR, RolUsuario::EDITOR], true)) {
            $_SESSION = [];
            Redirect::to('/login/show');
        }

        if ($rol !== null) {
            $_SESSION['rol'] = $rol;
        }

        if ($controller === 'editor') {
            if ($rol === null) {
                Redirect::to('/login/show');
            }
            if ($rol !== RolUsuario::EDITOR) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Esta página es exclusiva para editores.';
                exit;
            }
            if (!in_array($method, ['', 'show', 'sugerencias', 'aprobar', 'rechazar'], true)) {
                http_response_code(404);
                exit;
            }
            return;
        }

        if ($rol === RolUsuario::EDITOR && !($controller === 'login' && $method === 'logout')) {
            if ($controller === 'partida' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Los editores no pueden realizar esta acción.']);
                exit;
            }
            Redirect::to('/editor/show');
        }
    }
}
