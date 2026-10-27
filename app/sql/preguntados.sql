-- Base de datos para el proyecto Preguntados (Programación Web II)
-- HU-01: Registrarse en la aplicación

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
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS partidas ( id INT AUTO_INCREMENT PRIMARY KEY,
                                      usuario_id INT NOT NULL,
                                      estado VARCHAR(20) NOT NULL DEFAULT 'creada',
                                      puntaje INT NOT NULL DEFAULT 0,
                                      fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE categorias (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            nombre VARCHAR(50) NOT NULL UNIQUE,
                            color VARCHAR(7) NOT NULL
);

CREATE TABLE preguntas (
                           id INT AUTO_INCREMENT PRIMARY KEY,
                           enunciado TEXT NOT NULL,
                           categoria_id INT NOT NULL,

                           CONSTRAINT fk_preguntas_categorias
                               FOREIGN KEY (categoria_id)
                                   REFERENCES categorias(id)
);


INSERT INTO categorias (nombre, color) VALUES
                                           ('Historia', '#8B5CF6'),
                                           ('Ciencia', '#16A34A'),
                                           ('Deportes', '#DC2626'),
                                           ('Arte', '#EA580C');


INSERT INTO preguntas (enunciado, categoria_id) VALUES
                                                    ('¿En qué año ocurrió la Revolución de Mayo?', 1),
                                                    ('¿Cuál es el planeta más cercano al Sol?', 2),
                                                    ('¿Cuántos jugadores tiene un equipo de fútbol en cancha?', 3),
                                                    ('¿Quién pintó la Mona Lisa?', 4);
