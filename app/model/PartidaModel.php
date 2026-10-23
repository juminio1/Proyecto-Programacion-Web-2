<?php

namespace app\model;

use app\model\enums\EstadoPartida;
use PDO;

class PartidaModel{

    private $database;

    public function __construct($database){
        $this->database = $database;
    }


    public function crearPartida($idUsuario){
        $errores = [];

        if (!$this->validarUsuarioExistente($idUsuario)) {
            return ["El usuario no existe."];
        }

        if ($this->validarPartidaExistente($idUsuario)) {
            return ["Ya tenés una partida creada o en curso."];
        }
        $pdo = $this->database->getConexion(); //Se crea la partida

        $estadoInicial = EstadoPartida::CREADA;

        $sql = "INSERT INTO partidas ( usuario_id, estado, puntaje)
                VALUES (:usuario_id, :estado, 0)";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'usuario_id' => $idUsuario,
            'estado' => $estadoInicial->value
        ]);

        return $errores;
    }

    private function validarPartidaExistente($idUsuario){
        $pdo = $this->database->getConexion();

        $sql = "SELECT id
                FROM partidas
                WHERE usuario_id = :usuario_id
                  AND estado IN (:creada, :en_curso)
                LIMIT 1";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'usuario_id' => $idUsuario,
            'creada' => EstadoPartida::CREADA->value,
            'en_curso' => EstadoPartida::EN_CURSO->value
        ]);

        $partidaEncontrada = $consulta->fetch(PDO::FETCH_ASSOC);

        return $partidaEncontrada !== false;
    }

    private function validarUsuarioExistente($idUsuario){
        $pdo = $this->database->getConexion();

        $sql = "SELECT id
                FROM usuarios
                WHERE id = :usuario_id
                LIMIT 1";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'usuario_id' => $idUsuario
        ]);

        $usuarioEncontrado = $consulta->fetch(PDO::FETCH_ASSOC);

        return $usuarioEncontrado !== false;
    }


}