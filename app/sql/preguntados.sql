-- Base de datos para el proyecto Preguntados (Programación Web II)
-- HU-01: Registrarse en la aplicación
-- HU: Sumar puntos por respuestas correctas

CREATE DATABASE IF NOT EXISTS preguntados CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE preguntados;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(150) NOT NULL,
    anio_nacimiento INT NOT NULL,
    sexo ENUM('Masculino', 'Femenino', 'Prefiero no cargarlo') NOT NULL,
    pais VARCHAR(100) NOT NULL,
    ciudad VARCHAR(100) NOT NULL,
    latitud DECIMAL(10, 8) DEFAULT NULL,
    longitud DECIMAL(11, 8) DEFAULT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre_usuario VARCHAR(50) NOT NULL UNIQUE,
    foto_perfil VARCHAR(255) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    token_activacion VARCHAR(64) DEFAULT NULL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    puntaje_acumulado INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'creada',
    puntaje INT NOT NULL DEFAULT 0,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    pregunta TEXT NOT NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS opciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta_id INT NOT NULL,
    letra CHAR(1) NOT NULL,
    texto VARCHAR(255) NOT NULL,
    es_correcta TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (pregunta_id) REFERENCES preguntas(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS partida_preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partida_id INT NOT NULL,
    pregunta_id INT NOT NULL,
    numero_pregunta INT NOT NULL DEFAULT 1,
    opcion_id INT DEFAULT NULL,
    es_correcta TINYINT(1) DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_respuesta DATETIME DEFAULT NULL,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    FOREIGN KEY (pregunta_id) REFERENCES preguntas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_partida_pregunta (partida_id, pregunta_id)
);

-- Inserción de categorías iniciales
INSERT IGNORE INTO categorias (id, nombre, color) VALUES
                                                      (1, 'Historia', '#f4a100'),
                                                      (2, 'Geografía', '#2c91e8'),
                                                      (3, 'Ciencia', '#40b36c'),
                                                      (4, 'Deportes', '#ff7a00');

-- Inserción de preguntas iniciales
INSERT IGNORE INTO preguntas (id, categoria_id, pregunta) VALUES
(1, 1, '¿En qué año llegó el ser humano a la Luna?'),
(3, 3, '¿Cuál es el elemento químico con símbolo "O"?'),
(4, 4, '¿Quién pintó la Mona Lisa?'),
(5, 5, '¿Cada cuántos años se celebran los Juegos Olímpicos?'),
(7, 1, '¿En qué año comenzó la Segunda Guerra Mundial?'),
(9, 3, '¿Qué planeta del sistema solar es conocido como el Planeta Rojo?'),
(10, 5, '¿Qué selección ganó la Copa del Mundo de fútbol en Qatar 2022?');

-- Inserción de opciones para cada pregunta
INSERT IGNORE INTO opciones (id, pregunta_id, letra, texto, es_correcta) VALUES
-- Pregunta 1
(1, 1, 'A', '1965', 0),
(2, 1, 'B', '1969', 1),
(3, 1, 'C', '1971', 0),
(4, 1, 'D', '1959', 0),
-- Pregunta 3
(9, 3, 'A', 'Oro', 0),
(10, 3, 'B', 'Osmio', 0),
(11, 3, 'C', 'Oxígeno', 1),
(12, 3, 'D', 'Plata', 0),
-- Pregunta 4
(13, 4, 'A', 'Leonardo da Vinci', 1),
(14, 4, 'B', 'Vincent van Gogh', 0),
(15, 4, 'C', 'Pablo Picasso', 0),
(16, 4, 'D', 'Miguel Ángel', 0),
-- Pregunta 5
(17, 5, 'A', '2 años', 0),
(18, 5, 'B', '3 años', 0),
(19, 5, 'C', '4 años', 1),
(20, 5, 'D', '5 años', 0),
-- Pregunta 6
(21, 6, 'A', 'Zelda', 0),
(22, 6, 'B', 'Link', 1),
(23, 6, 'C', 'Ganon', 0),
(24, 6, 'D', 'Mario', 0),
-- Pregunta 7
(25, 7, 'A', '1914', 0),
(26, 7, 'B', '1939', 1),
(27, 7, 'C', '1945', 0),
(28, 7, 'D', '1929', 0),
-- Pregunta 8
(29, 8, 'A', 'Berlín', 0),
(30, 8, 'B', 'Roma', 0),
(31, 8, 'C', 'Madrid', 0),
(32, 8, 'D', 'París', 1),
-- Pregunta 9
(33, 9, 'A', 'Venus', 0),
(34, 9, 'B', 'Júpiter', 0),
(35, 9, 'C', 'Marte', 1),
(36, 9, 'D', 'Saturno', 0),
-- Pregunta 10
(37, 10, 'A', 'Francia', 0),
(38, 10, 'B', 'Argentina', 1),
(39, 10, 'C', 'Brasil', 0),
(40, 10, 'D', 'Croacia', 0);
