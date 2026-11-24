SET @consulta_fecha_creacion = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'preguntas'
          AND column_name = 'fecha_creacion'
    ),
    'SELECT 1',
    'ALTER TABLE preguntas ADD COLUMN fecha_creacion DATETIME NULL DEFAULT NULL'
);

PREPARE agregar_fecha_creacion FROM @consulta_fecha_creacion;
EXECUTE agregar_fecha_creacion;
DEALLOCATE PREPARE agregar_fecha_creacion;

ALTER TABLE preguntas
    MODIFY COLUMN fecha_creacion DATETIME NULL DEFAULT CURRENT_TIMESTAMP;