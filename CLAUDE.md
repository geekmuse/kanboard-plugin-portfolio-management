# CLAUDE.md — Agent Context for Claude Code

## Project Summary

Kanboard plugin ("Portfolio") adding cross-project portfolio management to Kanboard. Introduces portfolios (project groups), cross-project milestones with progress tracking, and dependency visualization (D3.js graphs, blocked task lists, critical path analysis). Pure PHP plugin — no Composer dependencies. Four new DB tables, 28 JSON-RPC API methods, 5 controllers, template hooks only (no overrides).

**Target:** Kanboard >= 1.2.20 · PHP >= 7.4 · SQLite / MySQL / PostgreSQL

## Repository Layout

```
plugins/Portfolio/          ← Plugin root (deploy here inside Kanboard)
├── Plugin.php              ← Entry point: DI, routes, hooks, API registration, events
├── Schema/{Sqlite,Mysql,Postgres}.php  ← DB migrations (versioned, one fn per version)
├── Model/                  ← Business logic (6 model classes)
├── Controller/             ← Web controllers (5 classes)
├── Action/                 ← Automatic actions (event-driven)
├── Notification/           ← Custom notification types
├── Formatter/              ← Data formatters (task list, Gantt, graph JSON)
├── Filter/                 ← Custom "portfolio:" search filter
├── Helper/                 ← Template helpers
├── Validator/              ← Input validation
├── Template/               ← PHP templates (portfolio/, milestone/, dependency/, widget/)
├── Asset/{js,css}/         ← Client assets (D3.js bundled, plugin JS/CSS)
├── Locale/en_US/           ← Translation strings
├── Test/{Model,Controller}/ ← PHPUnit tests
└── docs/specs/             ← Implementation spec
```

## Key Commands

```bash
# Run all plugin tests (from Kanboard root)
./vendor/bin/phpunit plugins/Portfolio/Test/

# Run a specific test class
./vendor/bin/phpunit plugins/Portfolio/Test/Model/PortfolioModelTest.php

# Run a single test method
./vendor/bin/phpunit --filter testCreatePortfolio plugins/Portfolio/Test/Model/PortfolioModelTest.php

# PHP syntax check
find plugins/Portfolio/ -name "*.php" -exec php -l {} \;

# PSR-12 code style (PHP_CodeSniffer)
./vendor/bin/phpcs --standard=PSR12 plugins/Portfolio/

# Static analysis (PHPStan level 5)
./vendor/bin/phpstan analyse plugins/Portfolio/ --level=5

# No build step — PHP templates render directly
```

## Code Style & Conventions

### PHP

- **Namespace:** `Kanboard\Plugin\Portfolio\{Layer}` (e.g., `Kanboard\Plugin\Portfolio\Model`)
- **Naming:** Classes = `PascalCase`, methods = `camelCase`, DB columns = `snake_case`
- **PHP version:** 7.4 syntax (typed properties, arrow functions OK; no union types, enums, or named args)
- **No Composer deps.** Only Kanboard built-ins: PicoDb, Pimple, Symfony EventDispatcher, JsonRPC
- **Models** extend `Kanboard\Core\Base`; access DB via `$this->db->table(...)`
- **Controllers** extend `Kanboard\Core\Base`; access models through DI container
- **Templates:** Pure PHP (`.php`), use `$this->text->e()` for HTML escaping, `$this->url->href()` for URLs
- **Translations:** Wrap all user-visible strings in `t('...')`; define in `Locale/en_US/translations.php`

### CSS

- All classes prefixed with `portfolio-` (e.g., `.portfolio-blocked-icon`, `.portfolio-graph-node--resolved`)
- BEM-ish modifiers: `--blocked`, `--resolved`, `--open`

### JavaScript

- jQuery for DOM interactions (Kanboard bundles it)
- D3.js v7 for visualizations (bundled in `Asset/js/d3.v7.min.js`)
- No build/transpile step; vanilla ES5-compatible JS

## Architectural Constraints — Must Respect

1. **No template overrides.** Only use Kanboard template hooks (`$this->template->hook->attach/attachCallable`). Never call `setTemplateOverride()`.
2. **No core table writes.** Plugin reads from core tables (`tasks`, `projects`, `task_has_links`, `users`, `columns`, `links`) via JOINs. Writes only to its own 4 tables: `portfolios`, `portfolio_has_projects`, `milestones`, `milestone_has_tasks`.
3. **PicoDb for all queries.** Use `$this->db->table(...)` query builder for cross-DB compatibility. If table aliases are needed (e.g., self-joining `tasks`), use `$this->db->execute()` with PDO prepared statements and maintain variants for all 3 DB drivers.
4. **API-first.** Every model capability must have a corresponding JSON-RPC endpoint registered in `Plugin.php`.
5. **Schema migrations are versioned.** Each `Schema/{Driver}.php` has `const VERSION = N` and `function version_N(\PDO $pdo)`. Never modify existing version functions; only add new ones.
6. **D3.js is bundled.** No CDN references. Air-gapped installations must work.
7. **CSRF on all form POSTs.** Controllers call `$this->checkCSRFParam()`.
8. **XSS prevention.** All template output through `$this->text->e()` or `$this->text->markdown()`.

## Data Model (4 tables)

| Table | Purpose | Key Relations |
|-------|---------|---------------|
| `portfolios` | Portfolio definitions | `owner_id` → `users.id` |
| `portfolio_has_projects` | Portfolio ↔ Project (M:N) | PK: `(portfolio_id, project_id)`, cascades on delete |
| `milestones` | Cross-project milestones | `portfolio_id` → `portfolios.id`, cascades on delete |
| `milestone_has_tasks` | Milestone ↔ Task (M:N) | PK: `(milestone_id, task_id)`, cascades on delete |

## API Method Categories (28 total)

| Category | Count | Key Methods |
|----------|-------|-------------|
| Portfolio CRUD | 6 | `createPortfolio`, `getPortfolio`, `getAllPortfolios`, `updatePortfolio`, `removePortfolio`, `getPortfolioByName` |
| Portfolio ↔ Project | 4 | `addProjectToPortfolio`, `removeProjectFromPortfolio`, `getPortfolioProjects`, `getProjectPortfolios` |
| Milestone CRUD | 5 | `createMilestone`, `getMilestone`, `getPortfolioMilestones`, `updateMilestone`, `removeMilestone` |
| Milestone ↔ Task | 5 | `addTaskToMilestone`, `removeTaskFromMilestone`, `getMilestoneTasks`, `getTaskMilestones`, `getMilestoneProgress` |
| Dependencies | 5 | `getPortfolioDependencies`, `getBlockedTasks`, `getBlockingTasks`, `getPortfolioCriticalPath`, `getPortfolioDependencyGraph` |
| Unified Tasks | 3 | `getPortfolioTasks`, `getPortfolioTaskCount`, `getPortfolioOverview` |

## Events

- **Listens to:** `task.close`, `task.open`, `task_internal_link.create_update`, `task_internal_link.delete`
- **Fires:** `portfolio.dependency.resolved` (when closing a task unblocks cross-project dependents)

## Access Control

| Role | Permissions |
|------|------------|
| `app-user` | Read-only: view portfolios, milestones, dependencies, task lists |
| `app-manager` | Create portfolios; full CRUD on owned portfolios/milestones |
| `app-admin` | Full CRUD on all portfolios/milestones |

## Common Gotchas

1. **PicoDb alias limitation.** `join()` may not support `AS` aliases on all DB drivers. For self-joins on `tasks` (dependency queries), use raw `$this->db->execute()` with prepared statements. Maintain SQL for all 3 drivers or use DB-agnostic syntax.
2. **Default swimlane quirk.** `swimlane_id = 0` doesn't exist in the `swimlanes` table. Use `LEFT JOIN` and display "Default" when 0.
3. **Board hook N+1.** `template:board:task:icons` and `template:board:task:footer` hooks fire per card. Implement `DependencyModel::preloadBlockedStatus(array $taskIds)` for bulk loading.
4. **API return types.** Follow Kanboard convention: creation returns `int|false`, update/delete returns `bool`, get-by-id returns `dict|null`, list returns `array` (never `false`).
5. **All DB values are strings.** PicoDb returns string-typed values from SQLite. Don't assume integer types in API responses.
6. **Milestone name uniqueness** is scoped to portfolio (application-layer check, not DB constraint).
7. **Removing a project from a portfolio** must also clean up that project's tasks from all milestones in the portfolio (model-layer logic, not DB cascade).
8. **`task_has_links` link IDs.** The "blocks" and "is blocked by" link IDs are core-defined. Query `links` table to resolve IDs dynamically; don't hardcode.

## Testing Expectations

- **Unit tests required** for all model methods (see `Test/Model/`)
- **Integration tests required** for controller actions (see `Test/Controller/`)
- **Key coverage areas:** CRUD operations, cascade deletes, validation failures (return `false`), milestone progress computation, dependency graph construction, critical path algorithm, event firing
- Tests use Kanboard's built-in PHPUnit test framework

## Documentation Pointers

| File | Contents |
|------|----------|
| `docs/specs/001-kanboard-portfolio.md` | **Complete implementation spec:** data model DDL, all 28 API methods with params/returns, controller routes, template hooks, event system, config settings, security model, testing strategy, CSS conventions |
| `README.md` | User/contributor guide: install, config, usage, architecture overview |
| `AGENTS.md` | Multi-agent workflow guide: task-oriented project map, verification checklists |
