<?php

namespace app\model;
use PDO;

class LoginModel{

    private $database;

    public  function __construct($database){
        $this->database = $database;
    }

    public function buscarUsuario($identificador){
        $pdo = $this->database->getConexion();

        $sql = "SELECT id, nombre_usuario, password, activo
                FROM usuarios
                WHERE nombre_usuario = :username
                OR email = :email";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'username' => $identificador,
            'email' => $identificador
        ]);

        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function iniciarSesion($identificador, $password){
        $errores = [];

        if ($identificador === '' || $password === '') {
            $errores[] = "Los campos son obligatorios";
            return $errores;
        }

        $usuarioEncontrado = $this->buscarUsuario($identificador);

        if ($usuarioEncontrado === false) {
            $errores[] = "El usuario no existe";
            return $errores;
        }

        if (!password_verify($password, $usuarioEncontrado['password'])) {
            $errores[] = "Usuario o contraseña incorrectos.";
            return $errores;
        }

        /*if ((int)$usuarioEncontrado['activo'] !== 1) {
            $errores[] = "Tenés que activar tu cuenta antes de iniciar sesión.";
            return $errores;
        }*/

        return $errores;
    }

}
