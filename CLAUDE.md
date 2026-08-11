# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

"La Belle Église" — a server-side-rendered PHP church-management app. Custom
micro-framework inspired by Laravel/Clean Architecture, but **zero external
dependencies**: no Composer, no CLI/artisan, no build step, no npm. Must remain
deployable by copying the folder onto any PHP+MySQL/MariaDB shared host. Do not
introduce Composer, a package manager, Docker, or a JS build step — this is a
hard project constraint, not an oversight.

## Commands

```bash
# First-time setup: copy env template and adjust DB/SMTP/app values
cp .env.example .env

# Local dev server
php -S 127.0.0.1:8000
# then http://127.0.0.1:8000/index.php

# Install / reset schema + demo data (DESTRUCTIVE: drops and recreates all tables)
php install.php

# Lint a changed file (no test suite exists — this is the verification step used instead)
php -l path/to/File.php
```

There is no PHPUnit/test runner in this repo. Verification before committing means:
`php -l` on every changed file, then manually walking the affected pages/POST
actions (login as admin/berger/responsable, hit the relevant `?page=`, submit
the relevant form with CSRF).

Demo accounts (email / password): `admin@labelleeglise.ga` / `LBEGF` (admin),
`user@labelleeglise.ga` / `user1111` (membre), `resp.bacenta.sion@labelleeglise.ga`
/ `ESKLna` (responsable), `berger.eric.bongo@labelleeglise.ga` / `BergerEB1` (berger).

## Architecture

Request flow: `index.php` (front controller) → `Bootstrap/init.php` (autoload,
config, session, helpers) → `Routes/web.php` maps `?page=xxx` (GET) or
`action=xxx` (POST, dispatched through `ActionsController`) to a
Controller → Service → Repository → `App\Core\Query`/`Database` (PDO, prepared
statements) → View. Layers are strict:

- **Controllers** (`app/Controllers`): HTTP in, view out. No SQL, no HTML, no business logic.
- **Services** (`app/Services`): business logic only. No HTML, no direct request access.
- **Repositories** (`app/Repositories`): all SQL lives here, nowhere else.
- **Views** (`Views/pages/**`, `Views/emails/**`): HTML + simple `<?= ?>` only. No SQL, no business logic.
- **Compat** (`app/Compat`): wrapper functions exposing repository/service calls
  as globals for older views (e.g. `structure.php`, `sections.php`, `data.php`).
  New code should still prefer Controller→Service→Repository, but check here
  before assuming a helper doesn't exist — most data-fetching globals used in
  views are defined in this layer.
- **Auth** (`app/Auth`): `AuthenticationService` (login/session/current user),
  `RbacService` (scope/permissions — admin sees everything; `responsable` scope
  = bacentas returned by `BacentaRepository::forResponsible()`; `berger` scope =
  own bacenta via `bacenta_id`). Global wrappers (`login()`, `logout()`,
  `verify_credentials()`, `scope_target()`, `current_user()`, `grant_access()`)
  are in `app/Auth/compat.php` and are what controllers/views actually call.
- **Config** (`Config/*.php`): plain PHP arrays returned by `require`. Every
  environment-specific value (DB credentials, SMTP, app URL/debug/timezone…)
  is read via `env_value('KEY', $default)` (defined in `Bootstrap/env.php`,
  which parses `.env` at the project root — see `.env.example` for the full
  list of recognized keys). `.env` is gitignored; never hardcode credentials
  back into `Config/*.php`. Real server environment variables always take
  precedence over `.env` if both are set.
- **Core** (`app/Core`): the framework itself — `Router`, `Database` (PDO
  singleton), `Query` (prepared-statement helpers: `all/one/value/run/raw`, plus
  `transaction()`), `View`, `Session`, `Csrf`, `Logger`, `Cache`, `Upload`,
  `Validator`. PHPMailer is vendored (not via Composer) under `app/Core/PHPMailer`
  and PSR-4-mapped to `PHPMailer\PHPMailer` in `Bootstrap/autoload.php`; always
  send mail through `App\Services\MailService`, never touch PHPMailer directly
  from a controller/service.

**Adding a page**: route in `Routes/web.php` → controller method → service/repo
→ view in `Views/pages/` → render via `render_page($title, $content, $charts?)`.

**Adding a POST action**: new `case` in `ActionsController::postAction()` with
`check_csrf()`, validate via `Validator`/service, write via a repository, end
with `redirect()`.

**Adding a table**: extend `Database/Migrations/2024_01_01_000000_create_schema.php`
(`up()`/`down()` functions — this file *is* the migration system, executed via
raw `CREATE TABLE IF NOT EXISTS` / idempotent `ALTER TABLE` guarded by
`information_schema` checks, no versioning framework). Add a dedicated
Repository for the new table; update `Database/Seeders/DatabaseSeeder.php` if
demo data is useful. After schema changes ship, production runs only
`\Database\Migrations\up()` (non-destructive) rather than `install.php`, which
calls `down()` first and wipes all data.

### Registration / verification / activation / bacenta-assignment subsystem

A full workflow lives on top of the base `users` table (see README.md §7 for
exhaustive detail — routes, permissions table, email templates): public
registration (`?page=register`) forces `role=membre` server-side (never trust
role from the request) and creates the account as
`email_verified=0, account_status='pending'`. A `random_bytes()`-generated,
SHA-256-hashed, single-use, 24h-expiring token is emailed for verification;
verifying flips `email_verified=1` but leaves `account_status='pending'` until
an admin explicitly activates the account via `AdminMiddleware`-protected
`admin_inscriptions`/`admin_inscription` pages. `compte_actif` (the
pre-existing login gate column) stays auto-synced to `account_status='active'`
so old code paths reading it keep working. Bacenta member assignment
(`BacentaMembershipService`) never trusts a client-submitted `bacenta_id` —
it re-derives the authorized bacenta from `RbacService::myBacentaIds()` — and
re-validates every submitted member id server-side inside a single
`Query::transaction()`. `NotificationService`/`NotificationRepository` back the
topbar bell (in-app notifications to all `role=admin` users on verification).

### Conventions worth knowing before editing

- PSR-12, `declare(strict_types=1)`, typed params/returns.
- CSS is modular under `assets/css/*.css` using variables from
  `assets/css/variables.css` — no hardcoded colors/spacing, no inline
  style/script blocks in views.
- Never break existing URLs, auth, or the existing "add member" form on the
  bacenta page when adding adjacent functionality (e.g. the member-picker
  section was added *alongside* it, not replacing it) — this pattern
  (extend without touching known-working UI) is the expected default here.
- `install.php` must be removable/deletable after initial setup — don't make
  runtime code depend on it existing.
</content>
