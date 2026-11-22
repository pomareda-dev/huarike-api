# Requisitos del backend — API Laravel 12 + MySQL para El Huarike

> Documento de especificación para crear el backend en un **repositorio nuevo**, separado del
> frontend React (SPA) actual. El frontend seguirá siendo un SPA; este backend lo consume
> como API JSON. Copiar este archivo al repositorio del backend y tratarlo como fuente de
> verdad del alcance.

- **Stack decidido:** Laravel 12 (API), MySQL 8, Laravel Sanctum (tokens), PHP 8.3+.
- **Alcance decidido:** paridad funcional completa con el frontend actual (hoy resuelto con
  `localStorage`) **más** seis decisiones: notificaciones email, CRUD de promociones, upload
  real de imágenes de platos/promociones, actualización del panel por **polling cada 30 s**
  (sin WebSockets), horarios y turnos de reserva editables desde el panel, y recuperación
  de contraseña por email.
- **Fuente de verdad de la paridad:** el código del frontend React de este repo. Cada regla
  de negocio citada abajo indica su origen con ruta `archivo:regla`.

---

## 1. Contexto y arquitectura general

El frontend React (Vite) es un SPA con tres áreas:

| Área | Rutas base | Quién entra | Datos que consume |
|---|---|---|---|
| Sitio público | `/`, `/carta`, `/promociones`, `/nosotros`, `/contacto` | cualquiera | carta, promociones, horarios, estado del local |
| Área cliente | `/cliente/*` (login, registro, disponibilidad, preorden, datos, confirmación, mis reservas, cuenta) | usuarios con rol `cliente` | su sesión, disponibilidad de mesas, carta, sus reservas |
| Panel admin | `/panel/*` (reservas, calendario, mesas, carta, categorías, platos, usuarios, reportes) | usuarios con rol `admin` | todo |

Arquitectura del backend:

```text
React SPA (Vite, puerto 5173)  ──HTTPS/JSON──▶  Laravel 12 API (/api/v1)  ──▶  MySQL 8
                                                    │
                                                    ├─ Sanctum (Bearer tokens)
                                                    ├─ Mailables en cola (emails)
                                                    └─ Polling del panel (GET /panel/cambios)
```

- **Sin vistas Blade ni sesión web:** el proyecto Laravel se crea con `api` como único
  front-end (`laravel new backend --api`), rutas exclusivamente bajo `routes/api.php`
  prefijadas `/api/v1`.
- **Autenticación:** Laravel Sanctum en modo **token portador** (`Authorization: Bearer`).
  El SPA y la API viven en orígenes distintos (repo separado), así que se descarta el modo
  cookie/same-domain de Sanctum para esta entrega; se documenta como posible evolución.
- **Capas obligatorias por endpoint:** `Route → Form Request (validación) → Controller →
  Service (reglas de negocio) → Eloquent Model → API Resource (salida JSON)`, con
  `Policy` para autorización por rol y propiedad.

---

## 2. Roles y permisos

Dos roles, sin tabla de roles aparte (columna `rol` en `users`, como hace el frontend en
`src/lib/panel.js`: `ROLES = { admin, cliente }`).

| Capacidad | cliente | admin |
|---|---|---|
| Ver carta, promociones, horarios, disponibilidad | ✔ | ✔ |
| Registrarse / iniciar sesión | ✔ | ✔ (no se autoregistran admins) |
| Crear reserva propia (con preorden) | ✔ | ✔ |
| Ver/cancelar **sus** reservas | ✔ | ✔ |
| CRUD mesas, categorías, platos, promociones, usuarios | ✗ | ✔ |
| Gestionar reservas de cualquier cliente (crear, editar, cambiar estado, historial) | ✗ | ✔ |
| Reportes, calendario, export CSV | ✗ | ✔ |

Reglas de integridad de roles (heredadas de `src/lib/panel.js`, funciones `puedeDesactivar`
y `puedeQuitarAdmin`):

- R1. Un admin **no puede desactivar su propia cuenta**.
- R2. No puede quedar el sistema **sin administradores activos**: desactivar o quitar el rol
  a un admin se rechaza (HTTP 409) si él es el último admin activo.
- R3. Un cliente solo puede actuar sobre reservas cuyo `correo` coincida con su cuenta
  (Policy `ReservaPolicy@own`); el admin pasa siempre.

---

## 3. Modelo de datos (MySQL)

Convenciones: UTF8MB4, InnoDB, timestamps Laravel (`created_at`/`updated_at`),
soft-delete solo en `platos`, `promociones` y `mesas` (nunca borrar lo que tuvo reservas).

### 3.1 `users`

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| nombre | varchar(120) | obligatorio, mínimo 3 (`src/lib/cuenta.js: validarNombre`) |
| correo | varchar(190) UNIQUE | obligatorio, se normaliza: trim + minúsculas (`normalizarCorreo`) |
| password | varchar(255) | hash bcrypt; mínimo 8 caracteres (`validarClave`) |
| telefono | varchar(20) | 9 dígitos, con o sin `+51`, se aceptan espacios/guiones (`validarTelefono`) |
| rol | enum('admin','cliente') | default `cliente` |
| activa | boolean | default `true`; falsa = cuenta desactivada, no puede ingresar |
| timestamps | | |

### 3.2 `mesas` (origen: `src/lib/reservas.js: MESAS` y `src/lib/mesas.js`)

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| numero | int UNIQUE | consecutivo; el panel calcula "mayor + 1" (`siguienteNumeroMesa`) |
| zona | enum('Salón','Terraza') | cerrado: `ZONAS` en `mesas.js` |
| capacidad | tinyint | 1 a 8 (`CAPACIDAD_MINIMA/MAXIMA`) |
| activa | boolean | inactiva no se ofrece al cliente pero **conserva sus reservas** |
| timestamps, softDeletes | | |

Semilla: las 12 mesas del frontend (`reservas.js`), 38 asientos.

### 3.3 `categorias` (origen: `CATEGORIAS_CARTA` en `src/content/sitio.js`)

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| nombre | varchar(120) UNIQUE | |
| posicion | smallint | orden de la carta |
| activa | boolean | inactiva se oculta del sitio (`contarCarta` en `carta.js`) |
| timestamps | | |

### 3.4 `platos` (origen: `CATEGORIAS_CARTA[].platos`, `src/lib/carta.js: validarPlato`)

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| categoria_id | FK → categorias | obligatoria; el validador exige "elige una categoría" |
| nombre | varchar(150) | mínimo 3, único por categoría (la UI genera ids slug únicos) |
| descripcion | text nullable | |
| precio | decimal(8,2) | > 0 (`validarPlato`) |
| etiquetas | json | array de strings, p.ej. `["Más pedido"]` |
| foto | varchar(255) nullable | ruta relativa del archivo subido (extra: upload) |
| disponible | boolean | default `true`; false = "agotado" |
| timestamps, softDeletes | | |

### 3.5 `reservas` (origen: `construirReserva` en `src/lib/reservas.js`)

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| folio | varchar(12) UNIQUE | `HRK-####`, consecutivo (`siguienteFolio`: mayor existente + 1) |
| user_id | FK → users NULLABLE | cliente registrado dueño; null si la cargó el admin sin cuenta |
| nombre | varchar(120) | datos de contacto de la reserva |
| correo | varchar(190) | |
| telefono | varchar(20) | 9 dígitos peruanos |
| notas | text nullable | |
| fecha | date | hoy o posterior (`esFechaValida` en `horario.js`) |
| hora | time | solo slots de turno (ver §5.2) |
| personas | tinyint | 1 a 6 (`validarReservaAdmin` en `panel.js`) |
| mesa_id | FK → mesas NULLABLE | null ⇒ lista de espera, **no ocupa mesa** |
| estado | enum('confirmada','por-confirmar','lista-espera','no-show','finalizada','cancelada') | `ESTADOS_RESERVA` en `reservas.js` |
| subtotal | decimal(10,2) | Σ precio×cantidad de la preorden |
| creada_por | varchar(120) NULLABLE | nombre del admin cuando la crea el panel |
| timestamps | | |

**Constraint de negocio crítico (unique index compuesto no sirve):** dos reservas de la
misma mesa chocan si empiezan a menos de **90 minutos** en la misma fecha
(`DURACION_MINUTOS`, `hayConflicto`). Excluye estados `cancelada` y `lista-espera`. Se
valida en `Service` con lock (`lockForUpdate`) al crear/editar, **no** como índice SQL.

### 3.6 `reserva_platos` (preorden, origen: `platos` en `construirReserva`)

| Columna | Tipo | Reglas |
|---|---|---|
| reserva_id | FK → reservas | |
| plato_id | FK → platos NULLABLE | |
| nombre | varchar(150) | snapshot del nombre al momento de reservar |
| precio | decimal(8,2) | snapshot del precio |
| cantidad | smallint | ≥ 1 |

### 3.7 `reserva_historial` (origen: `historial[]` en `construirReserva` / `panelDemo.js`)

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| reserva_id | FK → reservas | |
| tipo | varchar(30) | p.ej. `estado`, `creacion`, `edicion` |
| texto | varchar(255) | texto visible, p.ej. "Confirmada por WhatsApp al cliente" |
| autor | varchar(120) | quien hizo el cambio |
| created_at | | |

Cada cambio de estado/edición **añade** una entrada; nunca se sobreescribe.

### 3.8 `promociones` (hoy hardcodeadas en `src/content/sitio.js: PROMOCIONES`; extra: CRUD)

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| nombre | varchar(150) | obligatorio |
| slug | varchar(160) UNIQUE | generado del nombre |
| destacada | boolean | la primera promo destacada encabeza la página pública |
| vigencia | varchar(120) | texto visible, p.ej. "Vigente hasta el 31 de diciembre" |
| categoria | enum('desayunos','almuerzos','familia') | filtros de `FILTROS_PROMOCIONES` |
| condiciones | json | array de strings ("No acumulable…", "Válido con reserva previa") |
| codigo | varchar(30) | p.ej. `CEBICHE2X1` |
| precio_texto | varchar(30) | lo que se pinta como precio, p.ej. "2×1" |
| foto | varchar(255) nullable | upload |
| activa | boolean | inactiva no aparece en el sitio público |
| timestamps, softDeletes | | |

### 3.9 Horarios y turnos (nueva decisión: editables desde el panel)

Dos tablas que el panel administra y que la disponibilidad, los filtros por turno y el
estado del local leen en tiempo real. Origen del contenido sembrado: `HORARIOS` y
`TURNOS_RESERVA` de `src/lib/horario.js` / `src/lib/reservas.js`.

**horarios (franja de atención)**

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| nombre | varchar(60) | "Desayunos", "Almuerzos" |
| dias | json | días 0–6 (domingo = 0) en que se atiende; ≥ 1 |
| abre | time | |
| cierra | time | > `abre` |
| activa | boolean | inactiva no cuenta para el estado del local |
| timestamps | | |

**turnos (turno de reserva)**

| Columna | Tipo | Reglas |
|---|---|---|
| id | varchar(20) PK | "almuerzo", "cena" |
| nombre | varchar(60) | "Almuerzo", "Cena" |
| slots | json | horas `HH:MM` únicas y ordenadas; ≥ 1 |
| activa | boolean | inactiva no se ofrece al reservar |
| timestamps | | |

Semillas: franjas *desayunos* (martes–domingo, 8:00–12:00) y *almuerzos* (lunes–domingo,
12:00–17:00); turnos *almuerzo* (12:00, 12:30, 13:00, 13:30) y *cena*
(19:00…21:30 cada 30 min).

### 3.10 Tablas de infraestructura

- `personal_access_tokens` (Sanctum) y `password_reset_tokens` (recuperación).
- `jobs`/`failed_jobs` (emails en cola).

---

## 4. Autenticación (Sanctum, tokens)

Endpoints bajo `/api/v1/auth`:

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| POST | `/auth/register` | Crea cuenta `cliente` y devuelve token | pública |
| POST | `/auth/login` | Valida credenciales y devuelve `{ token, user }` | pública |
| POST | `/auth/password/email` | Envía el enlace de recuperación (respuesta genérica) | pública |
| POST | `/auth/password/reset` | Restablece con `{ token, correo, password, password_confirmation }` | pública |
| POST | `/auth/logout` | Revoca el token actual | Bearer |
| GET | `/auth/me` | Datos de la sesión (`{ correo, nombre, rol }`, como `sesionDeCuenta`) | Bearer |

Reglas:

- A1. Registro valida nombre (≥3), correo (formato, mensajes de `validarCorreo`), clave
  (≥8) y teléfono (9 dígitos). Rol siempre `cliente`.
- A2. Login rechaza: correo inexistente ("No encontramos una cuenta…"), clave que no
  coincida, cuenta con `activa=false` ("Tu cuenta está desactivada…"). Respuestas 401 con
  `message` y, cuando aplique, `campo` para que el SPA marque el input correcto.
- A3. `me` nunca expone `password`; el Resource devuelve exactamente
  `{ id, nombre, correo, telefono, rol, activa, creado_en }`.
- A4. Rate limiting: 5/min en `register`, `login` y `password/email`.
- A5. Recuperación de contraseña (nueva decisión): broker de Laravel
  (`Password::sendResetLink`, token con expiración de 60 min). `password/email` responde
  **siempre igual** (200 "Si el correo existe, te enviamos un enlace") para no enumerar
  cuentas; el email apunta a `FRONTEND_URL/reset-password?token=…&correo=…` y el SPA
  completa el flujo con `password/reset`. La nueva clave cumple A1 (≥ 8) y restablecer
  **revoca todos los tokens activos** del usuario.

---

## 5. Módulos funcionales y endpoints

Prefijo común: `/api/v1`. Respuestas JSON detalladas en §7. Paginación estilo frontend:
`{ data, meta: { pagina, por_pagina, total, total_paginas, desde, hasta } }`
(`paginar` en `panel.js`, 6 por página en reservas y usuarios).

### 5.1 Carta pública y gestión (paridad)

**Público** (sin auth): `GET /carta` → categorías **activas** con sus platos y
`disponible`, más el resumen del encabezado
(`contarCarta`: `{ categorias, platos, disponibles, agotados }`).

**Panel** (admin):

| Método | Ruta | Notas |
|---|---|---|
| GET | `/panel/carta` | incluye inactivas/agotados |
| POST | `/panel/categorias` | |
| PUT/PATCH | `/panel/categorias/{id}` | activar/desactivar incluido |
| DELETE | `/panel/categorias/{id}` | soft-delete; se rechaza si tiene platos activos |
| POST | `/panel/platos` | valida `validarPlato` |
| PUT/PATCH | `/panel/platos/{id}` | incluir toggle `disponible` |
| DELETE | `/panel/platos/{id}` | soft-delete |
| POST | `/panel/platos/{id}/foto` | multipart (extra §8.3) |

Validación de plato (mensajes heredados de `carta.js`): nombre no vacío y ≥3; precio > 0;
categoría existente.

### 5.2 Mesas y disponibilidad (paridad)

**Constantes de negocio** (sembrar/validar contra `reservas.js` y `horario.js`):

- Turnos y slots provienen de la tabla `turnos` (§3.9), sembrada con los valores actuales
  del frontend: **almuerzo** `12:00, 12:30, 13:00, 13:30`; **cena** `19:00, 19:30, 20:00,
  20:30, 21:00, 21:30` (`TURNOS_RESERVA`). Crear o editar una reserva valida la hora
  contra los slots **vigentes** de la tabla, no contra constantes.
- Duración de ocupación por reserva: 90 minutos.
- Estados de slot para un grupo: `disponible` (>2 mesas libres), `alta-demanda` (1–2),
  `ocupado` (0) (`estadoSlot`); horas pico informativas: 13:00, 19:30, 20:00, 20:30.
- **La simulación determinista de ocupación del prototipo NO se replica**: en el backend
  la disponibilidad se calcula solo con reservas reales (`mesasLibres` sin
  `mesaSimuladaOcupada`).

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| GET | `/mesas` | lista pública: `id, numero, zona, capacidad` (solo activas) | pública |
| GET | `/disponibilidad?fecha=YYYY-MM-DD&personas=N` | por cada slot: `estado`, `mesas_libres`, `resumen` ("Quedan N mesas"); rechaza fecha pasada | pública |
| GET | `/disponibilidad/mesas?fecha&hora&personas` | mesas con capacidad ≥ N y sin conflicto (para elegir mesa) | pública |
| GET | `/panel/mesas` | todas + `activa` + total de asientos activos (`asientosActivos`) | admin |
| POST | `/panel/mesas` | numero auto = mayor+1; valida `validarMesa` | admin |
| PUT | `/panel/mesas/{id}` | cambiar zona/capacidad | admin |
| PATCH | `/panel/mesas/{id}/activar` · `/desactivar` | inactiva no recibe reservas nuevas | admin |
| DELETE | `/panel/mesas/{id}` | soft-delete; prohibido si tiene reservas futuras activas | admin |

### 5.3 Reservas — flujo del cliente (paridad)

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/reservas` | crea la reserva del usuario autenticado |
| GET | `/mis-reservas` | `{ proximas, pasadas }` según `reservasProximas`/`reservasPasadas` (sin canceladas) |
| PATCH | `/reservas/{folio}/cancelar` | cancela **su** reserva |
| GET | `/reservas/{folio}` | detalle (solo dueño o admin) |

Cuerpo de creación (flujo Disponibilidad → Preorden → Datos → Confirmación):

```json
{
  "fecha": "2025-11-20",
  "hora": "20:00",
  "personas": 4,
  "mesa_id": 5,
  "notas": "Mesa junto a la ventana si es posible.",
  "platos": [ { "plato_id": 3, "cantidad": 2 } ]
}
```

Reglas (R#):

- RC1. `fecha` ≥ hoy; `hora` ∈ slots de un turno; `personas` 1–6; `mesa_id` activa con
  `capacidad ≥ personas` y **sin conflicto de 90 min** (si no, 409 con mensaje "Esa mesa ya
  está ocupada en esa franja" y la lista de mesas válidas).
- RC2. `mesa_id` puede omitirse ⇒ reserva en **lista de espera** (`estado=lista-espera`,
  no ocupa mesa).
- RC3. Folio generado por el backend (`HRK-<máximo+1>`); el cliente nunca lo envía.
- RC4. Preorden: snapshot de nombre/precio en `reserva_platos`; `subtotal` calculado por
  el backend; platos deben existir y estar `disponible`.
- RC5. Al crear: entrada en `reserva_historial` (tipo `creacion`) + email (extra §8.1).
- RC6. **Cancelar:** solo el dueño, y solo hasta **2 horas antes** del inicio
  (`puedeCancelar`); fuera de ventana ⇒ 409 "Solo puedes cancelar hasta 2 horas antes".
  Crea entrada de historial y email.
- RC7. Respuesta de creación con todo lo que muestra la Confirmación: folio, fecha larga,
  hora, turno, mesa `{numero, zona}`, tolerancia 15 min (texto informativo
  `TOLERANCIA_MINUTOS`).

### 5.4 Reservas — panel admin (paridad)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/panel/reservas?fecha&turno&estado&texto&page` | filtros + paginación 6 |
| POST | `/panel/reservas` | alta con mesa obligatoria (`validarReservaAdmin`) |
| GET | `/panel/reservas/{folio}` | detalle + historial + preorden |
| PUT | `/panel/reservas/{folio}` | editar fecha/hora/personas/mesa/datos |
| PATCH | `/panel/reservas/{folio}/estado` | transición de estado |
| GET | `/panel/reservas/export.csv?fecha&turno&estado&texto` | CSV con BOM (formato `csvDeReservas`) |

Reglas:

- RA1. Filtro `estado=pendiente` agrupa `por-confirmar` + `lista-espera`
  (`ESTADOS_PENDIENTES`); `texto` busca en nombre, correo, teléfono, folio, mesa y zona,
  insensible a tildes (`coincideTexto`); `turno` por slot (`turnoDeHora`).
- RA2. El pie de tabla requiere `{ reservas, personas }` del conjunto filtrado
  (`resumenReservas`): devolver en `meta.resumen` de cada listado.
- RA3. Crear/editar valida `validarReservaAdmin`: fecha ≥ hoy, hora de turno, personas
  1–6, mesa existente con capacidad suficiente y sin choque (excluyendo la propia reserva,
  como hace `mesasParaReserva` con `folio`).
- RA4. Cambio de estado: cualquier transición entre los 6 estados; rechazar cancelar una
  ya cancelada y cambiar estado a reservas pasadas no canceladas solo hacia
  `finalizada`/`no-show` (coherente con `reservaEsPasada`: las pasadas no tienen acciones).
  Cada cambio crea entrada de historial con `autor` = admin y texto del cambio, y envía
  email al cliente si el estado pasa a `confirmada` o `cancelada` (§8.1).
- RA5. `creada_por` = nombre del admin en reservas creadas por panel; las del cliente
  llevan `creada_por = null`.
- RA6. CSV idéntico al del frontend (`csvDeReservas`): columnas Folio, Fecha, Hora,
  Cliente, Teléfono, Correo, Personas, Mesa, Zona, Estado (etiqueta en español).

### 5.5 Reportes y KPIs (paridad)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/panel/reportes?fecha=YYYY-MM-DD` | métricas del día |
| GET | `/panel/reportes/rango?desde&hasta` | serie diaria para el gráfico de barras |

Métricas del día (`metricasDelDia` en `panel.js`, calcular en SQL/Service):

```json
{
  "reservas": 12,
  "comensales": 38,
  "no_show": 2,
  "porcentaje_no_show": 17,
  "reservas_ayer": 10,
  "delta": 2,
  "ocupacion": { "almuerzo": 64, "cena": 81, "promedio": 73 }
}
```

- Contables excluye `lista-espera`; `no_show` cuenta `cancelada + no-show`; `comensales`
  suma `personas` de las que no son cancelada/no-show; `delta` contra el día anterior.
- Ocupación por turno = Σ mesas ocupadas por franja / (mesas activas × slots del turno),
  como `ocupacionTurno`/`ocupacionFranja`, pero **solo con reservas reales**.
- El rango devuelve por día: reservas, comensales y ocupación promedio (alimento del
  `BarChart`).

### 5.6 Calendario (paridad)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/panel/calendario?anio=2025&mes=10` | 42 celdas (lunes–domingo, siempre 42, `calendarioDelMes`) |
| GET | `/panel/calendario/semana?fecha=YYYY-MM-DD` | 7 días (`calendarioSemana`) |

Cada celda: `{ fecha, dia, del_mes, pasado, es_hoy, reservas, personas, ocupacion:{almuerzo,cena,promedio}, ocupadas_pico, mesas_activas }` — todo calculado en el backend a partir de reservas reales y mesas activas.

### 5.7 Usuarios — panel (paridad)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/panel/usuarios?texto&rol&estado&page` | filtros texto/rol/estado + paginación 6 |
| GET | `/panel/usuarios/resumen` | `{ total, administradores, inactivas }` (`resumenUsuarios`) |
| POST | `/panel/usuarios` | crear cliente o admin |
| PUT | `/panel/usuarios/{id}` | editar nombre/teléfono/rol |
| PATCH | `/panel/usuarios/{id}/desactivar` · `/activar` | reglas R1/R2 (409 con `motivo`) |
| PATCH | `/panel/usuarios/{id}/rol` | reglas R2 |
| GET | `/panel/usuarios/export.csv` | columnas Alta, Usuario, Correo, Rol, Estado (`csvDeUsuarios`) |

Orden por fecha de alta descendente (`filtrarUsuarios`). Nunca devolver `password`.

### 5.8 Promociones (extra: CRUD + pública)

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| GET | `/promociones?categoria` | solo activas; filtro de `FILTROS_PROMOCIONES` | pública |
| GET | `/panel/promociones` | todas | admin |
| POST | `/panel/promociones` | | admin |
| PUT/PATCH | `/panel/promociones/{id}` | incluir activar/desactivar y `destacada` | admin |
| DELETE | `/panel/promociones/{id}` | soft-delete | admin |
| POST | `/panel/promociones/{id}/foto` | multipart (§8.3) | admin |

Semilla: las 6 promociones de `src/content/sitio.js`.

### 5.9 Horarios, turnos y contenido institucional

**Público:**

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| GET | `/horarios` | franjas de atención activas + estado del local (lógica de `estadoLocal`: abierto/cierra/próxima apertura) | pública |
| GET | `/turnos` | turnos de reserva activos con sus slots | pública |

**Panel (admin) — edición de horarios de disponibilidad (§3.9):**

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/panel/horarios` · `/panel/turnos` | todos, incluidos inactivos |
| POST | `/panel/horarios` · `/panel/turnos` | crear franja/turno |
| PUT/PATCH | `/panel/horarios/{id}` · `/panel/turnos/{id}` | editar y activar/desactivar |
| DELETE | `/panel/horarios/{id}` · `/panel/turnos/{id}` | prohibido si existen reservas futuras de ese turno |

Reglas:

- RH1. Horario: nombre obligatorio, `abre < cierra`, `dias` con al menos un día.
- RH2. Turno: `slots` en formato `HH:MM`, únicos y ordenados, ≥ 1.
- RH3. Todo lo que depende de turnos/slots — disponibilidad (§5.2), validación de hora
  (RC1), filtros por turno (RA1), ocupación de reportes (§5.5) y calendario (§5.6) — lee
  las tablas vigentes, nunca constantes.
- RH4. `GET /horarios` (o un endpoint `GET /estado-local`) replica la lógica de
  `estadoLocal` de `src/lib/horario.js` sobre las franjas guardadas, para el badge
  "Abierto ahora · cierra a las…" del sitio.

Hero slides, especialidades, reseñas, historia, equipo y textos de contacto **siguen en el
frontend** (no-goal de esta entrega, §10).

---

## 6. Validación y mensajes de error (contrato con el SPA)

El frontend muestra mensajes por campo; el backend debe devolverlos con la misma forma que
espera el SPA hoy (los textos están en `src/lib/*.js` y son la referencia de tono, pueden
adaptarse mientras sean igual de específicos).

Formato de respuesta de error (estándar Laravel):

```json
// 422
{ "message": "Los datos enviados no son válidos", "errors": { "correo": ["Revisa el correo: falta el @"] } }
// 409 (regla de negocio, p.ej. conflicto de mesa o reglas R1/R2)
{ "message": "Esa mesa ya está ocupada en esa franja" }
// 401 / 403
{ "message": "Tu sesión expiró. Inicia sesión de nuevo." }
```

Códigos HTTP: 200/201 éxito · 401 sin token · 403 rol insuficiente o recurso ajeno ·
404 no existe · 409 regla de negocio (conflicto mesa, cancelación fuera de ventana, R1/R2)
· 422 validación de forma · 429 rate limit.

**Idioma:** todos los `message` visibles en español (`lang: es` en las validaciones de
Laravel; usar `lang/es` oficial o traducciones propias).

---

## 7. Formato JSON y versionado

- Prefijo `/api/v1` en todas las rutas (`Route::prefix('api/v1')`).
- Recursos con `API Resource`: nunca exponer columnas de más (§4 A3).
- Fechas: ISO-8601; `fecha` como `YYYY-MM-DD` y `hora` como `HH:MM` (textual, como hoy
  usa el frontend). Zona horaria de la app: `America/Lima`.
- Moneda: `precio`/`subtotal` como número decimal (el SPA formatea "S/ 45.50").
- Enumeraciones en **string en español/kebab** exactos: estados de reserva
  (`confirmada`, `por-confirmar`, `lista-espera`, `no-show`, `finalizada`, `cancelada`),
  zonas (`Salón`, `Terraza`), roles (`admin`, `cliente`), turnos (`almuerzo`, `cena`) —
  idénticos a los del frontend para no romper etiquetas y colores (`EstadoReserva.jsx`).
- Colecciones paginadas: `data` + `meta` con `pagina, total_paginas, total, desde, hasta`
  (§5) para alimentar el componente `Paginacion.jsx` sin conversión.

---

## 8. Extras aprobados

### 8.1 Notificaciones por email (Mailables en cola)

| Evento | Destinatario | Contenido |
|---|---|---|
| Reserva creada (cliente o panel) | cliente + correo del restaurante | folio, fecha, hora, personas, mesa, preorden y subtotal |
| Reserva cancelada | cliente | folio, motivo/ventana |
| Estado pasa a `confirmada` o `cancelada` desde el panel | cliente | folio, nuevo estado, autor |
| Recuperación de contraseña (§4) | cliente | enlace con token (expira en 60 min) hacia el SPA |

Requisitos: cola `database` (o Redis si existe); plantillas Blade de correo (el "backend"
sí usa Blade para Mailables); envío asíncrono (nunca bloquear la respuesta HTTP);
configuración SMTP vía `.env` con driver `log` en local para probar.

### 8.2 Panel en vivo (decisión: polling, sin WebSockets)

Reverb no es desplegable en el entorno elegido, así que el panel se mantiene fresco con
**polling**, sin Echo, sin Pusher y sin servidor WebSocket que mantener:

- Endpoint: `GET /panel/cambios?desde=<ISO8601>` (admin) →
  `{ cambios: [ { tipo, id, folio, actualizado_en } ] }` con reservas y mesas tocadas
  después del timestamp; lista vacía si no hubo cambios.
- El SPA consulta cada **30 segundos** mientras la pestaña del panel está visible
  (`visibilitychange`) y refresca listado/calendario/KPIs **solo** cuando la respuesta
  trae cambios.
- Los listados exponen `actualizado_en` (`updated_at`) por elemento para facilitar
  diffs simples y evitar refetch completos.

### 8.3 Upload de imágenes (platos y promociones)

- `POST /panel/platos/{id}/foto` y `POST /panel/promociones/{id}/foto`, `multipart/form-data`,
  campo `foto`; validar `mimes:jpg,jpeg,png,webp` y `max:4096` (KB); guardar en disco
  `public` con nombre hash; responder `{ foto: "storage/…jpg" }`; URL completa via
  `APP_URL`.
- Si un plato no tiene foto, responder `foto: null` y que el SPA siga usando su
  placeholder (`Photo` ya lo soporta).
- Opcional: redimensionado con `intervention/image` para limitar el ancho a 1200 px;
  deseable, no bloqueante.

### 8.4 Seeders de demostración

El frontend tiene datos demo (`panelDemo.js`, `content/*.js`); el backend debe sembrar:

- 1 admin (`CUENTA_ADMIN_DEMO` de `src/content/panel.js`) y 1 cliente demo
  (`CUENTA_DEMO` de `src/content/cliente.js`), con credenciales en el README del backend.
- 12 mesas, carta completa (categorías + platos de `sitio.js`), 6 promociones, 2 franjas
  de horario y 2 turnos de reserva (§3.9).
- Reservas demo de `panelDemo.js` (12 reservas relativas a hoy, folios HRK-9001…,
  `demo=true`) **solo en entornos dev/local** (seeder separado `DemoSeeder`).

---

## 9. Integración con el frontend React (plan)

1. **Capa de servicios nueva:** crear `src/services/api.js` con `fetch` + `Authorization:
   Bearer <token>`, manejo de 401 → logout, y un módulo por dominio
   (`authApi`, `cartaApi`, `reservasApi`, `panelApi`, …).
2. **Config:** `VITE_API_URL` en `.env` del frontend; en dev, proxy de Vite
   (`server.proxy['/api'] → http://localhost:8000`) para evitar CORS; en producción,
   CORS del backend con `FRONTEND_URL` en `cors.php` (`supports_credentials = false`).
3. **Token:** guardar en memoria + `localStorage` (`elhuarike.token.v1`), enviar en cada
   request; `/auth/me` al arrancar para restaurar sesión.
4. **Proveedores:** reemplazar la persistencia de `SiteProvider/CuentaProvider/
   MesasProvider/CartaProvider/ReservasProvider` (hoy `src/services/storage.js`) por
   estado servidor: cargar con `GET`, mutar vía API y refrescar desde las respuestas. Las
   funciones puras de `src/lib/*.js` (formato, validación de UI, orden) se conservan; solo
   se elimina la simulación (`mesaSimuladaOcupada`, `presionDeSlot`).
5. **Errores de formularios:** mapear `errors` de 422 al mismo shape que usan hoy los
   componentes (`{ campo: mensaje }`), manteniendo el foco en el primer error.
6. **Panel en vivo (polling):** hook `useCambiosPanel` que consulta
   `GET /panel/cambios?desde=` cada 30 s con la pestaña visible y refresca los providers
   afectados solo si hay cambios (§8.2).
7. **Recuperación de contraseña:** vista `ResetPassword` que recibe `token` y `correo`
   por query y llama a los endpoints de §4.
8. **Tests del frontend:** los tests actuales que simulan `localStorage` se adaptan con
   mocks de `fetch`; no cambiar componentes visibles.

---

## 10. No-goals de v1

- Pagos online, delivery, multi-restaurante/sucursales.
- CRUD del contenido institucional (hero, equipo, reseñas): queda en el frontend.
- Autenticación social / 2FA.
- WebSockets/broadcasting (Reverb/Echo): el panel usa polling (§8.2).
- Replicar la simulación determinista de ocupación del prototipo (§5.2).

---

## 11. Requisitos no funcionales

- **Entorno:** PHP 8.3+, Laravel 12, MySQL 8, Composer; app con `APP_ENV`, `APP_DEBUG=false`
  en producción; zona `America/Lima`; idioma `es`.
- **Seguridad:** validación server-side total (Form Requests; nunca confiar en el SPA),
  hashing bcrypt, Policies en todos los endpoints `/panel/*` y sobre recursos propios,
  rate limiting (`login/register` 5/min; APIs públicas 60/min), `password` nunca en
  respuestas, IDs/folios no enumerables donde exponga datos personales (usar `folio`/`uuid`
  en rutas públicas de detalle).
- **Testing:** PHPUnit/Pest con feature tests por endpoint (auth, ciclo de reserva,
  conflicto de 90 min, ventana de cancelación, R1/R2, KPIs, CSV, promociones, upload);
  objetivo ≥ 80 % en servicios y controllers. Seeders independientes por suite.
- **Documentación de API:** colección Postman o OpenAPI generada (recomendado
  `scribe`/`l5-swagger`) como parte de la entrega, con ejemplos por endpoint.
- **README del backend:** requisitos, `composer install`, migraciones, seeders, usuario
  demo, cómo levantar MySQL, cómo apuntar el SPA (URL y CORS), y cómo correr los tests —
  mismo estándar que exige la rúbrica del curso para el frontend.

---

## 12. Plan de implementación por fases

Cada fase termina con feature tests verdes y merge al branch principal del backend.

| Fase | Contenido | Entregable verificable |
|---|---|---|
| 0 | Bootstrap: proyecto Laravel 12 `--api`, Sanctum, MySQL, migraciones + seeders §8.4, `GET /up` | seed desde cero reproduce la carta y mesas |
| 1 | Auth (§4) + usuarios panel (§5.7) | login/registro/mis datos; R1/R2 con tests |
| 2 | Carta/categorías/platos (§5.1) + estado local | SPA puede pintar carta pública |
| 3 | Mesas + disponibilidad (§5.2) + reservas cliente (§5.3) | flujo completo de reserva con folio |
| 4 | Reservas panel (§5.4) + reportes (§5.5) + calendario (§5.6) + CSV | panel 100 % sobre API |
| 5 | Promociones (§5.8) + horarios/turnos editables (§5.9) + upload fotos (§8.3) | CRUD con imágenes; disponibilidad refleja turnos editados |
| 6 | Emails (§8.1) + recuperación de contraseña (§4) + polling del panel (§8.2) | emails en cola; panel refresca cada 30 s |
| 7 | Endurecimiento: rate limits, OpenAPI/Postman, README, revisión de seguridad | documentación completa |

Estimación orientativa para 3 integrantes: fases 0–4 son el núcleo (≈ 3–4 semanas), 5–7 los
extras (≈ 2 semanas).

---

## 13. Criterios de aceptación (checklist final)

- [ ] Un cliente puede registrarse, reservar con preorden, ver y cancelar (2 h) sus reservas.
- [ ] El panel lista/filtra/pagina reservas y usuarios igual que hoy (6 por página, filtros
      `pendiente` y búsqueda con tildes).
- [ ] Dos reservas a menos de 90 min en la misma mesa son imposibles (test con concurrencia).
- [ ] Lista de espera no ocupa mesa; mesa inactiva no recibe reservas pero conserva historial.
- [ ] Estados de reserva con las 6 etiquetas exactas; historial acumulativo por cambio.
- [ ] Reportes del día con `delta`, ocupación por turno y CSV descargable con BOM.
- [ ] Calendario mensual de 42 celdas y semana con ocupación.
- [ ] No se puede dejar el sistema sin admins activos ni autodesactivarse (R1/R2).
- [ ] Emails de creación/cancelación/cambio de estado en cola y con plantilla legible.
- [ ] Promociones gestionadas por panel y visibles en el sitio público.
- [ ] Upload de fotos con validación de tipo/tamaño y `foto: null` soportado.
- [ ] El polling cada 30 s refresca el panel sin recargar (§8.2).
- [ ] Horarios y turnos editables desde el panel: disponibilidad, filtros, ocupación y
      estado del local reflejan los cambios sin reseed.
- [ ] Recuperación de contraseña de punta a punta (token con expiración, respuesta
      genérica sin enumerar cuentas, revoca tokens activos).
- [ ] Feature tests ≥ 80 % en capas de negocio; documentación API y README completos.
