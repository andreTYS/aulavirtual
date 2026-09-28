-- ============================================================
-- Migración v7 -> v8 (notas ponderadas, calificar sin entrega y foro)
-- Aula Virtual - IESTP Benjamín Franklin
--
-- Ejecutar UNA SOLA VEZ contra una base que ya corrió
-- migration_v6_to_v7.sql.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Categoría y peso (ponderación) de cada tarea.
ALTER TABLE tareas
    ADD COLUMN IF NOT EXISTS categoria ENUM('practica','examen','participacion','trabajo') NOT NULL DEFAULT 'practica' AFTER descripcion,
    ADD COLUMN IF NOT EXISTS peso DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER categoria;

-- Permite calificar a un estudiante sin que exista una entrega digital
-- (ej. examen oral o participación en clase).
ALTER TABLE entregas
    MODIFY COLUMN archivo_path VARCHAR(500) NULL,
    MODIFY COLUMN fecha_entrega TIMESTAMP NULL;

-- Foro del curso: un nivel de respuestas anidadas bajo cada publicación.
ALTER TABLE comentarios
    ADD COLUMN IF NOT EXISTS parent_id INT UNSIGNED NULL AFTER autor_id;

SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'comentarios' AND CONSTRAINT_NAME = 'fk_comentarios_parent'
);
SET @sql_fk = IF(@fk_exists = 0,
    'ALTER TABLE comentarios ADD CONSTRAINT fk_comentarios_parent FOREIGN KEY (parent_id) REFERENCES comentarios(id) ON DELETE CASCADE, ADD KEY idx_comentarios_parent (parent_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación: DESCRIBE tareas; debe incluir `categoria` y `peso`.
-- DESCRIBE entregas; `archivo_path` y `fecha_entrega` deben admitir NULL.
-- DESCRIBE comentarios; debe incluir `parent_id`.
