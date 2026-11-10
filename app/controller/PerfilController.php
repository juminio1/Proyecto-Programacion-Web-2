<?php

namespace app\controller;

use Redirect;

class PerfilController
{
    private $model;
    private $render;

    public function __construct($model, $render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function show()
    {
        $idSesion = $_SESSION['usuario_id'] ?? null;

        if ($idSesion === null) {
            Redirect::to('/login/show');
            return;
        }

        // Si viene ?id= se muestra ese perfil, si no, el del usuario logueado
        $idUsuario = isset($_GET['id']) ? (int) $_GET['id'] : (int) $idSesion;

        $usuario = $this->model->obtenerUsuarioPorId($idUsuario);

        if (!$usuario) {
            Redirect::to('/inicio/show');
            return;
        }

        $esMiPerfil = ($idUsuario === (int) $idSesion);

        $partidasFinalizadas = $this->model->obtenerPartidasFinalizadas($idUsuario);
        $totalPartidas = $this->model->calcularTotalPartidas($partidasFinalizadas);
        $puntajeAcumulado = $this->model->calcularPuntajeAcumulado($partidasFinalizadas);
        $tienePartidas = $totalPartidas > 0;

        $fotoPerfil = null;
        if (!empty($usuario['foto_perfil'])) {
            $fotoPerfil = '/' . ltrim($usuario['foto_perfil'], '/');
        }

        $nombreReferencia = !empty($usuario['nombre_completo']) ? $usuario['nombre_completo'] : $usuario['nombre_usuario'];
        $inicial = '';
        if (function_exists('mb_substr')) {
            $inicial = mb_strtoupper(mb_substr($nombreReferencia, 0, 1));
        } else {
            $inicial = strtoupper(substr($nombreReferencia, 0, 1));
        }

        $fechaRegistro = '-';
        if (!empty($usuario['fecha_registro'])) {
            $timestamp = strtotime($usuario['fecha_registro']);
            if ($timestamp !== false) {
                $fechaRegistro = date('d/m/Y', $timestamp);
            }
        }

        $latitud = ($usuario['latitud'] !== null && $usuario['latitud'] !== '') ? (float) $usuario['latitud'] : null;
        $longitud = ($usuario['longitud'] !== null && $usuario['longitud'] !== '') ? (float) $usuario['longitud'] : null;
        $tieneCoordenadas = ($latitud !== null && $longitud !== null);

        $datos = [
            'id' => $usuario['id'],
            'esMiPerfil' => $esMiPerfil,
            'nombre_completo' => $usuario['nombre_completo'],
            'nombre_usuario' => $usuario['nombre_usuario'],
            'email' => $usuario['email'],
            'sexo' => $usuario['sexo'],
            'anio_nacimiento' => $usuario['anio_nacimiento'],
            'pais' => $usuario['pais'],
            'ciudad' => $usuario['ciudad'],
            'latitud' => $latitud,
            'longitud' => $longitud,
            'tieneCoordenadas' => $tieneCoordenadas,
            'foto_perfil' => $fotoPerfil,
            'tieneFoto' => !empty($fotoPerfil),
            'inicial' => $inicial,
            'fecha_registro' => $fechaRegistro,
            'nivel' => ucfirst($usuario['nivel'] ?? 'medio'),
            'puntajeAcumulado' => $puntajeAcumulado,
            'totalPartidas' => $totalPartidas,
            'partidas' => $partidasFinalizadas,
            'tienePartidas' => $tienePartidas
        ];

        $this->render->renderiza('perfil', $datos);
    }
}
