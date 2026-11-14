ALTER TABLE usuarios
    ADD COLUMN rol ENUM('jugador', 'editor') NOT NULL DEFAULT 'jugador';
