-- Migración para soporte de reconexión y control de múltiples pestañas conectadas
ALTER TABLE partidas ADD COLUMN desconectada_en DATETIME(6) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS partida_pestanas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partida_id INT NOT NULL,
    pestana_id VARCHAR(64) NOT NULL,
    conectada TINYINT(1) NOT NULL DEFAULT 1,
    ultima_conexion DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    desconectada_en DATETIME(6) DEFAULT NULL,
    es_recarga TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_partida_pestana (partida_id, pestana_id)
);
