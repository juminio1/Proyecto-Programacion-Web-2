<?php

namespace app\controller;

use Request;
use Redirect;

class RegistroController
{
    private $model;
    private $render;
    private $mailer;
    private $config;

    public function __construct($model, $render, $mailer, $config)
    {
        $this->model = $model;
        $this->render = $render;
        $this->mailer = $mailer;
        $this->config = $config;
    }

    public function show()
    {
        $this->render->renderiza("registro");
    }

    public function registrar()
    {
        $datos = [
            'nombre_completo' => Request::post('nombre_completo', ''),
            'anio_nacimiento' => Request::post('anio_nacimiento', ''),
            'sexo' => Request::post('sexo', ''),
            'pais' => Request::post('pais', ''),
            'ciudad' => Request::post('ciudad', ''),
            'latitud' => Request::post('latitud', ''),
            'longitud' => Request::post('longitud', ''),
            'email' => Request::post('email', ''),
            'password' => Request::post('password', ''),
            'confirmar_password' => Request::post('confirmar_password', ''),
            'nombre_usuario' => Request::post('nombre_usuario', '')
        ];

        $archivoFoto = $_FILES['foto_perfil'] ?? null;

        $errores = $this->model->validarDatos($datos, $archivoFoto);

        if (!empty($errores)) {
            $this->renderizarConErrores($datos, $errores);
            return;
        }

        $directorioFotos = dirname(__DIR__, 2) . '/public/uploads/perfiles';
        $rutaFoto = $this->model->guardarFotoPerfil($archivoFoto, $directorioFotos);

        if ($rutaFoto === null) {
            $this->renderizarConErrores($datos, ["No se pudo guardar la foto de perfil en el servidor."]);
            return;
        }

        $tokenActivacion = $this->model->crearUsuario($datos, $rutaFoto);

        $baseUrl = rtrim($this->config['base_url'], '/');

        $enlaceActivacion = $baseUrl
            . '/registro/activar?token='
            . urlencode($tokenActivacion);

        $correoEnviado = $this->mailer->enviarActivacion(
            $datos['email'],
            $this->config['smtp_user'],
            $this->config['smtp_password'],
            $enlaceActivacion
        );

        if (!$correoEnviado) {
            $this->renderizarConErrores($datos, [
                'La cuenta fue creada, pero no se pudo enviar el correo de activación.'
            ]);

            return;
        }

        $this->render->renderiza("registro", [
            'exito' => true,
            'nombre_usuario' => $datos['nombre_usuario'],
            'email' => $datos['email']
        ]);
    }

    private function renderizarConErrores(array $datos, array $errores)
    {
        $this->render->renderiza("registro", [
            'hay_errores' => true,
            'errores' => $errores,
            'nombre_completo' => $datos['nombre_completo'],
            'anio_nacimiento' => $datos['anio_nacimiento'],
            'pais' => $datos['pais'],
            'ciudad' => $datos['ciudad'],
            'latitud' => $datos['latitud'],
            'longitud' => $datos['longitud'],
            'email' => $datos['email'],
            'nombre_usuario' => $datos['nombre_usuario'],
            'sexo_masculino' => $datos['sexo'] === 'Masculino',
            'sexo_femenino' => $datos['sexo'] === 'Femenino',
            'sexo_no_cargado' => $datos['sexo'] === 'Prefiero no cargarlo'
        ]);
    }

    public function activar()
    {
        $token = Request::get('token', '');

        if (empty($token)) {
            $this->render->renderiza("activacion", [
                'mensaje' => 'El enlace de activación no es válido.',
                'activada' => false
            ]);

            return;
        }

        $activado = $this->model->activarUsuario($token);

        if ($activado) {
            $this->render->renderiza("activacion", [
                'mensaje' => 'Tu cuenta fue activada correctamente.',
                'activada' => true
            ]);
        } else {
            $this->render->renderiza("activacion", [
                'mensaje' => 'El enlace es inválido o ya fue utilizado.',
                'activada' => false
            ]);
        }
    }

}
