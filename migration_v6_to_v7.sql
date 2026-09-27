-- ============================================================
-- Migración v6 -> v7 (Integración con Google Meet - reemplaza Zoom)
-- Aula Virtual - IESTP Benjamín Franklin
--
-- Ejecutar UNA SOLA VEZ contra una base que ya corrió
-- migration_v5_to_v6.sql. Renombra `sesiones.link_zoom` a
-- `link_meet` (los enlaces ya guardados se conservan), agrega
-- `sesiones.google_event_id` y crea la tabla `google_tokens`.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE sesiones
    CHANGE COLUMN link_zoom link_meet VARCHAR(500) NULL;

ALTER TABLE sesiones
    ADD COLUMN IF NOT EXISTS google_event_id VARCHAR(255) NULL AFTER link_meet;

CREATE TABLE IF NOT EXISTS google_tokens (
    usuario_id INT UNSIGNED PRIMARY KEY,
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    expira_en TIMESTAMP NOT NULL,
    scope VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_google_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación: DESCRIBE sesiones; debe mostrar `link_meet` y
-- `google_event_id` (ya no `link_zoom`). SHOW TABLES; debe incluir
-- 'google_tokens'.
