<?php

namespace app\controller;

use Redirect;
use Request;
use Endroid\QrCode\QrCode;
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
        $idSesion =
            $_SESSION['usuario_id'] ?? null;


        /* =====================================================
           USUARIO A MOSTRAR
           ===================================================== */

        if (isset($_GET['id'])) {

            $idUsuario =
                (int) $_GET['id'];

        } else {

            if ($idSesion === null) {
                Redirect::to('/login/show');
                return;
            }

            $idUsuario =
                (int) $idSesion;
        }


        if ($idUsuario <= 0) {
            Redirect::to('/inicio/show');
            return;
        }


        $usuario =
            $this->model
                ->obtenerUsuarioPorId(
                    $idUsuario
                );


        if (!$usuario) {
            Redirect::to('/inicio/show');
            return;
        }


        /* =====================================================
           PRIVACIDAD
           ===================================================== */

        $esMiPerfil =
            $idSesion !== null
            && $idUsuario === (int) $idSesion;


        $perfilPublico =
            (int) (
                $usuario['perfil_publico']
                ?? 1
            ) === 1;


        /*
         * El dueño siempre ve su perfil completo.
         *
         * Otro usuario solo ve la información completa
         * cuando el perfil está configurado como público.
         */
        $mostrarDatosPrivados =
            $esMiPerfil
            || $perfilPublico;


        $perfilLimitado =
            !$mostrarDatosPrivados;


        if ($esMiPerfil) {
            $_SESSION['perfil_privacidad_csrf']
                ??= bin2hex(
                    random_bytes(32)
                );
        }


        $mensajePrivacidad =
            $_SESSION[
            'perfil_privacidad_mensaje'
            ] ?? null;


        unset(
            $_SESSION[
            'perfil_privacidad_mensaje'
            ]
        );


        /* =====================================================
           QR
           ===================================================== */

        $baseUrl =
            'https://preguntadospw2.infinityfreeapp.com';


        $urlPerfil =
            $baseUrl
            . '/perfil/show?id='
            . (int) $usuario['id'];


        $qrCode =
            QrCode::create($urlPerfil)
                ->setSize(300)
                ->setMargin(12);


        $writer =
            new SvgWriter();


        $resultadoQr =
            $writer->write($qrCode);


        $qrPerfil =
            $resultadoQr->getDataUri();


        /* =====================================================
           TOTAL DE PARTIDAS + PUNTAJE
           ===================================================== */

        $resumenPartidas =
            $this->model
                ->obtenerResumenPartidasFinalizadas(
                    $idUsuario
                );


        $totalPartidas =
            $resumenPartidas[
            'totalPartidas'
            ];


        $puntajeAcumulado =
            $resumenPartidas[
            'puntajeAcumulado'
            ];


        /* =====================================================
           HISTORIAL
           ===================================================== */

        /*
         * Si otro usuario está viendo un perfil privado,
         * directamente NO consultamos el detalle de las partidas.
         */
        $partidasFinalizadas =
            $mostrarDatosPrivados
                ? $this->model
                ->obtenerPartidasFinalizadas(
                    $idUsuario
                )
                : [];


        $tienePartidas =
            !empty(
            $partidasFinalizadas
            );


        /* =====================================================
           FOTO
           ===================================================== */

        $fotoPerfil = null;


        if (
            !empty(
            $usuario['foto_perfil']
            )
        ) {

            $fotoPerfil =
                '/'
                . ltrim(
                    $usuario['foto_perfil'],
                    '/'
                );
        }


        /* =====================================================
           INICIAL
           ===================================================== */

        $nombreReferencia =
            !empty(
            $usuario['nombre_completo']
            )
                ? $usuario[
            'nombre_completo'
            ]
                : $usuario[
            'nombre_usuario'
            ];


        if (
            function_exists(
                'mb_substr'
            )
        ) {

            $inicial =
                mb_strtoupper(
                    mb_substr(
                        $nombreReferencia,
                        0,
                        1
                    )
                );

        } else {

            $inicial =
                strtoupper(
                    substr(
                        $nombreReferencia,
                        0,
                        1
                    )
                );
        }


        /* =====================================================
           FECHA DE REGISTRO
           ===================================================== */

        $fechaRegistro = '-';


        if (
            !empty(
            $usuario[
            'fecha_registro'
            ]
            )
        ) {

            $timestamp =
                strtotime(
                    $usuario[
                    'fecha_registro'
                    ]
                );


            if ($timestamp !== false) {

                $fechaRegistro =
                    date(
                        'd/m/Y',
                        $timestamp
                    );
            }
        }


        /* =====================================================
           UBICACIÓN
           ===================================================== */

        $latitud = null;
        $longitud = null;
        $tieneCoordenadas = false;


        /*
         * Si el perfil es privado para el visitante,
         * las coordenadas directamente no llegan a la vista.
         */
        if ($mostrarDatosPrivados) {

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


            $tieneCoordenadas =
                $latitud !== null
                && $longitud !== null;
        }


        /*
         * En perfil privado visto por otra persona
         * se muestra solamente el país.
         */
        $ubicacionCabecera =
            $mostrarDatosPrivados
                ? trim(
                (
                    $usuario['ciudad']
                    ?? ''
                )
                . (
                !empty(
                $usuario['ciudad']
                )
                && !empty(
                $usuario['pais']
                )
                    ? ', '
                    : ''
                )
                . (
                    $usuario['pais']
                    ?? ''
                )
            )
                : (
                $usuario['pais']
                ?? ''
            );


        /* =====================================================
           DATOS PARA MUSTACHE
           ===================================================== */

        $datos = [

            'id' =>
                $usuario['id'],


            'esMiPerfil' =>
                $esMiPerfil,


            'perfilPublico' =>
                $perfilPublico,

            'perfilPrivado' =>
                !$perfilPublico,

            'mostrarDatosPrivados' =>
                $mostrarDatosPrivados,

            'perfilLimitado' =>
                $perfilLimitado,


            'tokenPrivacidad' =>
                $esMiPerfil
                    ? $_SESSION[
                'perfil_privacidad_csrf'
                ]
                    : null,


            'mensajePrivacidad' =>
                $mensajePrivacidad,


            /* Siempre visibles */

            'nombre_completo' =>
                $usuario[
                'nombre_completo'
                ],

            'nombre_usuario' =>
                $usuario[
                'nombre_usuario'
                ],

            'pais' =>
                $usuario['pais'],

            'nivel' =>
                ucfirst(
                    $usuario['nivel']
                    ?? 'medio'
                ),

            'fecha_registro' =>
                $fechaRegistro,

            'foto_perfil' =>
                $fotoPerfil,

            'tieneFoto' =>
                !empty(
                $fotoPerfil
                ),

            'inicial' =>
                $inicial,

            'puntajeAcumulado' =>
                $puntajeAcumulado,

            'totalPartidas' =>
                $totalPartidas,

            'qrPerfil' =>
                $qrPerfil,

            'urlPerfil' =>
                $urlPerfil,


            /* Datos sensibles */

            'email' =>
                $mostrarDatosPrivados
                    ? $usuario['email']
                    : null,

            'sexo' =>
                $mostrarDatosPrivados
                    ? $usuario['sexo']
                    : null,

            'anio_nacimiento' =>
                $mostrarDatosPrivados
                    ? $usuario[
                'anio_nacimiento'
                ]
                    : null,

            'ciudad' =>
                $mostrarDatosPrivados
                    ? $usuario['ciudad']
                    : null,


            /* Ubicación */

            'ubicacionCabecera' =>
                $ubicacionCabecera,

            'latitud' =>
                $latitud,

            'longitud' =>
                $longitud,

            'tieneCoordenadas' =>
                $tieneCoordenadas,


            /* Historial */

            'partidas' =>
                $partidasFinalizadas,

            'tienePartidas' =>
                $tienePartidas
        ];


        $this->render->renderiza(
            'perfil',
            $datos
        );
    }


    public function cambiarPrivacidad(): void
    {
        if (
            ($_SERVER['REQUEST_METHOD']
                ?? 'GET')
            !== 'POST'
        ) {

            http_response_code(405);

            header(
                'Allow: POST'
            );

            return;
        }


        $idUsuario =
            (int) (
                $_SESSION[
                'usuario_id'
                ] ?? 0
            );


        if ($idUsuario <= 0) {

            Redirect::to(
                '/login/show'
            );

            return;
        }


        /* =====================================================
           CSRF
           ===================================================== */

        $token =
            Request::post(
                'csrf_token'
            );


        $tokenGuardado =
            $_SESSION[
            'perfil_privacidad_csrf'
            ] ?? null;


        if (
            !is_string($token)
            || !is_string(
                $tokenGuardado
            )
            || !hash_equals(
                $tokenGuardado,
                $token
            )
        ) {

            http_response_code(403);

            header(
                'Content-Type: text/plain; charset=utf-8'
            );

            echo
            'Volvé a abrir tu perfil para cambiar la privacidad.';

            return;
        }


        /* =====================================================
           ESTADO SELECCIONADO
           ===================================================== */

        $valorPrivacidad =
            (string)
            Request::post(
                'perfil_publico',
                ''
            );


        if (
            !in_array(
                $valorPrivacidad,
                ['0', '1'],
                true
            )
        ) {

            http_response_code(400);

            header(
                'Content-Type: text/plain; charset=utf-8'
            );

            echo
            'El estado de privacidad seleccionado no es válido.';

            return;
        }


        $perfilPublico =
            $valorPrivacidad === '1';


        $this->model
            ->actualizarPrivacidadPerfil(
                $idUsuario,
                $perfilPublico
            );


        $_SESSION[
        'perfil_privacidad_csrf'
        ] =
            bin2hex(
                random_bytes(32)
            );


        $_SESSION[
        'perfil_privacidad_mensaje'
        ] =
            $perfilPublico
                ? 'Tu perfil ahora es público.'
                : 'Tu perfil ahora es privado.';


        Redirect::to(
            '/perfil/show'
        );
    }
}