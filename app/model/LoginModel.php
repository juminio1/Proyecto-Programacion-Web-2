<?php

namespace app\model;

use PDO;

class LoginModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function autenticar(string $usuario, string $password): ?array
    {
        $usuarioLimpio = trim($usuario);

        if ($usuarioLimpio === '' || $password === '') {
            return null;
        }

        $passwordHash = md5($password);
        $pdo = $this->database->getConexion();

        $sql = "SELECT id, nombre_usuario, nombre_completo, email, foto_perfil, activo
                FROM usuarios
                WHERE (nombre_usuario = :usuario OR email = :usuario)
                  AND password = :password
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':usuario', $usuarioLimpio, PDO::PARAM_STR);
        $stmt->bindValue(':password', $passwordHash, PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : $fila;
    }
}
