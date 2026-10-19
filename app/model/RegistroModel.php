<?php

namespace app\model;

use Log;
use PDO;

class RegistroModel
{
    private $database;

    private const SEXOS_PERMITIDOS = [
        'Masculino',
        'Femenino',
        'Prefiero no cargarlo'
    ];

    private const EXTENSIONES_FOTO_PERMITIDAS = [
        'jpg',
        'jpeg',
        'png',
        'webp'
    ];

    private const TAMANIO_MAXIMO_FOTO = 2097152; // 2 MB en bytes

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function validarDatos(array $datos, ?array $archivoFoto): array
    {
        $errores = [];

        $nombreCompleto    = trim($datos['nombre_completo'] ?? '');
        $anioNacimiento    = trim((string)($datos['anio_nacimiento'] ?? ''));
        $sexo              = trim($datos['sexo'] ?? '');
        $pais              = trim($datos['pais'] ?? '');
        $ciudad            = trim($datos['ciudad'] ?? '');
        $email             = trim($datos['email'] ?? '');
        $password          = $datos['password'] ?? '';
        $confirmarPassword = $datos['confirmar_password'] ?? '';
        $nombreUsuario     = trim($datos['nombre_usuario'] ?? '');

        if (
            $nombreCompleto === '' ||
            $anioNacimiento === '' ||
            $sexo === '' ||
            $pais === '' ||
            $ciudad === '' ||
            $email === '' ||
            $password === '' ||
            $confirmarPassword === '' ||
            $nombreUsuario === ''
        ) {
            $errores[] = "Todos los campos obligatorios deben estar completos.";
        }

        if ($anioNacimiento !== '') {
            $anioInt = filter_var($anioNacimiento, FILTER_VALIDATE_INT);
            $anioActual = (int)date('Y');
            if ($anioInt === false || $anioInt < 1900 || $anioInt > $anioActual) {
                $errores[] = "El año de nacimiento no es válido.";
            }
        }

        if ($sexo !== '' && !in_array($sexo, self::SEXOS_PERMITIDOS, true)) {
            $errores[] = "El sexo seleccionado no es válido.";
        }

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores[] = "El formato del correo electrónico no es válido.";
            } elseif ($this->existeEmail($email)) {
                $errores[] = "El correo electrónico ya está registrado.";
            }
        }

        if ($nombreUsuario !== '' && $this->existeUsuario($nombreUsuario)) {
            $errores[] = "El nombre de usuario ya está en uso.";
        }

        if ($password !== '' && $confirmarPassword !== '' && $password !== $confirmarPassword) {
            $errores[] = "Las contraseñas no coinciden.";
        }

        $erroresFoto = $this->validarFoto($archivoFoto);
        foreach ($erroresFoto as $errorFoto) {
            $errores[] = $errorFoto;
        }

        return $errores;
    }

    public function validarFoto(?array $archivoFoto): array
    {
        $errores = [];

        if (
            $archivoFoto === null ||
            !isset($archivoFoto['error']) ||
            $archivoFoto['error'] === UPLOAD_ERR_NO_FILE ||
            empty($archivoFoto['name'])
        ) {
            $errores[] = "La foto de perfil es obligatoria.";
            return $errores;
        }

        if ($archivoFoto['error'] !== UPLOAD_ERR_OK) {
            $errores[] = "Ocurrió un error al subir la foto de perfil.";
            return $errores;
        }

        if (($archivoFoto['size'] ?? 0) > self::TAMANIO_MAXIMO_FOTO) {
            $errores[] = "La foto de perfil no debe superar los 2 MB.";
        }

        $extension = strtolower(pathinfo($archivoFoto['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONES_FOTO_PERMITIDAS, true)) {
            $errores[] = "El formato de la foto no es válido. Formatos permitidos: JPG, JPEG, PNG, WEBP.";
        }

        return $errores;
    }

    public function existeEmail(string $email): bool
    {
        $pdo = $this->database->getConexion();
        $sql = "SELECT id FROM usuarios WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function existeUsuario(string $nombreUsuario): bool
    {
        $pdo = $this->database->getConexion();
        $sql = "SELECT id FROM usuarios WHERE nombre_usuario = :nombre_usuario LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':nombre_usuario', $nombreUsuario, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function guardarFotoPerfil(array $archivoFoto, string $directorioBase): ?string
    {
        if (!is_dir($directorioBase)) {
            mkdir($directorioBase, 0755, true);
        }

        $extension = strtolower(pathinfo($archivoFoto['name'], PATHINFO_EXTENSION));
        $nombreArchivo = uniqid('perfil_', true) . '.' . $extension;
        $rutaAbsoluta = rtrim($directorioBase, '/\\') . DIRECTORY_SEPARATOR . $nombreArchivo;

        if (!move_uploaded_file($archivoFoto['tmp_name'], $rutaAbsoluta)) {
            return null;
        }

        return 'public/uploads/perfiles/' . $nombreArchivo;
    }

    public function crearUsuario(array $datos, string $rutaFoto): string
    {
        $pdo = $this->database->getConexion();
        $tokenActivacion = bin2hex(random_bytes(16));
        $passwordHasheada = password_hash(
            $datos['password'],
            PASSWORD_DEFAULT
        );

        $latitud = isset($datos['latitud']) && $datos['latitud'] !== '' ? (float)$datos['latitud'] : null;
        $longitud = isset($datos['longitud']) && $datos['longitud'] !== '' ? (float)$datos['longitud'] : null;

        $sql = "INSERT INTO usuarios (
                    nombre_completo,
                    anio_nacimiento,
                    sexo,
                    pais,
                    ciudad,
                    latitud,
                    longitud,
                    email,
                    password,
                    nombre_usuario,
                    foto_perfil,
                    activo,
                    token_activacion
                ) VALUES (
                    :nombre_completo,
                    :anio_nacimiento,
                    :sexo,
                    :pais,
                    :ciudad,
                    :latitud,
                    :longitud,
                    :email,
                    :password,
                    :nombre_usuario,
                    :foto_perfil,
                    :activo,
                    :token_activacion
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':nombre_completo', trim($datos['nombre_completo']), PDO::PARAM_STR);
        $stmt->bindValue(':anio_nacimiento', (int)$datos['anio_nacimiento'], PDO::PARAM_INT);
        $stmt->bindValue(':sexo', trim($datos['sexo']), PDO::PARAM_STR);
        $stmt->bindValue(':pais', trim($datos['pais']), PDO::PARAM_STR);
        $stmt->bindValue(':ciudad', trim($datos['ciudad']), PDO::PARAM_STR);
        $stmt->bindValue(':latitud', $latitud, $latitud === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':longitud', $longitud, $longitud === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':email', trim($datos['email']), PDO::PARAM_STR);
        $stmt->bindValue(':password', $passwordHasheada, PDO::PARAM_STR);
        $stmt->bindValue(':nombre_usuario', trim($datos['nombre_usuario']), PDO::PARAM_STR);
        $stmt->bindValue(':foto_perfil', $rutaFoto, PDO::PARAM_STR);
        $stmt->bindValue(':activo', 0, PDO::PARAM_INT);
        $stmt->bindValue(':token_activacion', $tokenActivacion, PDO::PARAM_STR);

        $stmt->execute();

        if (class_exists('Log')) {
            Log::info("Usuario registrado pendiente de activación: " . trim($datos['nombre_usuario']));
        }

        return $tokenActivacion;
    }
}
