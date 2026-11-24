# Huarike API v1 — Full Laravel 12 REST API for El Huarike

Stack: Laravel 12 + MySQL 8 + Sanctum bearer tokens + PHPUnit. Source of truth: `docs/backend-laravel-requisitos.md` (sections cited as §N). Delivery: one branch per phase (`feat/fase-N-*`), one PR per branch into `main`.

## Specs

S1. API-only Laravel 12 app: every route under `/api/v1` in `routes/api.php`; no Blade views except mailables; layers `Route → FormRequest → Controller → Service → Model → ApiResource` with Policies for role/ownership. (§1, §11)

S2. Auth by Sanctum bearer tokens. Endpoints `POST /auth/register`, `POST /auth/login`, `POST /auth/password/email`, `POST /auth/password/reset`, `POST /auth/logout`, `GET /auth/me`. `me` returns exactly `{ id, nombre, correo, telefono, rol, activa, creado_en }`. Login failures 401 with `message` (+`campo` when applicable): "No encontramos una cuenta…", clave incorrecta, "Tu cuenta está desactivada…". Rate limit 5/min on register, login, password/email. (§4)

S3. Two roles only, column `rol` enum('admin','cliente'), default `cliente`. R1: an admin cannot deactivate its own account. R2: rejecting deactivation/role removal when it is the last active admin, HTTP 409 with `motivo`. (§2)

S4. Data model exactly as §3: `users`, `mesas` (softDeletes), `categorias`, `platos` (softDeletes), `reservas`, `reserva_platos`, `reserva_historial`, `promociones` (softDeletes), `horarios`, `turnos`. UTF8MB4/InnoDB, decimal(8,2) precios, enum strings identical to frontend: `confirmada, por-confirmar, lista-espera, no-show, finalizada, cancelada`; zonas `Salón|Terraza`; roles `admin|cliente`.

S5. Overlap rule: two reservations of the same `mesa_id` conflict when their start times are less than 90 minutes apart on the same `fecha`, excluding states `cancelada` and `lista-espera`. Enforced in a Service with `lockForUpdate`, never as a SQL unique index. (§3.5)

S6. Public catalogue: `GET /carta` returns active categories with dishes plus header summary `{ categorias, platos, disponibles, agotados }`. Admin CRUD `GET/POST/PUT/PATCH/DELETE /panel/categorias`, `/panel/platos`, plus `POST /panel/platos/{id}/foto`. Plato validation: nombre non-empty ≥3, precio > 0, existing categoria; mensajes from `carta.js`. Category delete rejected when it has active dishes. (§5.1)

S7. Tables and availability: `GET /mesas` (active only: `id, numero, zona, capacidad`), `GET /disponibilidad?fecha&personas` (per slot `estado` = `disponible|alta-demanda|ocupado`, `mesas_libres`, `resumen` "Quedan N mesas"), `GET /disponibilidad/mesas?fecha&hora&personas`, admin CRUD `/panel/mesas` with `numero` = max+1, activate/deactivate, delete forbidden with future active reservations. Availability reads `turnos`/`horarios` tables, never constants. (§5.2, §3.9)

S8. Client reservations: `POST /reservas` (body `fecha, hora, personas, mesa_id?, notas?, platos[]`), `GET /mis-reservas` → `{ proximas, pasadas }` without cancelled, `PATCH /reservas/{folio}/cancelar`, `GET /reservas/{folio}` (owner or admin). Rules RC1-RC7: fecha ≥ today, hora in an active turno slot, personas 1-6, `mesa_id` active with `capacidad ≥ personas` and no conflict (409 "Esa mesa ya está ocupada en esa franja" + valid tables), omitting `mesa_id` creates `lista-espera`, folio `HRK-<max+1>` server-side, preorder snapshot of nombre/precio with server-computed `subtotal`, history entry `creacion` on create, cancel only up to 2 hours before start (409 "Solo puedes cancelar hasta 2 horas antes"), creation response includes folio, long date, hora, turno, mesa `{numero, zona}`, tolerancia 15 min. (§5.3)

S9. Admin reservations: `GET /panel/reservas?fecha&turno&estado&texto&page` (6 per page, `estado=pendiente` groups `por-confirmar`+`lista-espera`, `texto` searches nombre/correo/telefono/folio/mesa/zona accent-insensitive, `meta.resumen` = `{ reservas, personas }`), `POST /panel/reservas` (mesa required), `GET /panel/reservas/{folio}` (detail + historial + preorder), `PUT /panel/reservas/{folio}`, `PATCH /panel/reservas/{folio}/estado`, `GET /panel/reservas/export.csv` (BOM, columns Folio, Fecha, Hora, Cliente, Teléfono, Correo, Personas, Mesa, Zona, Estado). State change appends history with admin `autor`; past non-cancelled reservations accept only `finalizada`/`no-show`; cancelling a cancelled one is rejected. (§5.4)

S10. KPIs and calendar: `GET /panel/reportes?fecha` returns `{ reservas, comensales, no_show, porcentaje_no_show, reservas_ayer, delta, ocupacion:{almuerzo,cena,promedio} }` excluding `lista-espera` from counts, `no_show` = cancelada+no-show, `delta` vs previous day; `GET /panel/reportes/rango?desde&hasta` daily series; `GET /panel/calendario?anio&mes` 42 cells and `GET /panel/calendario/semana?fecha` 7 days with `{ fecha, dia, del_mes, pasado, es_hoy, reservas, personas, ocupacion, ocupadas_pico, mesas_activas }`. (§5.5, §5.6)

S11. Panel users: `GET /panel/usuarios?texto&rol&estado&page` (6 per page, newest first), `GET /panel/usuarios/resumen` → `{ total, administradores, inactivas }`, `POST /panel/usuarios`, `PUT /panel/usuarios/{id}`, `PATCH /panel/usuarios/{id}/desactivar|activar`, `PATCH /panel/usuarios/{id}/rol`, `GET /panel/usuarios/export.csv` (Alta, Usuario, Correo, Rol, Estado). `password` never returned. (§5.7)

S12. Promotions: public `GET /promociones?categoria` (active only, categories `desayunos|almuerzos|familia`), admin CRUD `/panel/promociones` + `POST /panel/promociones/{id}/foto`. Fields per §3.8 including slug, destacada, vigencia, condiciones[], codigo, precio_texto, foto, activa. (§5.8)

S13. Horarios/turnos: public `GET /horarios` (active franjas + local state open/closes/next opening) and `GET /turnos`; admin CRUD `/panel/horarios` and `/panel/turnos` with delete forbidden when future reservations use that turno. RH1: nombre required, `abre < cierra`, `dias` ≥ 1 day (0-6, Sunday 0). RH2: `slots` `HH:MM`, unique, sorted, ≥ 1. Everything turn-dependent reads these tables. (§5.9)

S14. Error contract: 422 `{ message: "Los datos enviados no son válidos", errors: { campo: [mensaje] } }`, 409 `{ message }` for business rules, 401/403 Spanish `message`, 404, 429. All visible messages in Spanish (`lang/es`). HTTP codes per §6.

S15. JSON format: `/api/v1` prefix, API Resources that never over-expose columns, ISO-8601 dates with `fecha` `YYYY-MM-DD` and `hora` `HH:MM` textual, timezone `America/Lima`, prices as decimals, paginated collections `{ data, meta: { pagina, por_pagina, total, total_paginas, desde, hasta } }`, `actualizado_en` per item for polling. (§7, §8.2)

S16. Emails queued (`database` queue): reservation created (client + restaurant), cancelled, state changed to confirmada/cancelada, password reset link to `FRONTEND_URL/reset-password?token=&correo=` expiring in 60 min. Reset revokes all user tokens. `password/email` always answers the same 200 message. (§4 A5, §8.1)

S17. Panel polling: `GET /panel/cambios?desde=<ISO8601>` → `{ cambios: [ { tipo, id, folio, actualizado_en } ] }` for reservations and tables touched after the timestamp, empty list otherwise. (§8.2)

S18. Uploads: `POST .../foto` multipart field `foto`, `mimes:jpg,jpeg,png,webp`, `max:4096`, stored on `public` disk with hashed name, response `{ foto: "storage/…jpg" }`, `foto: null` when absent. (§8.3)

S19. Seeders: 1 admin and 1 demo client from frontend content with credentials documented in README; 12 mesas (38 seats), full menu from `sitio.js`, 6 promociones, 2 horarios, 2 turnos. Separate `DemoSeeder` with HRK-9001… reservations only for dev/local. (§8.4)

S20. Testing: PHPUnit feature tests per endpoint covering auth, reservation lifecycle, 90-minute conflict, cancellation window, R1/R2, KPIs, CSV, promotions, upload; target ≥ 80% in services and controllers. (§11)

S21. Docs: README (requirements, composer install, migrations, seeders, demo users, MySQL setup, CORS/SPA URL, how to run tests) and generated API documentation (Scribe). (§11)

S22. Non-goals: payments, delivery, multi-restaurant, institutional content CRUD, social auth/2FA, WebSockets, replicating the deterministic occupancy simulation. (§10)

## Tasks

| ID | Linked | Route | Commit |
|---|---|---|---|
| T1 | S1,S4,S15,S19,S22 | inline | feat: bootstrap API foundation, schema and seeders |
| T2 | S2,S3,S11 | inline | feat: auth and panel users with R1/R2 guards |
| T3 | S6,S13 | inline | feat: public menu and admin catalogue |
| T4 | S7,S8 | inline | feat: tables, availability and client reservations |
| T5 | S9,S10,S15 | inline | feat: panel reservations, KPIs, calendar and CSV |
| T6 | S12,S13,S18 | inline | feat: promotions, editable schedules and image uploads |
| T7 | S16,S17,S2 | inline | feat: queued emails, password reset and panel polling |
| T8 | S19,S20,S21 | inline | chore: hardening, rate limits, API docs and README |

## Log

L1. «Crea el API para la web y aplicación de reservas del restaurante "El Huarike", la documentación se encuentra en @docs/backend-laravel-requisitos.md . El prototipo del frontend se encuentra en ~/idat/examen-interfaces-2 pero no necesitas hacerle caso porque solo es un prototipo frontend con un backend simulado en localstorage, pero la documentación de ese repositorio si te puede servir. Divide tu trabajo en partes para ir desarrollando rama por rama, para revisar los commits en los PR que vayas creando. Hazme todas las preguntas necesarias. Aprovecha los skills de Laravel Boost.»

L2. Decisions (human answers): scope = all 8 phases 0-7, one branch per phase; database = MySQL 8 for dev and tests; delivery = push each branch and open a PR into `main` on origin; tests = PHPUnit (already installed).

L3. Evidence: MySQL already configured for the app (`database.default=mysql`, database `huarike_lara`, migrations applied). Connection user holds DDL privileges; created `huarike_lara_test` for the test suite, so tests run on MySQL rather than SQLite in memory (the scaffold default) because the spec demands MySQL 8 semantics and the 90-minute concurrency test exercises row locks.

L4. Environment constraint: `.env` and `.env.example` are blocked by the harness safety policy, so configuration changes that would need `.env` edits are expressed as `env()` fallbacks with safe defaults in `config/*.php` instead. Frontend prototype at `~/idat/examen-interfaces-2` is read-only evidence for seed content.

L5. Slice boundaries. The branch `feat/fase-0-bootstrap` carries four commits: the vendored Laravel Boost tooling plus this spec (bulk, ~6k lines of generated skill markdown), then the API shell/config (≈700 lines), the schema plus enums and models (≈800 lines), and the seeders with their tests (≈900 lines). The vendored skill content dominates the raw line count and is not hand-authored logic; review it as generated tooling.

L6. Doc deviations, deliberately surfaced instead of invented: the requirements document says six seeded promotions but `src/content/sitio.js` defines five, so five real promotions are seeded. Photo columns are seeded as `null` because the prototype references bundled Vite assets, and `foto: null` is the documented SPA fallback.

L7. Tooling defect and decision. Native review could not run: `~/.pi/gentle-ai/models.json`, `~/.pi/agent/agents/*.md` and `~/.pi/agent/subagents.json` all assigned reviewers the retired model id `opencode-go/deepseek-v4-flash`; the live catalog exposes `deepseek-v4.1-flash`. All three config locations were corrected (backups kept, human-authorized), but the running Pi process keeps its startup model map, so the fix only applies on a fresh start. With the human's explicit decision, receipt-driven development was disabled for this clone (`gentle-ai review mode disable --scope clone`) and delivery follows ordinary repository policy: the human reviews each PR. Lineage `review-28c3c0e1cf3d2e4c` stays unapproved and unused.

L8. F0 outcome: `php artisan migrate:fresh --seed` reproduces 12 mesas/38 seats, 8 categories, 22 dishes, 5 promotions, 2 franjas, 2 turnos and the demo accounts; `php artisan test` green (6 tests, 44 assertions) against MySQL `huarike_lara_test`. Slice commit: 492f297 (tooling), 4e66218 (API shell), ea32b2a (schema/models), e811ae0 (seeders).

L9. F1 outcome: auth by Sanctum tokens with register/login/me/logout, the password broker flow (generic answer, 60-minute token, all tokens revoked on reset) and panel user administration with R1/R2 answering 409 plus `motivo`. `php artisan test` green (28 tests, 175 assertions). Slices: 635668f (auth), ec67f18 (panel). PR #2, stacked on PR #1.

L10. F2 outcome: public `GET /carta` (active categories, header counters), `GET /horarios` with the `estadoLocal` badge and `GET /turnos`; panel `GET /panel/carta`, category CRUD with soft-delete rejected while active dishes remain, and dish CRUD with per-category unique names, price > 0, disponible toggle and soft-delete. Deviation: categorias gains `softDeletes` because §5.1 asks for soft-delete there while §3.3 lists only platos/promociones/mesas; the endpoint contract wins. `php artisan test` green (50 tests, 269 assertions). Slices: ddd4ae4 (public), f8064c0 (panel). PR #3.

L11. F3 outcome: public availability from real reservations only (10 slots across the two active turns, estado/mesas_libres/resumen/pico), panel table CRUD with auto numbering and delete blocked by future active reservations (409 `mesa-con-reservas`), and the client booking flow: server-side folio from HRK-2481, preorder snapshot with server-computed subtotal, `creacion` history entry, waitlist when no table is chosen, 409 "Esa mesa ya está ocupada en esa franja" with valid alternatives, `{proximas, pasadas}` split and the 2-hour cancellation window. Concurrency uses `lockForUpdate` on the table row and on the last reservation for the folio. `php artisan test` green (81 tests, 389 assertions). Slices: f010dd7 (availability), c036872 (panel tables), 036dc0d (bookings). PR #4.

L12. F4 outcome: panel reservation listing with date/turn/state/text filters (accent-insensitive over name, email, phone, folio, table and zone), six per page with `meta.resumen`; admin create requiring a table and recording `creada_por`; partial edit excluding the reservation itself from the conflict check; state flow with history authorship and the past-reservation restriction; CSV with BOM and the prototype columns; daily KPIs (`delta`, `porcentaje_no_show`, occupancy per turn computed as occupied tables per slot over active tables times slots), range series capped at 366 days, a 42-cell Monday-first month calendar and a 7-day week. `PreordenService` extracted so client and panel creation share the price snapshot. `php artisan test` green (102 tests, 522 assertions). Slices: c799691 (reservations), 185c1b1 (KPIs/calendar). PR #5.

L13. F5 outcome: public promotions (active only, highlighted first, category filter) and panel CRUD with de-duplicated slugs, upper-cased codes and soft-delete; multipart photo upload for dishes and promotions on the public disk with mimes/size validation, hashed names, `{foto: "storage/…"}` and `foto: null` fallback; editable franjas and turns with deletion blocked by future reservations (409 `franja-con-reservas` / `turno-con-reservas`). RH3 is covered by tests: deactivating the `cena` turn drops availability from 10 to 4 slots and adding an `18:00` slot makes new bookings at 18:00 pass. A strict `HoraValida` rule was added because `date_format:H:i` accepts `25:00`. `php artisan test` green (127 tests, 630 assertions). Slices: d6b6bbd (promotions/uploads), 9a98e77 (schedule). PR #6.

L14. F6 outcome: three queued mailables (created with restaurant copy, cancelled with author, state changed only for confirmada/cancelada) dispatched after the transaction commits, with Blade templates; and `GET /panel/cambios?desde` returning reservations and tables touched after the mark with `tipo, id, folio, numero, actualizado_en`, empty when nothing changed. `php artisan test` green (136 tests, 659 assertions). Slices: 9d06e6e (mail), 0fb4356 (polling). PR #7.

L15. F7 outcome: README with installation, MySQL setup for app and test databases, demo credentials, SPA wiring, queue worker, endpoint map, business rules and four explicit deviations; an OpenAPI 3.0 contract covering all 50 paths; panel rate limit of 120/min and destructive artisan commands blocked in production. Final verification: `migrate:fresh --seed` reproduces the catalogue, `DemoSeeder` adds HRK-9001…9012, `php artisan test` green (136 tests, 659 assertions), Pint clean, and a live smoke test over `artisan serve` answered /up 200, carta 8/22/21/1, estado del local, admin login, KPIs, availability and a booking at HRK-9013 with subtotal 90. Slice: a3f9dbe. PR #8.

L16. Known limitation: measured coverage (`php artisan test --coverage`) is unavailable because neither xdebug nor pcov is installed in this environment; the 80% target is covered by feature tests per endpoint instead, and the README states it.

L17. Delivery state: eight branches chained as stacked PRs #1→#8 into `main`. Merge in order, or retarget each PR base as the previous one lands.
