# AGENTS.md — Multi-Agent Development Guide

## Quick Orientation

This is a Kanboard plugin (PHP) named "Portfolio" that adds cross-project portfolio management with grouped projects, shared milestones, and dependency visualization. Read `CLAUDE.md` for concise project context, commands, and constraints. Read `README.md` for user-facing docs. Read `docs/specs/001-kanboard-portfolio.md` for the authoritative implementation specification.

---

## Commands

After **any** code change, run:

```bash
ralphi check
```

`ralphi check` uses `.ralphi/config.yaml` and runs:
- PHPUnit tests (when Kanboard test runtime is available)
- PHP syntax lint (`php -l`)
- PHP_CodeSniffer (PSR-12) when installed
- PHPStan (level 5) when installed

---

## Conventions

1. Use PicoDb query builder for all database queries — no raw SQL string interpolation
2. All templates escape output with `$this->text->e()` — never echo raw user input
3. All user-visible strings wrapped in `t('...')` for localization
4. CSS classes prefixed with `portfolio-` to avoid collisions
5. Template hooks only — never use `setTemplateOverride()`
6. Write only to plugin tables — never write to Kanboard core tables
7. Schema migrations are versioned and append-only — never modify existing `version_N()` functions
8. Every model method must have a corresponding unit test
9. Require integration tests for all controller actions
10. Follow PSR-12 coding standard (enforced via PHP_CodeSniffer)
11. Maintain PHPStan level 5 compliance — no new baseline errors

---

## Documentation Index

| File | Description |
|------|-------------|
| `README.md` | User & contributor guide — install, config, usage, architecture, troubleshooting |
| `CLAUDE.md` | Agent context — project summary, commands, code style, architectural constraints, gotchas |
| `.ralphi/config.yaml` | Ralphi loop configuration — commands, rules, boundaries |
| `docs/specs/001-kanboard-portfolio.md` | **Complete implementation spec** — data model DDL (§3), all 28 API methods with params/returns (§4), controller routes (§5), template & UI hooks (§6), event system (§7), configuration (§8), dependencies (§9), error handling & edge cases (§10), security model (§11), testing strategy (§12), localization (§13), future considerations (§14), full `Plugin.php` registration (Appendix A), CSS conventions (Appendix C) |

---

## Task-Oriented Project Map

### Database / Schema Work

| Look at | For |
|---------|-----|
| `Schema/Sqlite.php`, `Schema/Mysql.php`, `Schema/Postgres.php` | Migration functions (one per version) |
| `docs/specs/001-kanboard-portfolio.md` §3 | Full DDL for all 4 tables, column specs, indexes, constraints |

**Rules:**
- Never modify existing `version_N()` functions; only add `version_N+1()`
- Increment `const VERSION` in all 3 driver files simultaneously
- Test migrations on SQLite at minimum; ideally all 3 drivers
- `Schema/Sqlite.php`, `Schema/Mysql.php`, and `Schema/Postgres.php` intentionally reuse namespace-level symbols (`VERSION`, `version_N()`), so cross-driver tests should statically inspect non-active driver files instead of requiring all schema files in one PHP process

---

### Model Layer (Business Logic)

| Look at | For |
|---------|-----|
| `Model/PortfolioModel.php` | Portfolio CRUD |
| `Model/PortfolioProjectModel.php` | Portfolio ↔ Project junction (add/remove/list) |
| `Model/MilestoneModel.php` | Milestone CRUD + progress computation |
| `Model/MilestoneTaskModel.php` | Milestone ↔ Task junction + validation |
| `Model/DependencyModel.php` | Cross-project dependency queries, critical path, event handling |
| `Model/PortfolioTaskModel.php` | Unified cross-project task queries (filterable, paginated) |
| `docs/specs/001-kanboard-portfolio.md` §4 | All 28 API method contracts (params, returns, validation, PicoDb examples) |
| `Validator/PortfolioValidator.php` | Input validation rules |

**Rules:**
- Models extend `Kanboard\Core\Base`
- Use `$this->db->table(...)` (PicoDb) for all queries
- For self-joins on `tasks`, may need `$this->db->execute()` with prepared statements
- Return types: create → `int|false`, update/delete → `bool`, get-one → `dict|null`, list → `array`
- Update methods should ignore `null` keys when values come from API `compact(...)` defaults, so omitted fields do not fail validation or overwrite persisted data unintentionally
- When removing a project from a portfolio, also clean its tasks from the portfolio's milestones
- `addTaskToMilestone` must verify the task's project is in the milestone's portfolio
- Dependency queries must resolve dependency link IDs dynamically from `links` and normalize direction using `label`/`opposite_label` so `blocks` and `is blocked by` rows are interpreted consistently
- Critical-path calculations should run on unresolved active edges only, topologically sort the graph, and deterministically break cycles by removing the latest edge before longest-path evaluation
- Unified portfolio task queries should precompute project/column/user/dependency lookup maps once, then apply filters/sort/pagination in-memory for deterministic cross-driver behavior without raw SQL joins

---

### Controller Layer (Web Routes)

| Look at | For |
|---------|-----|
| `Controller/PortfolioListController.php` | `GET /portfolios` — portfolio index |
| `Controller/PortfolioViewController.php` | Portfolio dashboard, task list, board, timeline |
| `Controller/PortfolioModificationController.php` | Portfolio create/edit/delete/settings forms |
| `Controller/MilestoneController.php` | Milestone CRUD views + task add/remove (AJAX) |
| `Controller/DependencyController.php` | Dependency graph, blocked tasks, critical path views |
| `docs/specs/001-kanboard-portfolio.md` §5 | Route table, access maps, controller method signatures |

**Rules:**
- Controllers extend `Kanboard\Core\Base`
- All form POSTs must call `$this->checkCSRFParam()`
- Fetch route params with `$this->request->getIntegerParam('portfolio_id')`
- Read-only routes: `Role::APP_USER`; write routes: `Role::APP_MANAGER`
- For portfolio membership settings, load current members via `portfolioProjectModel->getProjects()` and compute addable projects by subtracting membership IDs from `projectModel->getAll()` so add/remove forms stay deterministic across runtimes
- For filterable list views, normalize GET query params in the controller and pass scalar `filters` + `pagination_query` arrays to templates so selected values and previous/next links stay stable across runtimes
- For aggregate portfolio board/timeline views, fetch active tasks with fixed deterministic filters (`status_id=1`, explicit sort/direction, bounded limit) and build grouped view data in the controller to keep templates simple and testable in the lightweight harness
- For milestone task membership actions, keep controller handlers thin (`task_id`, `is_critical`, `position`) and delegate project-in-portfolio validation to `milestoneTaskModel->add()` so UI flows and API behavior stay consistent

---

### Template / UI Layer

| Look at | For |
|---------|-----|
| `Template/portfolio/` | Portfolio pages (index, show, create, edit, tasks, board, settings, remove) |
| `Template/milestone/` | Milestone pages (index, show, create, edit, remove) |
| `Template/dependency/` | Dependency views (graph, blocked, critical_path) |
| `Template/widget/` | Hook-injected widgets (dashboard, board cards, task detail, project sidebar, header) |
| `docs/specs/001-kanboard-portfolio.md` §6 | Template hook registrations, widget markup examples, performance notes |

**Rules:**
- All output escaped with `$this->text->e()` — never echo raw user input
- URLs via `$this->url->href()`
- All user-visible strings wrapped in `t('...')`
- CSS classes prefixed with `portfolio-` (see Appendix C in spec)
- **No template overrides** — hooks only

---

### Client-Side Assets

| Look at | For |
|---------|-----|
| `Asset/js/d3.v7.min.js` | Bundled D3.js library (do not modify) |
| `Asset/js/dependency-graph.js` | Force-directed graph component |
| `Asset/js/portfolio-gantt.js` | Multi-project timeline |
| `Asset/js/milestone-progress.js` | Progress bar interactions |
| `Asset/css/portfolio.css` | All plugin styles |

**Rules:**
- D3.js is bundled — no CDN references
- jQuery is available (Kanboard bundles it)
- Vanilla ES5-compatible JS — no build/transpile step
- Timeline pages pass serialized items via `data-items` on `.portfolio-timeline-chart`; `Asset/js/portfolio-gantt.js` reads that attribute and renders markers through `window.PortfolioGantt.render(...)`

---

### Event System & Automatic Actions

| Look at | For |
|---------|-----|
| `Plugin.php` (event registration section) | Event listeners and firing |
| `Action/NotifyDependencyResolved.php` | Auto-action: notify assignee when dependency resolved |
| `Action/CommentDependencyResolved.php` | Auto-action: add comment when dependency resolved |
| `Notification/DependencyResolvedType.php` | Custom notification type |
| `docs/specs/001-kanboard-portfolio.md` §7 | Event contracts, event data shapes, action logic |

**Rules:**
- Listen to: `task.close`, `task.open`, `task_internal_link.create_update`, `task_internal_link.delete`
- Fire: `portfolio.dependency.resolved` (only on cross-project unblock)
- Actions extend `Kanboard\Action\Base`

---

### API (JSON-RPC)

| Look at | For |
|---------|-----|
| `Plugin.php` (API registration section) | All 28 `withCallback()` registrations |
| `docs/specs/001-kanboard-portfolio.md` §4 | Full method contracts: params, types, defaults, returns, failure conditions |

**Rules:**
- Methods registered via `$this->api->getProcedureHandler()->withCallback(...)`
- Read methods: no access map entry needed (defaults to `APP_USER`)
- Write methods: must be in `$this->apiAccessMap` with `Role::APP_MANAGER`

---

### Tests

| Look at | For |
|---------|-----|
| `Test/Model/PortfolioModelTest.php` | Portfolio CRUD tests |
| `Test/Model/MilestoneModelTest.php` | Milestone CRUD + progress tests |
| `Test/Model/DependencyModelTest.php` | Dependency queries, critical path, event tests |
| `Test/Model/PortfolioTaskModelTest.php` | Unified task query tests |
| `Test/Controller/PortfolioControllerTest.php` | Controller integration tests |
| `Test/Controller/MilestoneControllerTest.php` | Milestone controller tests |
| `docs/specs/001-kanboard-portfolio.md` §12 | Test case inventory, acceptance criteria |

**Rules:**
- Use Kanboard's PHPUnit test framework
- Every model method must have corresponding tests
- Test both success and failure paths (e.g., duplicate name → `false`)
- Test cascade deletes
- Test access control (unauthorized → error)
- If Kanboard runtime dependencies are unavailable locally, model tests may use an in-memory SQLite harness plus a minimal `Kanboard\Core\Base` stub and PicoDb-like adapter to validate behavior deterministically
- If full HTTP functional helpers are unavailable, controller integration tests may execute controller actions directly using lightweight stubs for request/response/template/flash services, while still asserting rendered templates, redirects, and access-map role wiring

---

### Localization

| Look at | For |
|---------|-----|
| `Locale/en_US/translations.php` | All translation strings |
| `docs/specs/001-kanboard-portfolio.md` §13 | Complete translation key list |

---

### Configuration

| Look at | For |
|---------|-----|
| `docs/specs/001-kanboard-portfolio.md` §8 | All `portfolio_*` settings with defaults |
| `Template/config/sidebar.php` | Settings page sidebar link |

---

## Agent Workflow Guidance

### Implementing a New Feature

1. **Read the spec** — Check `docs/specs/001-kanboard-portfolio.md` for the relevant section
2. **Schema first** — If new tables/columns needed, add migrations to all 3 driver files
3. **Model** — Implement business logic. Use PicoDb. Follow return type conventions.
4. **API** — Register JSON-RPC method in `Plugin.php`. Add access map entry if write operation.
5. **Validator** — Add input validation rules for new fields
6. **Controller** — Add routes and controller methods. CSRF on POSTs. Access map entry.
7. **Templates** — Create views. Escape output. Use `t()` for strings.
8. **Events** — If the feature involves task state changes, check if event listeners need updating
9. **Tests** — Unit tests for model, integration tests for controller
10. **Translations** — Add new strings to `Locale/en_US/translations.php`

### Fixing a Bug

1. **Reproduce** — Write a failing test that exposes the bug
2. **Locate** — Use the task-oriented map above to find the relevant code layer
3. **Fix** — Make the minimal change needed
4. **Verify** — Ensure the failing test now passes; run full test suite
5. **Check blast radius** — Review integration points (see below)

### Refactoring

1. **Tests first** — Ensure comprehensive test coverage exists before refactoring
2. **One layer at a time** — Don't refactor model + controller + template in one pass
3. **Preserve API contracts** — JSON-RPC method signatures and return types must not change
4. **Preserve DB schema** — Use migrations for any schema changes

---

## Integration Points & Blast Radius

Understanding which components connect helps assess the impact of changes:

```
Plugin.php ──── registers ──→ Models (DI container)
    │                         Controllers (routes + access maps)
    │                         API methods (JSON-RPC)
    │                         Event listeners
    │                         Template hooks
    │                         Asset loading
    │
    ├── Model changes affect → API responses
    │                          Controller data
    │                          Template rendering
    │                          Event handling
    │
    ├── Schema changes affect → All 3 migration files (Sqlite, Mysql, Postgres)
    │                           Model queries
    │                           Test fixtures
    │
    ├── Controller changes affect → Route accessibility
    │                                Template data contracts
    │
    └── Template changes affect → Only that view (isolated)
        (unless widget hooks, which affect Kanboard core pages)
```

### High-Impact Changes (touch multiple layers)

- Adding a new table → Schema (×3) + Model + API + Controller + Template + Tests
- Changing a model method signature → API registration + Controller calls + Tests
- Adding a new event → `Plugin.php` + Model (fire) + Action classes + Notification

### Low-Impact Changes (isolated)

- Template markup/styling → Single `.php` file + possibly `portfolio.css`
- Translation string → `Locale/en_US/translations.php` only
- Test additions → `Test/` only

---

## File Naming & Creation Conventions

| Component | Location | Naming Pattern | Example |
|-----------|----------|----------------|---------|
| Model | `Model/` | `{Entity}Model.php` | `PortfolioModel.php` |
| Controller | `Controller/` | `{Entity}{Purpose}Controller.php` | `PortfolioModificationController.php` |
| Action | `Action/` | `{Verb}{Entity}{Subject}.php` | `NotifyDependencyResolved.php` |
| Formatter | `Formatter/` | `{Entity}{Format}Formatter.php` | `DependencyGraphFormatter.php` |
| Filter | `Filter/` | `Task{Name}Filter.php` | `TaskPortfolioFilter.php` |
| Helper | `Helper/` | `{Name}Helper.php` | `PortfolioHelper.php` |
| Validator | `Validator/` | `{Name}Validator.php` | `PortfolioValidator.php` |
| Template | `Template/{section}/` | `{action}.php` | `Template/portfolio/create.php` |
| Test | `Test/{Layer}/` | `{Class}Test.php` | `Test/Model/PortfolioModelTest.php` |
| Migration | `Schema/` | `{Driver}.php` | `Schema/Sqlite.php` |

---

## Verification Checklist

Before marking any task as complete, verify:

### Code Quality
- [ ] PHP syntax check passes: `find plugins/Portfolio/ -name "*.php" -exec php -l {} \;`
- [ ] PSR-12 compliant: `./vendor/bin/phpcs --standard=PSR12 plugins/Portfolio/`
- [ ] PHPStan level 5 clean: `./vendor/bin/phpstan analyse plugins/Portfolio/ --level=5`
- [ ] Code follows project conventions (see `CLAUDE.md` and `.ralphi/config.yaml`)
- [ ] No hardcoded link IDs — resolve from `links` table dynamically
- [ ] No raw SQL string interpolation — PicoDb or PDO prepared statements only
- [ ] All template output escaped with `$this->text->e()`
- [ ] All user-visible strings wrapped in `t('...')`
- [ ] CSS classes prefixed with `portfolio-`

### Functionality
- [ ] All existing tests pass: `./vendor/bin/phpunit plugins/Portfolio/Test/`
- [ ] New model methods have unit tests
- [ ] New controller actions have integration tests
- [ ] API return types match Kanboard conventions (`int|false`, `bool`, `dict|null`, `array`)
- [ ] CSRF protection on all form POST handlers
- [ ] Access control enforced (check access map in `Plugin.php`)

### Cross-DB Compatibility
- [ ] If schema changed: all 3 migration files updated (`Sqlite.php`, `Mysql.php`, `Postgres.php`)
- [ ] PicoDb queries avoid driver-specific SQL
- [ ] Self-joins handled via `$this->db->execute()` if PicoDb aliases don't work cross-driver

### Documentation
- [ ] `Locale/en_US/translations.php` updated with any new strings
- [ ] Spec (`docs/specs/001-kanboard-portfolio.md`) still accurate, or updated if the implementation diverges

### Integration
- [ ] `Plugin.php` updated if new routes, DI registrations, hooks, API methods, or events added
- [ ] No template overrides — hooks only
- [ ] No writes to core Kanboard tables

---

## Cross-References

- **For project context, commands, and constraints** → see `CLAUDE.md`
- **For user-facing docs, install, and architecture overview** → see `README.md`
- **For authoritative implementation details** → see `docs/specs/001-kanboard-portfolio.md`
