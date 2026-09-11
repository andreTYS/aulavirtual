-- ============================================================
-- Migración v2 -> v3 (Comunicación: muro de avisos)
-- Aula Virtual - IESTP Benjamín Franklin
--
-- Ejecutar UNA SOLA VEZ contra una base de datos que ya corrió
-- migration_v1_to_v2.sql. Solo agrega la tabla `avisos`; no borra
-- ni modifica nada existente.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS avisos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    autor_id INT UNSIGNED NULL,
    curso_id INT UNSIGNED NULL,
    titulo VARCHAR(200) NOT NULL,
    contenido TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_avisos_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_avisos_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    KEY idx_avisos_curso (curso_id),
    KEY idx_avisos_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación: SHOW TABLES; debe incluir 'avisos'.
