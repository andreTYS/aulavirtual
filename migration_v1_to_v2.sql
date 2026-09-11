-- ============================================================
-- Migración v1 -> v2 (PRD Entrega 1: periodos, sesiones, asistencia)
-- Aula Virtual - IESTP Benjamín Franklin
--
-- Ejecutar UNA SOLA VEZ contra la base de datos de PRODUCCIÓN que ya
-- tiene datos reales (la que se creó con el db.sql original / v1).
-- NO reemplaza a db.sql: es un parche incremental que agrega tablas y
-- columnas nuevas sin borrar nada de lo existente.
--
-- IMPORTANTE: haz un respaldo antes de correr esto (ver comandos VPS).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. carreras: duración en ciclos
ALTER TABLE carreras
    ADD COLUMN IF NOT EXISTS duracion_ciclos TINYINT UNSIGNED NOT NULL DEFAULT 6;

-- 2. periodos_academicos (tabla nueva)
CREATE TABLE IF NOT EXISTS periodos_academicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_periodo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. usuarios: DNI/teléfono/carrera/apoderado (estudiante) y especialidad (docente)
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS dni VARCHAR(15) NULL AFTER activo,
    ADD COLUMN IF NOT EXISTS telefono VARCHAR(20) NULL AFTER dni,
    ADD COLUMN IF NOT EXISTS carrera_id INT UNSIGNED NULL AFTER telefono,
    ADD COLUMN IF NOT EXISTS especialidad VARCHAR(150) NULL AFTER carrera_id,
    ADD COLUMN IF NOT EXISTS apoderado_nombre VARCHAR(150) NULL AFTER especialidad,
    ADD COLUMN IF NOT EXISTS apoderado_telefono VARCHAR(20) NULL AFTER apoderado_nombre;

ALTER TABLE usuarios ADD UNIQUE KEY uk_usuarios_dni (dni);
ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_carrera FOREIGN KEY (carrera_id) REFERENCES carreras(id) ON DELETE SET NULL;

-- 4. cursos: ciclo y periodo académico
ALTER TABLE cursos
    ADD COLUMN IF NOT EXISTS ciclo TINYINT UNSIGNED NULL AFTER docente_id,
    ADD COLUMN IF NOT EXISTS periodo_academico_id INT UNSIGNED NULL AFTER ciclo;

ALTER TABLE cursos ADD CONSTRAINT fk_cursos_periodo FOREIGN KEY (periodo_academico_id) REFERENCES periodos_academicos(id) ON DELETE SET NULL;

-- 5. matriculas: baja/reactivación en vez de borrado
ALTER TABLE matriculas
    ADD COLUMN IF NOT EXISTS estado ENUM('activo','retirado') NOT NULL DEFAULT 'activo' AFTER fecha_matricula,
    ADD COLUMN IF NOT EXISTS fecha_baja TIMESTAMP NULL AFTER estado;

-- 6. sesiones (tabla nueva, reemplaza el concepto de "unidades")
CREATE TABLE IF NOT EXISTS sesiones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    duracion_min SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    tema VARCHAR(200) NOT NULL,
    link_zoom VARCHAR(500) NULL,
    link_grabacion VARCHAR(500) NULL,
    estado ENUM('programada','realizada','cancelada') NOT NULL DEFAULT 'programada',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sesiones_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    KEY idx_sesiones_curso_fecha (curso_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. contenidos: sesion_id reemplaza a unidad_id (el material existente
--    se conserva, queda como "general del curso" al no tener sesión).
ALTER TABLE contenidos ADD COLUMN IF NOT EXISTS sesion_id INT UNSIGNED NULL AFTER curso_id;
ALTER TABLE contenidos ADD CONSTRAINT fk_contenidos_sesion FOREIGN KEY (sesion_id) REFERENCES sesiones(id) ON DELETE SET NULL;
ALTER TABLE contenidos DROP FOREIGN KEY fk_contenidos_unidad;
ALTER TABLE contenidos DROP COLUMN unidad_id;

-- 8. tareas: sesion_id reemplaza a unidad_id (misma lógica que arriba)
ALTER TABLE tareas ADD COLUMN IF NOT EXISTS sesion_id INT UNSIGNED NULL AFTER curso_id;
ALTER TABLE tareas ADD CONSTRAINT fk_tareas_sesion FOREIGN KEY (sesion_id) REFERENCES sesiones(id) ON DELETE SET NULL;
ALTER TABLE tareas DROP FOREIGN KEY fk_tareas_unidad;
ALTER TABLE tareas DROP COLUMN unidad_id;

-- 9. la tabla unidades ya no se usa
DROP TABLE IF EXISTS unidades;

-- 10. asistencias (tabla nueva)
CREATE TABLE IF NOT EXISTS asistencias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sesion_id INT UNSIGNED NOT NULL,
    estudiante_id INT UNSIGNED NOT NULL,
    estado ENUM('presente','tarde','falta','justificado') NOT NULL DEFAULT 'falta',
    observacion TEXT NULL,
    registrado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_asistencia (sesion_id, estudiante_id),
    CONSTRAINT fk_asistencias_sesion FOREIGN KEY (sesion_id) REFERENCES sesiones(id) ON DELETE CASCADE,
    CONSTRAINT fk_asistencias_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación rápida (deben aparecer las tablas/columnas nuevas):
-- SHOW TABLES;
-- DESCRIBE usuarios;
-- DESCRIBE cursos;
-- DESCRIBE matriculas;
