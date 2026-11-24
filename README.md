# El Huarike — API de reservas (Laravel 12)

API JSON para el sitio público, el área de cliente y el panel del restaurante
**El Huarike** (San Borja, Lima). Sustituye el backend simulado en
`localStorage` del prototipo React.

- **Stack:** Laravel 12, PHP 8.2+, MySQL 8, Laravel Sanctum (tokens Bearer).
- **Prefijo de rutas:** `/api/v1`.
- **Zona horaria:** `America/Lima`. **Idioma de mensajes:** español.
- **Fuente de verdad del alcance:** [`docs/backend-laravel-requisitos.md`](docs/backend-laravel-requisitos.md).
- **Especificación de trabajo (ODD):** [`odd/tasks/huarike-api-v1.md`](odd/tasks/huarike-api-v1.md).
- **Contrato OpenAPI:** [`docs/openapi.yaml`](docs/openapi.yaml).

---

## 1. Requisitos

| Requisito | Versión |
|---|---|
| PHP | 8.2 o superior (probado con 8.3) |
| Extensiones | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json` |
| Composer | 2.x |
| MySQL | 8.x |
| Node (opcional) | solo si vas a compilar assets del frontend |

## 2. Instalación

```bash
composer install
cp .env.example .env          # si el repo no trae .env
php artisan key:generate

# MySQL: crea las bases (una de aplicación y una de tests)
mysql -u root -p -e "CREATE DATABASE huarike_lara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE DATABASE huarike_lara_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Configura `.env`:

```dotenv
APP_ENV=local
APP_URL=http://localhost:8000
APP_TIMEZONE=America/Lima

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=huarike_lara
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_clave

QUEUE_CONNECTION=database
MAIL_MAILER=log
FRONTEND_URL=http://localhost:5173
HUARIKE_CORREO_RESTAURANTE=reservas@elhuarike.pe
```

Migraciones, datos base y enlace de almacenamiento público de fotos:

```bash
php artisan migrate --seed          # datos base (§8.4 del documento)
php artisan storage:link            # public/storage -> storage/app/public
php artisan serve                   # http://localhost:8000/api/v1
```

Reservas de demostración (solo desarrollo/local, folios `HRK-9001…`):

```bash
php artisan db:seed --class=DemoSeeder
```

Envío de correos y otros comandos útiles:

```bash
php artisan queue:work              # procesa los Mailables en cola
php artisan test                    # suite PHPUnit contra MySQL
./vendor/bin/pint                   # estilo (Laravel Pint)
```

## 3. Usuarios demo

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador (panel) | `ana.quispe@elhuarike.pe` | `panel2026` |
| Cliente | `demo@elhuarike.com` | `reservas2024` |

> Cámbialas antes de desplegar. Los admins **no** se autoregistran: se crean
> desde el panel o por seeder.

## 4. Autenticación

Sanctum en modo **token portador** (`Authorization: Bearer <token>`). No hay
sesión por cookie: el SPA y la API viven en orígenes distintos.

```bash
# Registro (siempre rol cliente)
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"nombre":"Lucía Ramírez","correo":"demo@elhuarike.com","telefono":"987654321","password":"reservas2024","password_confirmation":"reservas2024"}'

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"correo":"demo@elhuarike.com","password":"reservas2024"}'

# Sesión actual
curl http://localhost:8000/api/v1/auth/me -H "Authorization: Bearer $TOKEN"
```

El login distingue las tres causas de rechazo (`401` + `campo`): correo
inexistente, contraseña incorrecta y cuenta desactivada.

## 5. Configuración del SPA

- `VITE_API_URL=http://localhost:8000/api/v1` en el `.env` del frontend.
- En desarrollo, proxy de Vite: `server.proxy['/api'] → http://localhost:8000`.
- En producción, CORS se toma de `FRONTEND_URL` (`config/cors.php`),
  `supports_credentials = false`.
- Recuperación de contraseña: el correo apunta a
  `FRONTEND_URL/reset-password?token=…&correo=…`; el SPA llama a
  `POST /auth/password/reset`.
- Polling del panel: `GET /panel/cambios?desde=<ISO8601>` cada 30 s con la
  pestaña visible; refresca solo si `cambios` no está vacío.

## 6. Endpoints

Todas las rutas cuelgan de `/api/v1`. `🔒` = requiere token; `👤` = además rol
administrador.

### Autenticación
| Método | Ruta | Descripción |
|---|---|---|
| POST | `/auth/register` | Alta de cliente + token (5/min) |
| POST | `/auth/login` | Login + token (5/min) |
| POST | `/auth/password/email` | Envía enlace de recuperación (5/min) |
| POST | `/auth/password/reset` | Restablece con token y revoca tokens |
| POST 🔒 | `/auth/logout` | Revoca el token actual |
| GET 🔒 | `/auth/me` | `{ id, nombre, correo, telefono, rol, activa, creado_en }` |

### Sitio público
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/carta` | Categorías activas + resumen de la carta |
| GET | `/promociones?categoria` | Promociones activas |
| GET | `/horarios` | Franjas activas + estado del local |
| GET | `/turnos` | Turnos activos con sus slots |
| GET | `/mesas` | Mesas activas |
| GET | `/disponibilidad?fecha&personas` | Estado de cada slot |
| GET | `/disponibilidad/mesas?fecha&hora&personas` | Mesas válidas para la franja |

### Cliente 🔒
| Método | Ruta | Descripción |
|---|---|---|
| POST | `/reservas` | Crea reserva (opcional preorden) |
| GET | `/mis-reservas` | `{ proximas, pasadas }` |
| GET | `/reservas/{folio}` | Detalle (dueño o admin) |
| PATCH | `/reservas/{folio}/cancelar` | Cancela hasta 2 h antes |

### Panel 👤
| Método | Ruta | Descripción |
|---|---|---|
| GET/POST | `/panel/reservas` | Listado filtrado / alta |
| GET | `/panel/reservas/export.csv` | CSV con BOM |
| GET/PUT/PATCH | `/panel/reservas/{folio}` | Detalle / edición |
| PATCH | `/panel/reservas/{folio}/estado` | Cambio de estado |
| GET | `/panel/reportes?fecha` | KPIs del día |
| GET | `/panel/reportes/rango?desde&hasta` | Serie diaria |
| GET | `/panel/calendario?anio&mes` | 42 celdas del mes |
| GET | `/panel/calendario/semana?fecha` | 7 días |
| GET | `/panel/cambios?desde` | Polling del panel |
| GET/POST/PUT/PATCH/DELETE | `/panel/mesas` | Mesas + activar/desactivar |
| GET/POST/PUT/PATCH/DELETE | `/panel/categorias`, `/panel/platos` | Carta |
| POST | `/panel/platos/{id}/foto` | Upload de foto |
| GET/POST/PUT/PATCH/DELETE | `/panel/promociones` | Promociones |
| POST | `/panel/promociones/{id}/foto` | Upload de foto |
| GET/POST/PUT/PATCH/DELETE | `/panel/horarios`, `/panel/turnos` | Franjas y turnos |
| GET/POST/PUT/PATCH | `/panel/usuarios` | Usuarios (R1/R2) |
| GET | `/panel/usuarios/resumen`, `/panel/usuarios/export.csv` | Resumen y CSV |

La referencia completa de cuerpos, parámetros y respuestas está en
[`docs/openapi.yaml`](docs/openapi.yaml) (importable en Postman, Insomnia o
Swagger UI).

## 7. Reglas de negocio clave

- **Choque de mesa:** dos reservas de la misma mesa chocan si empiezan a menos
  de **90 minutos** en la misma fecha. `cancelada` y `lista-espera` no ocupan
  franja. Se valida en el `Service` con `lockForUpdate`, no con índice único.
- **Lista de espera:** reservar sin `mesa_id` crea `estado=lista-espera`.
- **Folio:** generado por el backend, `HRK-<máximo+1>` desde `HRK-2481`.
- **Cancelación del cliente:** solo hasta **2 horas** antes del inicio.
- **Precios congelados:** la preorden guarda nombre y precio del momento.
- **Historial acumulativo:** cada cambio agrega una fila en `reserva_historial`.
- **R1/R2:** un admin no se autodesactiva y nunca queda el sistema sin
  administradores activos (`409` con `motivo`).
- **Turnos y franjas editables:** disponibilidad, validación de hora, filtros,
  ocupación y estado del local leen las tablas `turnos`/`horarios`.
- **Estado del local:** `Abierto ahora · cierra a las 17:00` o
  `Cerrado · abre el lunes a las 8:00`, calculado en `America/Lima`.

## 8. Errores

```json
// 422 validación
{ "message": "Los datos enviados no son válidos",
  "errors": { "correo": ["Revisa el correo: falta un formato válido (ejemplo@dominio.com)."] } }

// 409 regla de negocio
{ "message": "Esa mesa ya está ocupada en esa franja", "motivo": "conflicto", "mesas": [ … ] }

// 401 / 403
{ "message": "Tu sesión expiró. Inicia sesión de nuevo." }
```

Códigos: `200/201` éxito · `401` sin token · `403` rol insuficiente o recurso
ajeno · `404` no existe · `409` regla de negocio · `422` validación · `429`
rate limit.

## 9. Tests

```bash
php artisan test
```

- La suite corre contra **MySQL** (`huarike_lara_test`, ver `phpunit.xml`),
  no contra SQLite: el documento exige MySQL 8 y la regla de 90 minutos usa
  locks de fila.
- La cobertura por capa (`php --coverage`) requiere `xdebug` o `pcov`, que no
  están instalados en este entorno; por eso el objetivo de 80 % se sostiene con
  feature tests por endpoint (136 tests, 659 assertions) en lugar de un
  porcentaje medido.

## 10. Decisiones y desvíos respecto al documento

1. **Promociones sembradas:** el documento menciona seis, el prototipo define
   cinco; se siembran las cinco reales para no inventar contenido comercial.
2. **`categorias` con soft-delete:** §5.1 pide soft-delete en el `DELETE`, así
   que la tabla lo tiene aunque §3.3 solo lo liste para platos, promociones y
   mesas.
3. **Fotos sembradas en `null`:** el prototipo usa assets de Vite; `foto: null`
   es el fallback documentado del SPA.
4. **Simulación de ocupación:** no se replica la ocupación determinista del
   prototipo; solo cuentan reservas reales.
5. **`reserva_platos` con `id`:** se agrega una PK propia (además de
   `reserva_id` + `plato_id` único) para simplificar Eloquent; el JSON no la
   expone.

## 11. Seguridad

- Validación server-side en Form Requests; nunca se confía en el SPA.
- Policies en todo `/panel/*` y sobre reservas propias.
- Rate limit `5/min` en register/login/recuperación y `60/min` en público.
- `password` nunca aparece en respuestas.
- Detalle de reserva por `folio`, no por id autoincremental.
- Uploads limitados a `jpg,jpeg,png,webp` y 4 MB, guardados con nombre hash.
