<?php

namespace app\controller;

use Redirect;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

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
        $idUsuarioSesion = $_SESSION['usuario_id'] ?? null;

        if (isset($_GET['id'])) {
            $idUsuario = (int) $_GET['id'];
        } else {
            if ($idUsuarioSesion === null) {
                Redirect::to('/login/show');
                return;
            }

            $idUsuario = (int) $idUsuarioSesion;
        }

        if ($idUsuario <= 0) {
            Redirect::to('/login/show');
            return;
        }

        $usuario = $this->model->obtenerUsuarioPorId($idUsuario);

        if (!$usuario) {
            Redirect::to('/login/show');
            return;
        }

        $baseUrl = 'https://preguntadospw2.infinityfreeapp.com';

        $urlPerfil = $baseUrl
            . '/perfil/show?id='
            . (int) $usuario['id'];

        $builderQr = Builder::create()
            ->writer(new SvgWriter())
            ->data($urlPerfil)
            ->size(300)
            ->margin(12);

        $resultadoQr = $builderQr->build();

        $qrPerfil = $resultadoQr->getDataUri();

        $partidasFinalizadas = $this->model->obtenerPartidasFinalizadas((int) $idUsuario);
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
            'tienePartidas' => $tienePartidas,

            'qrPerfil' => $qrPerfil,
            'urlPerfil' => $urlPerfil
        ];

        $this->render->renderiza('perfil', $datos);
    }
}
