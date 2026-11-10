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
    habilitada TINYINT(1) NOT NULL DEFAULT 1,
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

SET NAMES utf8mb4;
START TRANSACTION;

INSERT IGNORE INTO categorias (id, nombre, color) VALUES
                                                      (1, 'Historia', '#e0403b'),
                                                      (3, 'Ciencia', '#7c4ee6'),
                                                      (4, 'Deportes', '#2fa866'),
                                                      (6, 'Entretenimiento', '#f0605a');

-- Limpieza de una importación previa de estas mismas preguntas (hace que el script se pueda re-ejecutar)
-- Borra por texto exacto; las opciones se eliminan por ON DELETE CASCADE
DELETE FROM preguntas WHERE pregunta IN ('¿En qué año se declaró la independencia de Argentina?', '¿Quién lideró el Cruce de los Andes?', '¿En qué año llegó Cristóbal Colón a América?', '¿Qué civilización construyó Machu Picchu?', '¿En qué año terminó la Segunda Guerra Mundial?', '¿En qué año ocurrió la Revolución de Mayo en Argentina?', '¿Quién creó la bandera argentina?', '¿Qué antigua civilización construyó las pirámides de Giza?', '¿En qué año cayó el Muro de Berlín?', '¿En qué año comenzó la Primera Guerra Mundial?', '¿De qué país fue emperador Napoleón Bonaparte?', '¿Qué país fue gobernado antiguamente por los zares?', '¿Quién fue el primer hombre en pisar la Luna?', '¿Qué imperio tenía como capital a la ciudad de Roma?', '¿En qué año comenzó la última dictadura militar en Argentina?', '¿En qué año Argentina recuperó la democracia?', '¿Cómo se llamó la guerra que enfrentó a Argentina y Reino Unido en 1982?', '¿Quién fue conocido como "El Libertador" de Venezuela, Colombia y otros países sudamericanos?', '¿Qué antigua civilización creó los Juegos Olímpicos?', '¿Qué gran acontecimiento comenzó en Francia en 1789?', '¿Cuántos jugadores de un equipo hay en la cancha en un partido de fútbol?', '¿Qué selección ganó el Mundial de Qatar 2022?', '¿En qué deporte se juega el torneo de Wimbledon?', '¿Cada cuántos años se celebran los Juegos Olímpicos de verano?', '¿Cuántos anillos tiene el símbolo olímpico?', '¿Cuántos puntos vale un tiro de tres en básquet?', '¿En qué deporte se utiliza un bate y un guante?', '¿Cuántos jugadores de un equipo hay en la cancha en básquet?', '¿Qué país ganó el primer Mundial de fútbol, en 1930?', '¿Qué jugador argentino de fútbol es apodado "La Pulga"?', '¿Cuántos minutos dura un partido de fútbol sin tiempo agregado?', '¿En qué deporte se utiliza un palo llamado "putter"?', '¿En qué deporte se destacó el velocista Usain Bolt?', '¿Cómo se llama la selección argentina de rugby?', '¿Cuántos jugadores de un equipo hay en la cancha en vóley?', '¿Cómo se conoce a la selección argentina femenina de hockey sobre césped?', '¿De qué país es originario el judo?', '¿Qué piloto argentino ganó cinco campeonatos de Fórmula 1?', '¿Cuántos kilómetros tiene aproximadamente una maratón?', '¿Qué país fue sede del Mundial de fútbol de 2014?', '¿Cuál es el planeta más grande del sistema solar?', '¿Cuál es la fórmula química del agua?', '¿Qué gas necesitamos respirar para vivir?', '¿Qué planeta es conocido como el planeta rojo?', '¿Cuántos huesos tiene el cuerpo humano adulto?', '¿Qué planeta es famoso por sus anillos visibles?', '¿Qué órgano del cuerpo bombea la sangre?', '¿Cuál es el animal más grande del mundo?', '¿Qué estrella se encuentra en el centro de nuestro sistema solar?', '¿Cuál es el símbolo químico del oro?', '¿Cuántos planetas tiene el sistema solar?', '¿Cómo se llama el proceso por el cual las plantas producen su alimento usando la luz del sol?', '¿Cuál es el satélite natural de la Tierra?', '¿Qué científico formuló la teoría de la relatividad?', '¿A cuántos grados Celsius hierve el agua al nivel del mar?', '¿Qué metal es líquido a temperatura ambiente?', '¿A qué científico se le atribuye la ley de la gravitación universal?', '¿Cuál es el hueso más largo del cuerpo humano?', '¿Qué vitamina produce nuestro cuerpo al recibir la luz del sol?', '¿Cuál es el planeta más cercano al Sol?', '¿Quién interpreta a Jack Sparrow en la saga Piratas del Caribe?', '¿Cómo se llama el joven mago protagonista de los libros de J.K. Rowling?', '¿Qué serie tiene como protagonista a Walter White?', '¿Cómo se llama el fontanero de Nintendo con gorra roja?', '¿Qué banda es autora de "Bohemian Rhapsody"?', '¿En qué película de Disney aparece el personaje Simba?', '¿Cómo se llama el reino donde viven Elsa y Anna en la película Frozen?', '¿Qué videojuego se basa en construir y explorar un mundo hecho de bloques?', '¿Quién dirigió la película Titanic?', '¿Qué cantante colombiana es conocida por el tema "Hips Don''t Lie"?', '¿Cómo se llama el robot protagonista de la película de Pixar estrenada en 2008?', '¿Quién es el padre de Luke Skywalker en Star Wars?', '¿Qué serie animada transcurre en la ciudad de Springfield?', '¿Cómo se llama el protagonista de la saga de videojuegos "The Legend of Zelda"?', '¿Qué actor interpreta a Iron Man en el Universo Cinematográfico de Marvel?', '¿Qué plataforma de streaming produjo la serie "Stranger Things"?', '¿Quién canta el tema "Despacito"?', '¿Qué banda argentina de rock es autora de "De música ligera"?', '¿Cómo se llama el erizo azul mascota de SEGA?', '¿Cómo se llama el ogro verde protagonista de la película de DreamWorks?');

-- 1: Historia (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿En qué año se declaró la independencia de Argentina?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '1853', 0), (@pid, 'B', '1810', 0), (@pid, 'C', '1825', 0), (@pid, 'D', '1816', 1);
-- 2: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿Quién lideró el Cruce de los Andes?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Domingo F. Sarmiento', 0), (@pid, 'B', 'Juan Manuel de Rosas', 0), (@pid, 'C', 'José de San Martín', 1), (@pid, 'D', 'Manuel Belgrano', 0);
-- 3: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿En qué año llegó Cristóbal Colón a América?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '1453', 0), (@pid, 'B', '1588', 0), (@pid, 'C', '1519', 0), (@pid, 'D', '1492', 1);
-- 4: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿Qué civilización construyó Machu Picchu?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Los mayas', 0), (@pid, 'B', 'Los aztecas', 0), (@pid, 'C', 'Los incas', 1), (@pid, 'D', 'Los romanos', 0);
-- 5: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿En qué año terminó la Segunda Guerra Mundial?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '1939', 0), (@pid, 'B', '1918', 0), (@pid, 'C', '1950', 0), (@pid, 'D', '1945', 1);
-- 6: Historia (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿En qué año ocurrió la Revolución de Mayo en Argentina?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '1816', 0), (@pid, 'B', '1820', 0), (@pid, 'C', '1806', 0), (@pid, 'D', '1810', 1);
-- 7: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿Quién creó la bandera argentina?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Bernardino Rivadavia', 0), (@pid, 'B', 'José de San Martín', 0), (@pid, 'C', 'Manuel Belgrano', 1), (@pid, 'D', 'Mariano Moreno', 0);
-- 8: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿Qué antigua civilización construyó las pirámides de Giza?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Los griegos', 0), (@pid, 'B', 'Los mayas', 0), (@pid, 'C', 'Los egipcios', 1), (@pid, 'D', 'Los persas', 0);
-- 9: Historia (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿En qué año cayó el Muro de Berlín?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '1989', 1), (@pid, 'B', '1991', 0), (@pid, 'C', '1975', 0), (@pid, 'D', '1961', 0);
-- 10: Historia (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿En qué año comenzó la Primera Guerra Mundial?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '1918', 0), (@pid, 'B', '1939', 0), (@pid, 'C', '1914', 1), (@pid, 'D', '1905', 0);
-- 11: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿De qué país fue emperador Napoleón Bonaparte?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Italia', 0), (@pid, 'B', 'España', 0), (@pid, 'C', 'Alemania', 0), (@pid, 'D', 'Francia', 1);
-- 12: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿Qué país fue gobernado antiguamente por los zares?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Inglaterra', 0), (@pid, 'B', 'Egipto', 0), (@pid, 'C', 'China', 0), (@pid, 'D', 'Rusia', 1);
-- 13: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿Quién fue el primer hombre en pisar la Luna?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'John Glenn', 0), (@pid, 'B', 'Buzz Aldrin', 0), (@pid, 'C', 'Neil Armstrong', 1), (@pid, 'D', 'Yuri Gagarin', 0);
-- 14: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿Qué imperio tenía como capital a la ciudad de Roma?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'El Imperio persa', 0), (@pid, 'B', 'El Imperio inca', 0), (@pid, 'C', 'El Imperio otomano', 0), (@pid, 'D', 'El Imperio romano', 1);
-- 15: Historia (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿En qué año comenzó la última dictadura militar en Argentina?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '1976', 1), (@pid, 'B', '1955', 0), (@pid, 'C', '1983', 0), (@pid, 'D', '1982', 0);
-- 16: Historia (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿En qué año Argentina recuperó la democracia?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '1973', 0), (@pid, 'B', '1983', 1), (@pid, 'C', '1989', 0), (@pid, 'D', '1976', 0);
-- 17: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿Cómo se llamó la guerra que enfrentó a Argentina y Reino Unido en 1982?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Guerra del Paraguay', 0), (@pid, 'B', 'Guerra del Chaco', 0), (@pid, 'C', 'Guerra del Pacífico', 0), (@pid, 'D', 'Guerra de Malvinas', 1);
-- 18: Historia (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿Quién fue conocido como "El Libertador" de Venezuela, Colombia y otros países sudamericanos?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Bernardo O''Higgins', 0), (@pid, 'B', 'Francisco de Miranda', 0), (@pid, 'C', 'Simón Bolívar', 1), (@pid, 'D', 'Antonio José de Sucre', 0);
-- 19: Historia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿Qué antigua civilización creó los Juegos Olímpicos?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Los vikingos', 0), (@pid, 'B', 'Los egipcios', 0), (@pid, 'C', 'Los romanos', 0), (@pid, 'D', 'Los griegos', 1);
-- 20: Historia (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Historia' LIMIT 1), '¿Qué gran acontecimiento comenzó en Francia en 1789?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'La Revolución Francesa', 1), (@pid, 'B', 'La Revolución Industrial', 0), (@pid, 'C', 'La Revolución Rusa', 0), (@pid, 'D', 'La Guerra de los Cien Años', 0);
-- 21: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Cuántos jugadores de un equipo hay en la cancha en un partido de fútbol?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '11', 1), (@pid, 'B', '10', 0), (@pid, 'C', '12', 0), (@pid, 'D', '9', 0);
-- 22: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Qué selección ganó el Mundial de Qatar 2022?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Argentina', 1), (@pid, 'B', 'Croacia', 0), (@pid, 'C', 'Brasil', 0), (@pid, 'D', 'Francia', 0);
-- 23: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿En qué deporte se juega el torneo de Wimbledon?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Golf', 0), (@pid, 'B', 'Béisbol', 0), (@pid, 'C', 'Rugby', 0), (@pid, 'D', 'Tenis', 1);
-- 24: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Cada cuántos años se celebran los Juegos Olímpicos de verano?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '3 años', 0), (@pid, 'B', '5 años', 0), (@pid, 'C', '4 años', 1), (@pid, 'D', '2 años', 0);
-- 25: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Cuántos anillos tiene el símbolo olímpico?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '6', 0), (@pid, 'B', '5', 1), (@pid, 'C', '4', 0), (@pid, 'D', '7', 0);
-- 26: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Cuántos puntos vale un tiro de tres en básquet?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '2', 0), (@pid, 'B', '1', 0), (@pid, 'C', '3', 1), (@pid, 'D', '4', 0);
-- 27: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿En qué deporte se utiliza un bate y un guante?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Tenis', 0), (@pid, 'B', 'Críquet de salón', 0), (@pid, 'C', 'Hockey', 0), (@pid, 'D', 'Béisbol', 1);
-- 28: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Cuántos jugadores de un equipo hay en la cancha en básquet?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '6', 0), (@pid, 'B', '4', 0), (@pid, 'C', '5', 1), (@pid, 'D', '7', 0);
-- 29: Deportes (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Qué país ganó el primer Mundial de fútbol, en 1930?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Italia', 0), (@pid, 'B', 'Argentina', 0), (@pid, 'C', 'Uruguay', 1), (@pid, 'D', 'Brasil', 0);
-- 30: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Qué jugador argentino de fútbol es apodado "La Pulga"?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Lionel Messi', 1), (@pid, 'B', 'Ángel Di María', 0), (@pid, 'C', 'Sergio Agüero', 0), (@pid, 'D', 'Paulo Dybala', 0);
-- 31: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Cuántos minutos dura un partido de fútbol sin tiempo agregado?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '80 minutos', 0), (@pid, 'B', '90 minutos', 1), (@pid, 'C', '100 minutos', 0), (@pid, 'D', '60 minutos', 0);
-- 32: Deportes (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿En qué deporte se utiliza un palo llamado "putter"?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Golf', 1), (@pid, 'B', 'Billar', 0), (@pid, 'C', 'Polo', 0), (@pid, 'D', 'Hockey', 0);
-- 33: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿En qué deporte se destacó el velocista Usain Bolt?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Atletismo', 1), (@pid, 'B', 'Natación', 0), (@pid, 'C', 'Ciclismo', 0), (@pid, 'D', 'Boxeo', 0);
-- 34: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Cómo se llama la selección argentina de rugby?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Los Jaguares', 0), (@pid, 'B', 'Los Cóndores', 0), (@pid, 'C', 'Los Pumas', 1), (@pid, 'D', 'Los Leones', 0);
-- 35: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Cuántos jugadores de un equipo hay en la cancha en vóley?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '5', 0), (@pid, 'B', '7', 0), (@pid, 'C', '6', 1), (@pid, 'D', '8', 0);
-- 36: Deportes (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Cómo se conoce a la selección argentina femenina de hockey sobre césped?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Las Panteras', 0), (@pid, 'B', 'Las Pumas', 0), (@pid, 'C', 'Las Águilas', 0), (@pid, 'D', 'Las Leonas', 1);
-- 37: Deportes (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿De qué país es originario el judo?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'China', 0), (@pid, 'B', 'Japón', 1), (@pid, 'C', 'Corea del Sur', 0), (@pid, 'D', 'Tailandia', 0);
-- 38: Deportes (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Qué piloto argentino ganó cinco campeonatos de Fórmula 1?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Juan Manuel Fangio', 1), (@pid, 'B', 'Gabriel Bonilla', 0), (@pid, 'C', 'Carlos Reutemann', 0), (@pid, 'D', 'José Froilán González', 0);
-- 39: Deportes (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Cuántos kilómetros tiene aproximadamente una maratón?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '21 km', 0), (@pid, 'B', '42 km', 1), (@pid, 'C', '50 km', 0), (@pid, 'D', '30 km', 0);
-- 40: Deportes (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Deportes' LIMIT 1), '¿Qué país fue sede del Mundial de fútbol de 2014?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Alemania', 0), (@pid, 'B', 'Sudáfrica', 0), (@pid, 'C', 'Rusia', 0), (@pid, 'D', 'Brasil', 1);
-- 41: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Cuál es el planeta más grande del sistema solar?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Neptuno', 0), (@pid, 'B', 'Saturno', 0), (@pid, 'C', 'Júpiter', 1), (@pid, 'D', 'Tierra', 0);
-- 42: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Cuál es la fórmula química del agua?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'O2', 0), (@pid, 'B', 'CO2', 0), (@pid, 'C', 'H2O', 1), (@pid, 'D', 'NaCl', 0);
-- 43: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Qué gas necesitamos respirar para vivir?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Helio', 0), (@pid, 'B', 'Nitrógeno', 0), (@pid, 'C', 'Hidrógeno', 0), (@pid, 'D', 'Oxígeno', 1);
-- 44: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Qué planeta es conocido como el planeta rojo?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Urano', 0), (@pid, 'B', 'Marte', 1), (@pid, 'C', 'Mercurio', 0), (@pid, 'D', 'Venus', 0);
-- 45: Ciencia (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Cuántos huesos tiene el cuerpo humano adulto?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '150', 0), (@pid, 'B', '206', 1), (@pid, 'C', '300', 0), (@pid, 'D', '250', 0);
-- 46: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Qué planeta es famoso por sus anillos visibles?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Venus', 0), (@pid, 'B', 'Saturno', 1), (@pid, 'C', 'Mercurio', 0), (@pid, 'D', 'Marte', 0);
-- 47: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Qué órgano del cuerpo bombea la sangre?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'El riñón', 0), (@pid, 'B', 'Los pulmones', 0), (@pid, 'C', 'El hígado', 0), (@pid, 'D', 'El corazón', 1);
-- 48: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Cuál es el animal más grande del mundo?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'El elefante africano', 0), (@pid, 'B', 'El tiburón ballena', 0), (@pid, 'C', 'La ballena azul', 1), (@pid, 'D', 'La jirafa', 0);
-- 49: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Qué estrella se encuentra en el centro de nuestro sistema solar?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Próxima Centauri', 0), (@pid, 'B', 'El Sol', 1), (@pid, 'C', 'Sirio', 0), (@pid, 'D', 'La Estrella Polar', 0);
-- 50: Ciencia (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Cuál es el símbolo químico del oro?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Ag', 0), (@pid, 'B', 'Go', 0), (@pid, 'C', 'Or', 0), (@pid, 'D', 'Au', 1);
-- 51: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Cuántos planetas tiene el sistema solar?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '10', 0), (@pid, 'B', '7', 0), (@pid, 'C', '9', 0), (@pid, 'D', '8', 1);
-- 52: Ciencia (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Cómo se llama el proceso por el cual las plantas producen su alimento usando la luz del sol?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Respiración', 0), (@pid, 'B', 'Fermentación', 0), (@pid, 'C', 'Digestión', 0), (@pid, 'D', 'Fotosíntesis', 1);
-- 53: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Cuál es el satélite natural de la Tierra?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'La Luna', 1), (@pid, 'B', 'Europa', 0), (@pid, 'C', 'Titán', 0), (@pid, 'D', 'Fobos', 0);
-- 54: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Qué científico formuló la teoría de la relatividad?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Albert Einstein', 1), (@pid, 'B', 'Galileo Galilei', 0), (@pid, 'C', 'Stephen Hawking', 0), (@pid, 'D', 'Isaac Newton', 0);
-- 55: Ciencia (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿A cuántos grados Celsius hierve el agua al nivel del mar?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', '120 °C', 0), (@pid, 'B', '100 °C', 1), (@pid, 'C', '90 °C', 0), (@pid, 'D', '50 °C', 0);
-- 56: Ciencia (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Qué metal es líquido a temperatura ambiente?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Mercurio', 1), (@pid, 'B', 'Aluminio', 0), (@pid, 'C', 'Hierro', 0), (@pid, 'D', 'Plomo', 0);
-- 57: Ciencia (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿A qué científico se le atribuye la ley de la gravitación universal?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Isaac Newton', 1), (@pid, 'B', 'Albert Einstein', 0), (@pid, 'C', 'Charles Darwin', 0), (@pid, 'D', 'Nikola Tesla', 0);
-- 58: Ciencia (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Cuál es el hueso más largo del cuerpo humano?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'El húmero', 0), (@pid, 'B', 'La tibia', 0), (@pid, 'C', 'El peroné', 0), (@pid, 'D', 'El fémur', 1);
-- 59: Ciencia (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Qué vitamina produce nuestro cuerpo al recibir la luz del sol?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Vitamina A', 0), (@pid, 'B', 'Vitamina B12', 0), (@pid, 'C', 'Vitamina C', 0), (@pid, 'D', 'Vitamina D', 1);
-- 60: Ciencia (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Ciencia' LIMIT 1), '¿Cuál es el planeta más cercano al Sol?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Marte', 0), (@pid, 'B', 'Mercurio', 1), (@pid, 'C', 'Tierra', 0), (@pid, 'D', 'Venus', 0);
-- 61: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Quién interpreta a Jack Sparrow en la saga Piratas del Caribe?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Orlando Bloom', 0), (@pid, 'B', 'Brad Pitt', 0), (@pid, 'C', 'Tom Cruise', 0), (@pid, 'D', 'Johnny Depp', 1);
-- 62: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Cómo se llama el joven mago protagonista de los libros de J.K. Rowling?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Gandalf', 0), (@pid, 'B', 'Merlín', 0), (@pid, 'C', 'Harry Potter', 1), (@pid, 'D', 'Percy Jackson', 0);
-- 63: Entretenimiento (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Qué serie tiene como protagonista a Walter White?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Dexter', 0), (@pid, 'B', 'Lost', 0), (@pid, 'C', 'Breaking Bad', 1), (@pid, 'D', 'Narcos', 0);
-- 64: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Cómo se llama el fontanero de Nintendo con gorra roja?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Mario', 1), (@pid, 'B', 'Luigi', 0), (@pid, 'C', 'Link', 0), (@pid, 'D', 'Sonic', 0);
-- 65: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Qué banda es autora de "Bohemian Rhapsody"?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Pink Floyd', 0), (@pid, 'B', 'Queen', 1), (@pid, 'C', 'Led Zeppelin', 0), (@pid, 'D', 'The Beatles', 0);
-- 66: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿En qué película de Disney aparece el personaje Simba?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Tarzán', 0), (@pid, 'B', 'Bambi', 0), (@pid, 'C', 'El Rey León', 1), (@pid, 'D', 'Aladdín', 0);
-- 67: Entretenimiento (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Cómo se llama el reino donde viven Elsa y Anna en la película Frozen?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Narnia', 0), (@pid, 'B', 'Corona', 0), (@pid, 'C', 'Arendelle', 1), (@pid, 'D', 'Agrabah', 0);
-- 68: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Qué videojuego se basa en construir y explorar un mundo hecho de bloques?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Pac-Man', 0), (@pid, 'B', 'Fortnite', 0), (@pid, 'C', 'Tetris', 0), (@pid, 'D', 'Minecraft', 1);
-- 69: Entretenimiento (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Quién dirigió la película Titanic?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'James Cameron', 1), (@pid, 'B', 'Quentin Tarantino', 0), (@pid, 'C', 'Steven Spielberg', 0), (@pid, 'D', 'Christopher Nolan', 0);
-- 70: Entretenimiento (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Qué cantante colombiana es conocida por el tema "Hips Don''t Lie"?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Karol G', 0), (@pid, 'B', 'Shakira', 1), (@pid, 'C', 'Thalía', 0), (@pid, 'D', 'Becky G', 0);
-- 71: Entretenimiento (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Cómo se llama el robot protagonista de la película de Pixar estrenada en 2008?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Baymax', 0), (@pid, 'B', 'R2-D2', 0), (@pid, 'C', 'C-3PO', 0), (@pid, 'D', 'WALL-E', 1);
-- 72: Entretenimiento (media)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Quién es el padre de Luke Skywalker en Star Wars?', 1, 'media');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Obi-Wan Kenobi', 0), (@pid, 'B', 'Yoda', 0), (@pid, 'C', 'Darth Vader', 1), (@pid, 'D', 'Han Solo', 0);
-- 73: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Qué serie animada transcurre en la ciudad de Springfield?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'South Park', 0), (@pid, 'B', 'Futurama', 0), (@pid, 'C', 'Los Simpson', 1), (@pid, 'D', 'Padre de familia', 0);
-- 74: Entretenimiento (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Cómo se llama el protagonista de la saga de videojuegos "The Legend of Zelda"?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Ganon', 0), (@pid, 'B', 'Link', 1), (@pid, 'C', 'Mario', 0), (@pid, 'D', 'Zelda', 0);
-- 75: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Qué actor interpreta a Iron Man en el Universo Cinematográfico de Marvel?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Mark Ruffalo', 0), (@pid, 'B', 'Robert Downey Jr.', 1), (@pid, 'C', 'Chris Evans', 0), (@pid, 'D', 'Chris Hemsworth', 0);
-- 76: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Qué plataforma de streaming produjo la serie "Stranger Things"?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'HBO Max', 0), (@pid, 'B', 'Amazon Prime Video', 0), (@pid, 'C', 'Disney+', 0), (@pid, 'D', 'Netflix', 1);
-- 77: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Quién canta el tema "Despacito"?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Maluma', 0), (@pid, 'B', 'Bad Bunny', 0), (@pid, 'C', 'J Balvin', 0), (@pid, 'D', 'Luis Fonsi', 1);
-- 78: Entretenimiento (dificil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Qué banda argentina de rock es autora de "De música ligera"?', 1, 'dificil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Los Redondos', 0), (@pid, 'B', 'Divididos', 0), (@pid, 'C', 'Los Fabulosos Cadillacs', 0), (@pid, 'D', 'Soda Stereo', 1);
-- 79: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Cómo se llama el erizo azul mascota de SEGA?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Sonic', 1), (@pid, 'B', 'Knuckles', 0), (@pid, 'C', 'Tails', 0), (@pid, 'D', 'Shadow', 0);
-- 80: Entretenimiento (facil)
INSERT INTO preguntas (categoria_id, pregunta, habilitada, dificultad) VALUES ((SELECT id FROM categorias WHERE nombre='Entretenimiento' LIMIT 1), '¿Cómo se llama el ogro verde protagonista de la película de DreamWorks?', 1, 'facil');
SET @pid = LAST_INSERT_ID();
INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (@pid, 'A', 'Gru', 0), (@pid, 'B', 'Shrek', 1), (@pid, 'C', 'Po', 0), (@pid, 'D', 'Burro', 0);

COMMIT;

ALTER TABLE partidas ADD COLUMN motivo_fin VARCHAR(32) DEFAULT NULL;
ALTER TABLE partida_preguntas ADD COLUMN vence_en DATETIME DEFAULT NULL;
ALTER TABLE usuarios  ADD COLUMN nivel ENUM('bajo','medio','alto') NOT NULL DEFAULT 'medio';
ALTER TABLE preguntas ADD COLUMN dificultad ENUM('facil','media','dificil') NOT NULL DEFAULT 'media';
ALTER TABLE partidas  ADD COLUMN nivel_inicial ENUM('bajo','medio','alto') NOT NULL DEFAULT 'medio';

CREATE TABLE IF NOT EXISTS reportes_preguntas (
                                                  id INT AUTO_INCREMENT PRIMARY KEY,
                                                  pregunta_id INT NOT NULL,
                                                  usuario_id INT NOT NULL,
                                                  partida_id INT DEFAULT NULL,
                                                  motivo VARCHAR(255) DEFAULT NULL,
                                                  estado ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
                                                  fecha_reporte DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                  fecha_revision DATETIME DEFAULT NULL,
                                                  editor_id INT DEFAULT NULL,
                                                  FOREIGN KEY (pregunta_id) REFERENCES preguntas(id) ON DELETE CASCADE,
                                                  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                                                  FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE SET NULL,
                                                  FOREIGN KEY (editor_id) REFERENCES usuarios(id) ON DELETE SET NULL,
                                                  UNIQUE KEY uq_reporte_usuario_pregunta (pregunta_id, usuario_id)
);

CREATE TABLE IF NOT EXISTS preguntasSugeridas (
                                                  id INT AUTO_INCREMENT PRIMARY KEY,
                                                  usuario_id INT NOT NULL,
                                                  categoria_id INT NOT NULL,
                                                  pregunta TEXT NOT NULL,
                                                  opcion_a VARCHAR(255) NOT NULL,
                                                  opcion_b VARCHAR(255) NOT NULL,
                                                  opcion_c VARCHAR(255) NOT NULL,
                                                  opcion_d VARCHAR(255) NOT NULL,
                                                  respuesta_correcta CHAR(1) NOT NULL,
                                                  estado ENUM('pendiente', 'aceptada', 'rechazada')
                                                      NOT NULL DEFAULT 'pendiente',
                                                  fecha_sugerencia DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                                                  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                                                  FOREIGN KEY (categoria_id) REFERENCES categorias(id)
);