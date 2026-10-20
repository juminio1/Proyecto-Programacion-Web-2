<?php

namespace app\controller;

use Request;
use Redirect;

class RegistroController
{
    private $model;
    private $render;

    public function __construct($model, $render)
    {
        $this->model  = $model;
        $this->render = $render;
    }

    public function show()
    {
        $this->render->renderiza("registro");
    }

    public function registrar()
    {
        $datos = [
            'nombre_completo'    => Request::post('nombre_completo', ''),
            'anio_nacimiento'    => Request::post('anio_nacimiento', ''),
            'sexo'               => Request::post('sexo', ''),
            'pais'               => Request::post('pais', ''),
            'ciudad'             => Request::post('ciudad', ''),
            'latitud'            => Request::post('latitud', ''),
            'longitud'           => Request::post('longitud', ''),
            'email'              => Request::post('email', ''),
            'password'           => Request::post('password', ''),
            'confirmar_password' => Request::post('confirmar_password', ''),
            'nombre_usuario'     => Request::post('nombre_usuario', '')
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

        $this->model->crearUsuario($datos, $rutaFoto);

        Redirect::to('/index.php?controller=login&method=show');
    }

    private function renderizarConErrores(array $datos, array $errores)
    {
        $this->render->renderiza("registro", [
            'hay_errores'     => true,
            'errores'         => $errores,
            'nombre_completo' => $datos['nombre_completo'],
            'anio_nacimiento' => $datos['anio_nacimiento'],
            'pais'            => $datos['pais'],
            'ciudad'          => $datos['ciudad'],
            'latitud'         => $datos['latitud'],
            'longitud'        => $datos['longitud'],
            'email'           => $datos['email'],
            'nombre_usuario'  => $datos['nombre_usuario'],
            'sexo_masculino'  => $datos['sexo'] === 'Masculino',
            'sexo_femenino'   => $datos['sexo'] === 'Femenino',
            'sexo_no_cargado' => $datos['sexo'] === 'Prefiero no cargarlo'
        ]);
    }
}
