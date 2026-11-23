-- Agrega el rol "admin". Requiere haber ejecutado antes editor.sql (columna usuarios.rol).
ALTER TABLE usuarios
    MODIFY COLUMN rol ENUM('jugador', 'editor', 'admin') NOT NULL DEFAULT 'jugador';
