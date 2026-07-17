-- ============================================================
-- Aula Virtual - IESTP Benjamin Franklin (Moquegua, Peru)
-- Esquema de base de datos - MVP
-- MySQL / MariaDB 10.11+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- usuarios: administradores, docentes y estudiantes en una sola
-- tabla, diferenciados por el campo `rol`.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('administrador','docente','estudiante') NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuarios_username (username),
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- carreras: programas de estudio del instituto
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS carreras (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    UNIQUE KEY uk_carreras_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- cursos: un curso pertenece a una carrera y tiene un docente
-- asignado (puede quedar sin asignar temporalmente).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cursos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    carrera_id INT UNSIGNED NOT NULL,
    docente_id INT UNSIGNED NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cursos_carrera FOREIGN KEY (carrera_id) REFERENCES carreras(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cursos_docente FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_cursos_docente (docente_id),
    KEY idx_cursos_carrera (carrera_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- matriculas: relacion muchos-a-muchos entre estudiantes y cursos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS matriculas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL,
    estudiante_id INT UNSIGNED NOT NULL,
    fecha_matricula TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_matricula (curso_id, estudiante_id),
    CONSTRAINT fk_matriculas_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    CONSTRAINT fk_matriculas_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- unidades: organizan el contenido de un curso por unidad/semana
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS unidades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    orden INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_unidades_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    KEY idx_unidades_curso (curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- contenidos: material subido por el docente (pdf, video o enlace)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contenidos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unidad_id INT UNSIGNED NOT NULL,
    curso_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    tipo ENUM('pdf','video','enlace') NOT NULL,
    archivo_path VARCHAR(500) NULL,
    url VARCHAR(500) NULL,
    fecha_publicacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contenidos_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE,
    CONSTRAINT fk_contenidos_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    KEY idx_contenidos_unidad (unidad_id),
    KEY idx_contenidos_curso (curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- tareas: asignaciones creadas por el docente dentro de un curso
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tareas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL,
    unidad_id INT UNSIGNED NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    fecha_limite DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tareas_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    CONSTRAINT fk_tareas_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE SET NULL,
    KEY idx_tareas_curso (curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- entregas: archivo subido por el estudiante para una tarea, con
-- calificacion (escala vigesimal peruana 0-20) y comentario del
-- docente.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS entregas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT UNSIGNED NOT NULL,
    estudiante_id INT UNSIGNED NOT NULL,
    archivo_path VARCHAR(500) NOT NULL,
    fecha_entrega TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    calificacion DECIMAL(4,2) NULL,
    comentario TEXT NULL,
    fecha_calificacion TIMESTAMP NULL,
    UNIQUE KEY uk_entrega (tarea_id, estudiante_id),
    CONSTRAINT fk_entregas_tarea FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
    CONSTRAINT fk_entregas_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT chk_calificacion CHECK (calificacion IS NULL OR (calificacion >= 0 AND calificacion <= 20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Datos iniciales
-- ============================================================

INSERT INTO carreras (nombre) VALUES
    ('Computación e Informática'),
    ('Enfermería Técnica'),
    ('Contabilidad'),
    ('Administración de Empresas'),
    ('Electrónica Industrial')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Usuario administrador inicial.
-- Usuario: admin  |  Email: admin@iestpbf.edu.pe  |  Contraseña: ver README
-- El hash de abajo corresponde a la contraseña entregada en el README de despliegue.
INSERT INTO usuarios (nombre, apellidos, username, email, password_hash, rol, activo)
VALUES (
    'Administrador',
    'Sistema',
    'admin',
    'admin@iestpbf.edu.pe',
    '$2y$12$MrpBzbyAoZbDEqAKimV2IulleDMNXxE5SPIaRznsXfrFFi3VkTFbG',
    'administrador',
    1
)
ON DUPLICATE KEY UPDATE email = VALUES(email);
