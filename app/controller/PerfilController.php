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
        $idSesion = $_SESSION['usuario_id'] ?? null;

        /*
         * Si viene ?id= mostramos ese perfil.
         * Esto permite acceder mediante QR aunque no haya sesión.
         */
        if (isset($_GET['id'])) {
            $idUsuario = (int) $_GET['id'];
        } else {
            /*
             * Si no viene id, significa que quiere entrar a "Mi perfil".
             * Para eso sí necesita estar logueado.
             */
            if ($idSesion === null) {
                Redirect::to('/login/show');
                return;
            }

            $idUsuario = (int) $idSesion;
        }

        if ($idUsuario <= 0) {
            Redirect::to('/inicio/show');
            return;
        }

        $usuario = $this->model->obtenerUsuarioPorId($idUsuario);

        if (!$usuario) {
            Redirect::to('/inicio/show');
            return;
        }

        /*
         * Indica si el perfil que estoy viendo es el mío.
         * Si entré mediante QR sin sesión, será false.
         */
        $esMiPerfil = $idSesion !== null
            && $idUsuario === (int) $idSesion;


        /* =====================================================
           QR DEL PERFIL
           ===================================================== */

        $baseUrl = 'https://preguntadospw2.infinityfreeapp.com';

        $urlPerfil = $baseUrl
            . '/perfil/show?id='
            . (int) $usuario['id'];

        try {

            $builderQr = Builder::create()
                ->writer(new SvgWriter())
                ->data($urlPerfil)
                ->size(300)
                ->margin(12);

            $resultadoQr = $builderQr->build();

            $qrPerfil = $resultadoQr->getDataUri();

        } catch (\Throwable $e) {

            die(
                'ERROR QR: '
                . htmlspecialchars($e->getMessage())
            );
        }


        /* =====================================================
           ESTADÍSTICAS
           ===================================================== */

        $partidasFinalizadas = $this->model->obtenerPartidasFinalizadas($idUsuario);

        $totalPartidas = $this->model->calcularTotalPartidas(
            $partidasFinalizadas
        );

        $puntajeAcumulado = $this->model->calcularPuntajeAcumulado(
            $partidasFinalizadas
        );

        $tienePartidas = $totalPartidas > 0;


        /* =====================================================
           FOTO
           ===================================================== */

        $fotoPerfil = null;

        if (!empty($usuario['foto_perfil'])) {
            $fotoPerfil = '/' . ltrim(
                    $usuario['foto_perfil'],
                    '/'
                );
        }


        /* =====================================================
           INICIAL
           ===================================================== */

        $nombreReferencia = !empty($usuario['nombre_completo'])
            ? $usuario['nombre_completo']
            : $usuario['nombre_usuario'];

        $inicial = '';

        if (function_exists('mb_substr')) {
            $inicial = mb_strtoupper(
                mb_substr($nombreReferencia, 0, 1)
            );
        } else {
            $inicial = strtoupper(
                substr($nombreReferencia, 0, 1)
            );
        }


        /* =====================================================
           FECHA DE REGISTRO
           ===================================================== */

        $fechaRegistro = '-';

        if (!empty($usuario['fecha_registro'])) {
            $timestamp = strtotime(
                $usuario['fecha_registro']
            );

            if ($timestamp !== false) {
                $fechaRegistro = date(
                    'd/m/Y',
                    $timestamp
                );
            }
        }


        /* =====================================================
           UBICACIÓN
           ===================================================== */

        $latitud = (
            $usuario['latitud'] !== null
            && $usuario['latitud'] !== ''
        )
            ? (float) $usuario['latitud']
            : null;

        $longitud = (
            $usuario['longitud'] !== null
            && $usuario['longitud'] !== ''
        )
            ? (float) $usuario['longitud']
            : null;

        $tieneCoordenadas = (
            $latitud !== null
            && $longitud !== null
        );


        /* =====================================================
           DATOS DE LA VISTA
           ===================================================== */

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

            'nivel' => ucfirst(
                $usuario['nivel'] ?? 'medio'
            ),

            'puntajeAcumulado' => $puntajeAcumulado,
            'totalPartidas' => $totalPartidas,

            'partidas' => $partidasFinalizadas,
            'tienePartidas' => $tienePartidas,

            'qrPerfil' => $qrPerfil,
            'urlPerfil' => $urlPerfil
        ];

        $this->render->renderiza(
            'perfil',
            $datos
        );
    }
}