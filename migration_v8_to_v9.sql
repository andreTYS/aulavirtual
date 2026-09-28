-- ============================================================
-- Migración v8 -> v9 (exámenes en línea)
-- Aula Virtual - IESTP Benjamín Franklin
--
-- Ejecutar UNA SOLA VEZ contra una base que ya corrió
-- migration_v7_to_v8.sql. Agrega las tablas para el módulo de
-- exámenes de opción múltiple / verdadero-falso con
-- autocalificación; no borra ni modifica nada existente. El
-- "horario semanal" y "sesiones recurrentes" de esta misma entrega
-- no requieren cambios de esquema (reutilizan la tabla `sesiones`).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS examenes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    peso DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    fecha_limite DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_examenes_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    KEY idx_examenes_curso (curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examen_preguntas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    examen_id INT UNSIGNED NOT NULL,
    enunciado TEXT NOT NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_preguntas_examen FOREIGN KEY (examen_id) REFERENCES examenes(id) ON DELETE CASCADE,
    KEY idx_preguntas_examen (examen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examen_opciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pregunta_id INT UNSIGNED NOT NULL,
    texto VARCHAR(500) NOT NULL,
    es_correcta TINYINT(1) NOT NULL DEFAULT 0,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_opciones_pregunta FOREIGN KEY (pregunta_id) REFERENCES examen_preguntas(id) ON DELETE CASCADE,
    KEY idx_opciones_pregunta (pregunta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examen_intentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    examen_id INT UNSIGNED NOT NULL,
    estudiante_id INT UNSIGNED NOT NULL,
    puntaje DECIMAL(4,2) NULL,
    fecha_inicio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_envio TIMESTAMP NULL,
    UNIQUE KEY uk_intento (examen_id, estudiante_id),
    CONSTRAINT fk_intentos_examen FOREIGN KEY (examen_id) REFERENCES examenes(id) ON DELETE CASCADE,
    CONSTRAINT fk_intentos_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS examen_respuestas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    intento_id INT UNSIGNED NOT NULL,
    pregunta_id INT UNSIGNED NOT NULL,
    opcion_id INT UNSIGNED NULL,
    UNIQUE KEY uk_respuesta (intento_id, pregunta_id),
    CONSTRAINT fk_respuestas_intento FOREIGN KEY (intento_id) REFERENCES examen_intentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_respuestas_pregunta FOREIGN KEY (pregunta_id) REFERENCES examen_preguntas(id) ON DELETE CASCADE,
    CONSTRAINT fk_respuestas_opcion FOREIGN KEY (opcion_id) REFERENCES examen_opciones(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación: SHOW TABLES; debe incluir 'examenes', 'examen_preguntas',
-- 'examen_opciones', 'examen_intentos' y 'examen_respuestas'.
