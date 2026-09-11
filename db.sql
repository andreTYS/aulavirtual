-- ============================================================
-- Aula Virtual - IESTP Benjamin Franklin (Moquegua, Peru)
-- Esquema de base de datos - v2 (segun PRD: Entrega 1)
-- MySQL / MariaDB 10.11+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- carreras: programas de estudio del instituto
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS carreras (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    duracion_ciclos TINYINT UNSIGNED NOT NULL DEFAULT 6,
    UNIQUE KEY uk_carreras_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- periodos_academicos: ciclos de matricula (ej. "2026-I")
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS periodos_academicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_periodo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- usuarios: administradores, docentes y estudiantes en una sola
-- tabla, diferenciados por el campo `rol`. Los campos DNI,
-- telefono, carrera_id y apoderado_* solo aplican a estudiantes;
-- especialidad solo aplica a docentes (quedan NULL en los demas
-- casos, simplificacion respecto al PRD para no fragmentar el
-- login/autenticacion en tablas separadas).
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
    dni VARCHAR(15) NULL,
    telefono VARCHAR(20) NULL,
    carrera_id INT UNSIGNED NULL,
    especialidad VARCHAR(150) NULL,
    apoderado_nombre VARCHAR(150) NULL,
    apoderado_telefono VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuarios_username (username),
    UNIQUE KEY uk_usuarios_email (email),
    UNIQUE KEY uk_usuarios_dni (dni),
    KEY idx_usuarios_rol (rol),
    CONSTRAINT fk_usuarios_carrera FOREIGN KEY (carrera_id) REFERENCES carreras(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- cursos: pertenece a una carrera, un ciclo y un periodo
-- academico, con un docente asignado (puede quedar sin asignar).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cursos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    carrera_id INT UNSIGNED NOT NULL,
    docente_id INT UNSIGNED NULL,
    ciclo TINYINT UNSIGNED NULL,
    periodo_academico_id INT UNSIGNED NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cursos_carrera FOREIGN KEY (carrera_id) REFERENCES carreras(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cursos_docente FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_cursos_periodo FOREIGN KEY (periodo_academico_id) REFERENCES periodos_academicos(id) ON DELETE SET NULL,
    KEY idx_cursos_docente (docente_id),
    KEY idx_cursos_carrera (carrera_id),
    KEY idx_cursos_periodo (periodo_academico_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- matriculas: relacion muchos-a-muchos entre estudiantes y cursos.
-- `estado` permite dar de baja una matricula dejando registro,
-- en vez de borrarla.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS matriculas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL,
    estudiante_id INT UNSIGNED NOT NULL,
    fecha_matricula TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activo','retirado') NOT NULL DEFAULT 'activo',
    fecha_baja TIMESTAMP NULL,
    UNIQUE KEY uk_matricula (curso_id, estudiante_id),
    CONSTRAINT fk_matriculas_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    CONSTRAINT fk_matriculas_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- sesiones: clase programada de un curso (presencial o via Zoom).
-- El link de Zoom solo se muestra activo al estudiante el mismo
-- dia de la sesion (regla de negocio validada en la capa de
-- aplicacion, no en la base de datos).
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- contenidos: material subido por el docente (pdf, video o enlace),
-- ligado al curso y opcionalmente a una sesion especifica.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contenidos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL,
    sesion_id INT UNSIGNED NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    tipo ENUM('pdf','video','enlace') NOT NULL,
    archivo_path VARCHAR(500) NULL,
    url VARCHAR(500) NULL,
    fecha_publicacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contenidos_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    CONSTRAINT fk_contenidos_sesion FOREIGN KEY (sesion_id) REFERENCES sesiones(id) ON DELETE SET NULL,
    KEY idx_contenidos_curso (curso_id),
    KEY idx_contenidos_sesion (sesion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- tareas: asignaciones creadas por el docente, ligadas al curso y
-- opcionalmente a una sesion especifica.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tareas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id INT UNSIGNED NOT NULL,
    sesion_id INT UNSIGNED NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    fecha_limite DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tareas_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    CONSTRAINT fk_tareas_sesion FOREIGN KEY (sesion_id) REFERENCES sesiones(id) ON DELETE SET NULL,
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

-- ------------------------------------------------------------
-- asistencias: registro de asistencia de cada estudiante
-- matriculado en una sesion especifica.
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- avisos: muro de anuncios. curso_id NULL = aviso general para
-- todo el instituto (solo administrador); con curso_id = aviso de
-- ese curso, visible para su docente y sus estudiantes matriculados.
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- comentarios: muro de comentarios dentro de un curso, visible
-- para el docente del curso y sus estudiantes matriculados.
-- ------------------------------------------------------------
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

-- ============================================================
-- Datos iniciales
-- ============================================================

INSERT INTO carreras (nombre, duracion_ciclos) VALUES
    ('Computación e Informática', 6),
    ('Enfermería Técnica', 6),
    ('Contabilidad', 6),
    ('Administración de Empresas', 6),
    ('Electrónica Industrial', 6)
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
