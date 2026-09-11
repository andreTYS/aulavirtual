# Aula Virtual — IESTP Benjamín Franklin (Moquegua, Perú)

MVP de aula virtual (tipo Moodle/Q10 simplificado) para el instituto. Stack:
PHP 8.4 plano (PDO, sin frameworks), MariaDB 10.11, Bootstrap 5 vía CDN,
Docker Compose detrás de Traefik.

## Estructura del proyecto

```
aulavirtual/
├── docker-compose.yml
├── db.sql                      # Esquema completo + datos iniciales
└── public_html/                # Esto es lo que se sube al VPS
    ├── config/                 # Conexión BD y configuración (protegido por .htaccess)
    ├── includes/                # auth.php, functions.php, header/footer
    ├── admin/                  # Panel Administrador
    ├── docente/                 # Panel Docente
    ├── estudiante/               # Panel Estudiante
    ├── uploads/                 # Archivos subidos (protegido, servido por download.php)
    ├── download.php             # Descarga de archivos con control de permisos
    ├── login.php / logout.php / index.php
    └── 403.php
```

## Alcance funcional (MVP)

- **Administrador**: CRUD de usuarios (docentes/estudiantes), CRUD de cursos,
  CRUD de carreras, matrícula de estudiantes en cursos.
- **Docente**: ve sus cursos asignados, organiza contenido por unidades
  (PDF, video o enlace), crea tareas con fecha límite, revisa entregas y
  califica en escala vigesimal (0-20) con comentario.
- **Estudiante**: ve sus cursos matriculados, contenido por unidad, tareas
  pendientes, sube su entrega (con opción de reemplazar mientras no esté
  calificada), y ve sus calificaciones por curso.

Flujo end-to-end verificado: login → curso → contenido → tarea → entrega → nota.

## Base de datos

Esquema en `db.sql`: `usuarios` (rol enum admin/docente/estudiante),
`carreras`, `cursos`, `matriculas` (N:M curso-estudiante), `unidades`,
`contenidos`, `tareas`, `entregas` (con `calificacion DECIMAL(4,2)` y
CHECK 0-20). Todas las relaciones tienen claves foráneas con `ON DELETE
CASCADE`/`SET NULL` según corresponda.

## Seguridad implementada

- Contraseñas con `password_hash()` (bcrypt).
- Todas las consultas usan sentencias preparadas PDO (sin concatenar SQL).
- Cada panel valida el rol de sesión (`requireRole()`) — un estudiante no
  puede ver rutas de docente/admin y viceversa; se verifica además que el
  docente sea dueño del curso/tarea y que el estudiante esté matriculado.
- CSRF token en todos los formularios que modifican datos.
- Los archivos subidos NO son accesibles directamente: `uploads/.htaccess`
  deniega el acceso y todo se sirve mediante `download.php`, que valida
  permisos (admin: todo: docente: solo sus cursos; estudiante: solo su
  propio contenido matriculado o su propia entrega) antes de leer el
  archivo con `readfile()`.
- `config/.htaccess` deniega acceso directo a los archivos de configuración.

## docker-compose.yml — nota importante sobre la red

Se agregó la red `traefik` (que es tu `n8n_default` externa) también al
servicio `db`. En tu plantilla original el servicio `db` no declaraba
`networks`, por lo que quedaría en la red "default" de Compose mientras
que `web` solo está en `n8n_default` — al no compartir red, el contenedor
web nunca podría resolver `aulavirtual-db` ni conectarse a MySQL. Con
ambos servicios en `n8n_default`, Docker resuelve `aulavirtual-db` por
nombre de contenedor sin exponer el puerto 3306 a internet. El resto del
archivo sigue exactamente tu plantilla (imagen, labels de Traefik, rutas
de volumen, nombres de contenedor).

También se agregó el montaje de `db.sql` como script de inicialización
(`docker-entrypoint-initdb.d`), así que la base de datos y el usuario
administrador quedan creados automáticamente la primera vez que arranca
el contenedor `db` (con datos ya existentes en el volumen, MariaDB no
vuelve a ejecutar los scripts de init — para reaplicar el esquema en una
base ya inicializada, hazlo manualmente, ver más abajo).

## Credenciales generadas

**Usuario administrador inicial** (ya cargado en `db.sql`):

| Campo | Valor |
|---|---|
| Usuario | `admin` |
| Correo | `admin@iestpbf.edu.pe` |
| Contraseña | `IESTPbf-et89TF32` |

**Credenciales de base de datos** (ya están en `docker-compose.yml` y
coinciden con `public_html/config/database.php`):

| Variable | Valor |
|---|---|
| `MYSQL_ROOT_PASSWORD` | `8aPPwLpuYT3cgZrNBtFKDiNx` |
| `MYSQL_DATABASE` | `aulavirtual` |
| `MYSQL_USER` | `aulavirtual` |
| `MYSQL_PASSWORD` | `qjWiBhv8vNi28334sNdG94Ei` |

**Cambia la contraseña del administrador y las credenciales de BD antes
de poner el sistema en producción real** (o al menos inmediatamente
después del primer despliegue, desde el propio panel de administrador
para el usuario, y editando `docker-compose.yml` + `config/database.php`
para la BD).

## Despliegue en el VPS

1. Crea la carpeta de destino y copia **solo el contenido de `public_html/`**
   (no la carpeta `public_html` en sí) dentro de:

   ```
   /opt/sites/aulavirtual.iestpbf.edu.pe/public_html/
   ```

   Es decir, `config/`, `includes/`, `admin/`, `docente/`, `estudiante/`,
   `uploads/`, `download.php`, `login.php`, etc. deben quedar
   directamente dentro de esa ruta.

2. Da permisos de escritura al directorio de subidas (el usuario que
   corre PHP dentro del contenedor `php-apache-custom` normalmente es
   `www-data`):

   ```bash
   chown -R www-data:www-data /opt/sites/aulavirtual.iestpbf.edu.pe/public_html/uploads
   chmod -R 775 /opt/sites/aulavirtual.iestpbf.edu.pe/public_html/uploads
   ```

3. Copia `docker-compose.yml` y `db.sql` a la carpeta donde manejas tus
   stacks de Docker Compose (ej. `/opt/stacks/aulavirtual/`), manteniendo
   ambos archivos en el mismo directorio (el compose monta `./db.sql`).

4. Verifica que la red externa exista (ya la tienes de tus otros
   proyectos):

   ```bash
   docker network inspect n8n_default
   ```

5. Levanta el stack:

   ```bash
   cd /opt/stacks/aulavirtual
   docker compose up -d
   ```

6. Verifica que el DNS de `aulavirtual.iestpbf.edu.pe` apunte al VPS y que
   Traefik emita el certificado (revisa logs de Traefik si no carga por
   HTTPS en los primeros minutos).

7. Entra a `https://aulavirtual.iestpbf.edu.pe/login.php` con las
   credenciales de administrador de arriba.

### Reaplicar el esquema manualmente (si el volumen de BD ya existía)

```bash
docker exec -i aulavirtual-db mariadb -uaulavirtual -pqjWiBhv8vNi28334sNdG94Ei aulavirtual < db.sql
```

### Backups sugeridos

- Base de datos: `docker exec aulavirtual-db mariadb-dump -uroot -p... aulavirtual > backup.sql`
- Archivos subidos: respaldar `/opt/sites/aulavirtual.iestpbf.edu.pe/public_html/uploads`
  periódicamente (no está en la base de datos).

## Límites conocidos del MVP (fuera de alcance por ahora)

- No hay recuperación de contraseña por correo (el admin la resetea
  manualmente desde el panel de usuarios).
- No hay notificaciones por correo de nuevas tareas/calificaciones.
- No hay foros de discusión ni mensajería interna.
- El tamaño máximo de subida es 50 MB (`config/config.php`, constante
  `MAX_UPLOAD_BYTES`) — ajustable también en `upload_max_filesize` /
  `post_max_size` de PHP si tu imagen los limita más.
