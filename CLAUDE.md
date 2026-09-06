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
`php -l` on every changed file, then walking the affected pages/POST actions.
When a MySQL/MariaDB is reachable (dev typically has one on `127.0.0.1:3306`),
the "manual walk" is best replaced by throwaway assertion scripts:
`php -r 'require "Bootstrap/init.php"; …'` or a standalone script that boots
`Bootstrap/init.php`, exercises the repo/service against the real DB, and
`assert()`s outcomes (run with `php -d zend.assertions=1 -d assert.exception=1`).
Views are smoke-tested the same way: `view('pages/foo', [...minimal vars...])`
and assert the output contains what it should. Run migrations idempotently with
`php -r 'require "Bootstrap/init.php"; require "Database/Migrations/2024_01_01_000000_create_schema.php"; \Database\Migrations\up();'` (safe to repeat; unlike `install.php` it does not wipe data).

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

**Adding a POST action**: new `case` in `ActionsController::postAction()` — do
**not** add `check_csrf()`, it is already called once at the top of `postAction()`
before the switch. Validate inline / via a service, write via a repository, end
with `$this->redirect(...)`. Deletes go through `ActionsController::getAction()`
(GET, `?action=delete_xxx`) and are deliberately **not** CSRF-protected (house
pattern — `delete_bacenta`, `delete_evenement`). `$this->requireUser()` /
`$this->deny()` / `$this->requireAdmin()` are **private** to `ActionsController`
— a standalone page controller cannot call them; guard inline
(`if (!current_user()) $this->redirect('index.php', ['page' => 'apropos']);`) or
delegate to a `render_*_page()` global in `app/Compat/`. The base `Controller`
only exposes `render()`, `page()`, `redirect()` (the last is `: never`, so it
halts).

**Wiring a service/repo into a view**: use the `_repo()` accessor in
`app/Compat/data.php` — `_repo(Foo::class)` returns a per-request cached
instance. New globals follow
`function foo_service(): \App\Services\Foo { return _repo(\App\Services\Foo::class); }`
(see `attendance_service`, `calendrier_service`, `rapport_jour_service`,
`classe_service`).

**Nav is role-branched** (`Views/layouts/layout.php`): `NAV_ORDER` is iterated
**only in the admin branch**. `berger`/`responsable` scopes get hardcoded links.
To expose a new page to non-admin managers, add a *hoist* block after the whole
`if/elseif/else` chain, guarded `if ($user && !$isAdmin && auth_can_xxx()) { $navLis[] = …; }`
(pattern used for `calendrier`/`anniversaires`, `rapports`, `classes`). A new
page also needs `SECTION_LABELS` + `SECTION_ICONS` + `NAV_ORDER` entries in
`Config/constants.php`.

**Adding a table**: extend `Database/Migrations/2024_01_01_000000_create_schema.php`.
`up()` is built from an initial `$schema` array (blocks 1–5) followed by
**numbered appended blocks** (`/* ---- N. MODULE — … */`, currently up to 13),
each idempotent: `CREATE TABLE IF NOT EXISTS`, or `ALTER TABLE` guarded by
`column_exists($pdo, $t, $c)` / `index_exists($pdo, $t, $i)` (both defined in the
file). Add your table to the `$tables` array in `down()`. Add a dedicated
Repository. **Default rows that must survive re-migration** (e.g. the 7 class
cursus) are seeded *inside `up()`*, guarded by `SELECT COUNT(*) FROM t = 0` —
**not** in `DatabaseSeeder::seed()`, which `TRUNCATE`s a hard-coded table list
(stopping at pre-M1 tables — it never touches `evenements`, `anniversaires`,
`rapports_jour`, `classes`, `classe_inscrits`) and is dev-only. Production runs
only `\Database\Migrations\up()` (non-destructive); `install.php` calls `down()`
first and wipes everything.

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

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
