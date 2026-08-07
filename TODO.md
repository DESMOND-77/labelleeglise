# Refonte Architecturale — La Belle Église

Objectif : transformer le projet en architecture modulaire (Laravel-inspired)
100% compatible hébergement mutualisé (sans Composer, sans CLI, sans installation).

## Milestones

### ✅ Milestone 1 — Fondations (Bootstrap + Config)
- [x] Créer `Bootstrap/autoload.php` (autoloader PSR-4 léger, sans Composer)
- [x] Créer `Bootstrap/init.php` (session, config, erreurs, logging)
- [x] Créer `Bootstrap/constants.php`
- [x] Scinder `config.php` → `Config/app.php`, `Config/database.php`, `Config/auth.php`, `Config/constants.php`

### ✅ Milestone 2 — Core (app/Core)
- [x] `Database.php` (PDO singleton — issu de db.php)
- [x] `Query.php` (qall/qone/qval/qexec)
- [x] `Router.php`
- [x] `View.php` (moteur de rendu)
- [x] `Request.php`, `Response.php`, `Session.php`
- [x] `Csrf.php`, `Logger.php`, `Cache.php`, `Upload.php`, `Validator.php`

### ✅ Milestone 3 — Repositories (app/Repositories)
- [x] Centre, Member, Bacenta, Basonta, Culte, Attendance, Contribution, Berger, CMS, User
- [x] Déplacer tout le SQL de data.php dans les repositories

### ✅ Milestone 4 — Services (app/Services)
- [x] AttendanceService, ContributionService, StatisticsService, AuthenticationService, MemberService, ReportService

### ✅ Milestone 5 — Auth + Middleware + Helpers
- [x] Auth (session, RBAC, porte d'accès) sous forme de classes + wrappers
- [x] Middleware (Auth, Admin, Csrf, Gate)
- [x] Helpers (h, url, redirect, dates, uploads…)

### ✅ Milestone 6 — Controllers (app/Controllers)
- [x] AuthController, DashboardController, SectionController, BergerController, FinanceController, SettingsController, AboutController, ProfileController

### ✅ Milestone 7 — Routes + Front controller
- [x] `Routes/web.php` (Router::get/post sur les clés `page`)
- [x] Réécrire `index.php` (bootstrap + dispatch)

### ✅ Milestone 8 — Views
- [x] Déplacer templates → `Views/pages/`, `Views/layouts/`, `Views/components/`

### ✅ Milestone 9 — Database
- [x] `Database/Migrations/` (schéma d'install.php)
- [x] `Database/Seeders/` (seed.php)

### ✅ Milestone 10 — Documentation & QA
- [x] README.md, ARCHITECTURE.md, PROJECT_STRUCTURE.md, CONTRIBUTING.md
- [x] Vérifier chaque page (URLs, auth, images, uploads, SQL) — toutes les pages testées en 200, login + POST vérifiés
</content>
