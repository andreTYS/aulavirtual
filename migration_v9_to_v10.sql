-- ============================================================
-- Migración v9 -> v10 (unidades, sílabo y notificaciones por correo)
-- Aula Virtual - IESTP Benjamín Franklin
--
-- Ejecutar UNA SOLA VEZ contra una base que ya corrió
-- migration_v8_to_v9.sql.
--
-- Agrega:
--  - cursos.silabo_path / cursos.competencias (sílabo por curso)
--  - tabla `unidades` (Unidad I/II/III... con peso propio)
--  - tareas.unidad_id / examenes.unidad_id
--
-- Migra los datos existentes sin alterar ningún promedio ya
-- calculado: crea una "Unidad única" (peso 1.00) por cada curso que
-- ya tenga tareas o exámenes, y les asigna esa unidad. Con una sola
-- unidad de peso 1, el promedio final del curso sigue dando
-- exactamente el mismo resultado que antes de esta migración.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE cursos
    ADD COLUMN IF NOT EXISTS silabo_path VARCHAR(500) NULL,
    ADD COLUMN IF NOT EXISTS competencias TEXT NULL;

CREATE TABLE IF NOT EXISTS unidades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    peso DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_unidades_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    KEY idx_unidades_curso (curso_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tareas
    ADD COLUMN IF NOT EXISTS unidad_id INT UNSIGNED NULL AFTER sesion_id,
    ADD KEY IF NOT EXISTS idx_tareas_unidad (unidad_id);

ALTER TABLE examenes
    ADD COLUMN IF NOT EXISTS unidad_id INT UNSIGNED NULL AFTER curso_id,
    ADD KEY IF NOT EXISTS idx_examenes_unidad (unidad_id);

-- Agrega las FK solo si todavía no existen (reejecución segura).
SET @fk_tareas_unidad = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tareas' AND CONSTRAINT_NAME = 'fk_tareas_unidad'
);
SET @sql_fk1 = IF(@fk_tareas_unidad = 0,
    'ALTER TABLE tareas ADD CONSTRAINT fk_tareas_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql_fk1; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_examenes_unidad = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'examenes' AND CONSTRAINT_NAME = 'fk_examenes_unidad'
);
SET @sql_fk2 = IF(@fk_examenes_unidad = 0,
    'ALTER TABLE examenes ADD CONSTRAINT fk_examenes_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql_fk2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Crea una "Unidad única" por cada curso que ya tenga tareas o
-- exámenes, para no dejarlos huérfanos de unidad.
INSERT INTO unidades (curso_id, nombre, orden, peso)
SELECT DISTINCT curso_id, 'Unidad única', 1, 1.00
FROM (
    SELECT curso_id FROM tareas
    UNION
    SELECT curso_id FROM examenes
) AS cursos_con_evaluaciones
WHERE curso_id NOT IN (SELECT curso_id FROM unidades);

-- Asigna esa unidad a las tareas y exámenes que todavía no tienen una.
UPDATE tareas t
JOIN unidades u ON u.curso_id = t.curso_id
SET t.unidad_id = u.id
WHERE t.unidad_id IS NULL;

UPDATE examenes e
JOIN unidades u ON u.curso_id = e.curso_id
SET e.unidad_id = u.id
WHERE e.unidad_id IS NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación: DESCRIBE cursos; debe incluir silabo_path y competencias.
-- SHOW TABLES; debe incluir 'unidades'. SELECT COUNT(*) FROM tareas WHERE
-- unidad_id IS NULL; debe dar 0 (igual para examenes).
