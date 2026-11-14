<?php


if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../helper/MyDatabase.php';

function prepararEditor(bool $soloSql): void
{
    $archivo = __DIR__ . '/../config/config.local.ini';
    if (!is_readable($archivo)) {
        throw new RuntimeException('Falta config/config.local.ini.');
    }

    $config = parse_ini_file($archivo, false, INI_SCANNER_RAW);
    if ($config === false) {
        throw new RuntimeException('No se pudo leer config/config.local.ini.');
    }

    $prefijo = $soloSql ? 'editor_hosting_' : 'editor_';
    $usuario = (string)($config[$prefijo . 'usuario'] ?? '');
    $email = (string)($config[$prefijo . 'email'] ?? '');
    $password = (string)($config[$prefijo . 'password'] ?? '');
    if (!preg_match('/\A[A-Za-z0-9_.-]{1,50}\z/', $usuario)
        || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($password) < 10 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new RuntimeException("Completá {$prefijo}usuario, {$prefijo}email y {$prefijo}password (10 a 72 bytes).");
    }

    if ($soloSql) {
        imprimirSqlEditor($usuario, $email, $password);
        return;
    }

    $host = (string)($config['db_host'] ?? '');
    $base = (string)($config['db_name'] ?? '');
    if (!preg_match('/\A(?:localhost|127\.0\.0\.1|::1)(?:;port=[0-9]{1,5})?\z/i', $host)
        || !preg_match('/\A[A-Za-z0-9_-]+\z/', $base)) {
        throw new RuntimeException('Este comando requiere una base local y un db_name válido.');
    }
    if (!isset($config['db_user'], $config['db_pass'])) {
        throw new RuntimeException('Completá db_user y db_pass en config/config.local.ini.');
    }

    $pdo = (new MyDatabase($host, $config['db_user'], $config['db_pass'], $base))->getConexion();
    if (!$pdo->query("SHOW COLUMNS FROM usuarios LIKE 'rol'")->fetch()) {
        throw new RuntimeException('Primero ejecutá app/sql/migraciones/editor.sql en tu base local.');
    }

    $pdo->beginTransaction();
    try {
        $buscar = $pdo->prepare(
            'SELECT id, nombre_usuario, email, password, rol, activo FROM usuarios
             WHERE nombre_usuario IN (?, ?) OR email IN (?, ?) FOR UPDATE'
        );
        $buscar->execute([$usuario, $email, $usuario, $email]);
        $coincidencias = $buscar->fetchAll(PDO::FETCH_ASSOC);

        if ($coincidencias === []) {
            $insertar = $pdo->prepare(
                "INSERT INTO usuarios (nombre_completo, anio_nacimiento, sexo, pais, ciudad,
                                       email, password, nombre_usuario, foto_perfil, activo, rol)
                 VALUES ('Editor local', 2000, 'Prefiero no cargarlo', 'Argentina', 'Buenos Aires',
                         ?, ?, ?, '', 1, 'editor')"
            );
            $insertar->execute([$email, password_hash($password, PASSWORD_DEFAULT), $usuario]);
        } else {
            $cuenta = $coincidencias[0];
            if (count($coincidencias) !== 1 || $cuenta['rol'] !== 'editor'
                || strcasecmp($cuenta['nombre_usuario'], $usuario) !== 0
                || strcasecmp($cuenta['email'], $email) !== 0) {
                throw new RuntimeException('El usuario o el correo ya pertenecen a otra cuenta. Elegí otros datos para el editor local.');
            }
            if (!password_verify($password, $cuenta['password']) || (int)$cuenta['activo'] !== 1) {
                $actualizar = $pdo->prepare(
                    'UPDATE usuarios SET password = ?, activo = 1, token_activacion = NULL WHERE id = ?'
                );
                $actualizar->execute([password_hash($password, PASSWORD_DEFAULT), $cuenta['id']]);
            }
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    echo "Editor local listo: {$usuario}" . PHP_EOL;
}

function imprimirSqlEditor(string $usuario, string $email, string $password): void
{
    // Codificación hexadecimal: los valores no dependen del modo de escape SQL.
    $usuarioSql = "CONVERT(0x" . bin2hex($usuario) . " USING utf8mb4)";
    $emailSql = "CONVERT(0x" . bin2hex($email) . " USING utf8mb4)";
    $hashSql = "CONVERT(0x" . bin2hex(password_hash($password, PASSWORD_DEFAULT)) . " USING utf8mb4)";

    echo <<<SQL
-- Seleccioná en phpMyAdmin la base del hosting antes de ejecutar.
-- Requiere la columna usuarios.rol. Crea una cuenta nueva una sola vez.
INSERT INTO usuarios (nombre_completo, anio_nacimiento, sexo, pais, ciudad,
                      email, password, nombre_usuario, foto_perfil, activo, rol)
VALUES ('Editor', 2000, 'Prefiero no cargarlo', 'Argentina', 'Buenos Aires',
        $emailSql, $hashSql, $usuarioSql, '', 1, 'editor');

SQL;
}

$args = array_slice($argv, 1);
if ($args !== [] && $args !== ['--sql']) {
    fwrite(STDERR, 'Uso: php scripts/crear-editor-local.php [--sql]' . PHP_EOL);
    exit(1);
}

try {
    prepararEditor($args === ['--sql']);
} catch (PDOException $error) {
    fwrite(STDERR, 'No se pudo preparar el editor. Revisá MySQL, la configuración local y el esquema de usuarios.' . PHP_EOL);
    exit(1);
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
