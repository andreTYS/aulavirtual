-- ============================================================
-- Migración v5 -> v6 (Mensajería interna - ampliación de paneles)
-- Aula Virtual - IESTP Benjamín Franklin
--
-- Ejecutar UNA SOLA VEZ contra una base que ya corrió
-- migration_v4_to_v5.sql. Agrega la tabla `mensajes`; no borra ni
-- modifica nada existente.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS mensajes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    remitente_id INT UNSIGNED NOT NULL,
    destinatario_id INT UNSIGNED NOT NULL,
    curso_id INT UNSIGNED NULL,
    cuerpo TEXT NOT NULL,
    leido TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mensajes_remitente FOREIGN KEY (remitente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_mensajes_destinatario FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_mensajes_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE SET NULL,
    KEY idx_mensajes_destinatario (destinatario_id, leido),
    KEY idx_mensajes_remitente (remitente_id),
    KEY idx_mensajes_conversacion (remitente_id, destinatario_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación: SHOW TABLES; debe incluir 'mensajes'.
