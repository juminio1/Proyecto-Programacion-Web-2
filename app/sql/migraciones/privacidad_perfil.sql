SET @consulta_perfil_publico = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'usuarios'
          AND column_name = 'perfil_publico'
    ),
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN perfil_publico TINYINT(1) NOT NULL DEFAULT 1'
);

PREPARE agregar_perfil_publico FROM @consulta_perfil_publico;
EXECUTE agregar_perfil_publico;
DEALLOCATE PREPARE agregar_perfil_publico;