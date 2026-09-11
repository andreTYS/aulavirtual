-- ============================================================
-- Migración v3 -> v4 (Comentarios dentro del curso)
-- Aula Virtual - IESTP Benjamín Franklin
--
-- Ejecutar UNA SOLA VEZ contra una base que ya corrió
-- migration_v2_to_v3.sql. Solo agrega la tabla `comentarios`; no
-- borra ni modifica nada existente. El calendario y los datos de
-- demostración de esta misma entrega no requieren cambios de esquema.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS comentarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL,
    autor_id INT UNSIGNED NULL,
    contenido TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comentarios_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    CONSTRAINT fk_comentarios_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_comentarios_curso (curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación: SHOW TABLES; debe incluir 'comentarios'.
