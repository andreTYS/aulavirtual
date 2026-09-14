-- ============================================================
-- Migración v4 -> v5 (Pagos y vouchers - Fase 2 del PRD)
-- Aula Virtual - IESTP Benjamín Franklin
--
-- Ejecutar UNA SOLA VEZ contra una base que ya corrió
-- migration_v3_to_v4.sql. Agrega las tablas `conceptos_pago` y
-- `pagos`, mas los conceptos de pago por defecto; no borra ni
-- modifica nada existente.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS conceptos_pago (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    monto_sugerido DECIMAL(8,2) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_concepto_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT UNSIGNED NOT NULL,
    concepto_pago_id INT UNSIGNED NOT NULL,
    monto DECIMAL(8,2) NOT NULL,
    fecha_pago DATE NOT NULL,
    numero_voucher VARCHAR(100) NULL,
    comprobante_path VARCHAR(500) NULL,
    estado ENUM('pendiente','validado','rechazado') NOT NULL DEFAULT 'pendiente',
    observacion TEXT NULL,
    registrado_por INT UNSIGNED NULL,
    fecha_validacion TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pagos_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagos_concepto FOREIGN KEY (concepto_pago_id) REFERENCES conceptos_pago(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pagos_registrador FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_pagos_estudiante (estudiante_id),
    KEY idx_pagos_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO conceptos_pago (nombre, monto_sugerido) VALUES
    ('Matrícula', 150.00),
    ('Pensión mensual', 250.00),
    ('Certificado / trámite', 50.00)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación: SHOW TABLES; debe incluir 'conceptos_pago' y 'pagos'.
