# Kanboard Portfolio Plugin

**Cross-project portfolio management for Kanboard** — portfolios, milestones, and dependency visualization.

[![Kanboard >= 1.2.20](https://img.shields.io/badge/Kanboard-%3E%3D%201.2.20-blue)](https://kanboard.org)
[![PHP >= 7.4](https://img.shields.io/badge/PHP-%3E%3D%207.4-purple)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## What Is This?

Kanboard's internal task links already support cross-project dependencies — `blocks` / `is blocked by` relationships work across project boundaries. But Kanboard provides **no tooling** to visualize, aggregate, or manage these relationships.

The **Portfolio** plugin fills that gap by adding three capabilities Kanboard lacks natively:

1. **Portfolios** — Named groups of related projects managed as a coordinated program
2. **Cross-Project Milestones** — First-class milestone entities with target dates that aggregate tasks from any project within a portfolio, with computed progress tracking
3. **Cross-Project Dependency Visualization** — Interactive views (D3.js force-directed graph, blocked task lists, critical path analysis) that surface dependency relationships between tasks in different projects

The plugin is API-first (31 JSON-RPC endpoints), hook-only (no template overrides), and adds just four database tables. It installs and removes cleanly without touching Kanboard core.

---

## Feature Highlights

| Feature | Description |
|---------|-------------|
| **Portfolio Dashboard** | Unified view of projects, milestones, task counts, and health indicators |
| **Aggregate Board** | See tasks from all portfolio projects on a single board |
| **Unified Task List** | Filterable, sortable, paginated task list across projects |
| **Cross-Project Milestones** | Define milestones spanning multiple projects with automatic progress tracking |
| **At-Risk / Overdue Detection** | Milestones flagged when approaching target dates with insufficient progress |
| **Dependency Graph** | Interactive D3.js force-directed graph showing cross-project blocking relationships |
| **Blocked Task View** | Identify which tasks are waiting on work in other projects |
| **Critical Path Analysis** | Compute the longest unresolved dependency chain through the portfolio |
| **Board Blocking Indicators** | 🔴 icons and "Blocked by" labels on task cards in the standard Kanboard board |
| **Dashboard Widgets** | "My Portfolios" sidebar and at-risk milestone alerts on the Kanboard dashboard |
| **Task Detail Integration** | Milestone membership and cross-project dependencies shown on task detail pages |
| **Full JSON-RPC API** | All 31 features exposed as API endpoints for CLI tools, dashboards, and automation |
| **Automatic Actions** | Notifications and comments when cross-project dependencies are resolved |
| **Search Filter** | `portfolio:` filter in Kanboard's task search |
| **Status Report API** | `getPortfolioStatusReport` — AI-ready structured summary: milestone health, blockers, at-risk items, dependency health |
| **Weighted Milestone Progress** | Track progress by task count, story points, or time estimated |
| **Activity Feed** | Recent activity across all portfolio projects on the dashboard |
| **Workload / Team View** | Per-user task metrics with overload indicators; filterable by portfolio |
| **Roadmap View** | Milestone-level D3.js timeline with health-coded progress bars and a "Today" marker |
| **Milestone on Task Create** | Assign a task to a portfolio milestone directly from the task creation form |
| **Portfolio Context** | Task detail pages, project headers, and board cards show portfolio membership at a glance |
| **Auto-Complete Milestones** | Milestones automatically close when all their tasks reach a done-pattern column |

---

## Prerequisites & System Requirements

| Requirement | Minimum Version | Notes |
|-------------|-----------------|-------|
| **Kanboard** | >= 1.2.20 | Required for all template hooks used |
| **PHP** | >= 7.4 | Matches Kanboard's own minimum |
| **Database** | SQLite 3.x, MySQL 5.7+ / MariaDB 10.2+, PostgreSQL 9.5+ | All three supported via separate migration files |

No external PHP dependencies (Composer packages) are required. D3.js v7 is bundled in the plugin assets.

---

## Installation

### Option 1: Git Clone (Recommended for Development)

```bash
# Navigate to your Kanboard plugins directory
cd /path/to/kanboard/plugins

# Clone the repository
git clone https://github.com/geekmuse/kanboard-plugin-portfolio-mgmt.git Portfolio

# That's it — Kanboard auto-discovers plugins and runs migrations on next page load
```

### Option 2: Download Release

1. Download the latest release archive from the [Releases](https://github.com/geekmuse/kanboard-plugin-portfolio-mgmt/releases) page
2. Extract into `plugins/Portfolio/` within your Kanboard installation
3. Ensure the directory is named exactly `Portfolio` (Kanboard uses the directory name for the namespace)

### Option 3: Kanboard Plugin Directory

Once published, install directly from Kanboard's **Settings → Plugins → Plugin Directory**.

### Post-Install Verification

1. Navigate to **Settings → Plugins** in Kanboard
2. Confirm "Portfolio" appears in the installed plugins list with version `1.22.1`
3. Check that the "Portfolios" link appears in the header navigation

Database tables (`portfolios`, `portfolio_has_projects`, `milestones`, `milestone_has_tasks`) are created automatically on first page load.

---

## Configuration

### Plugin Settings

Navigate to **Settings → Portfolio Settings** in Kanboard to configure:

| Setting | Default | Description |
|---------|---------|-------------|
| `portfolio_milestone_at_risk_days` | `7` | Days before target date to flag milestones as "at risk" |
| `portfolio_milestone_at_risk_threshold` | `80` | % completion threshold below which milestones are flagged |
| `portfolio_board_show_blockers` | `1` | Show blocking indicators on board task cards |
| `portfolio_dashboard_widget_enabled` | `1` | Show portfolio widget on dashboard |
| `portfolio_dependency_link_types` | `"blocks"` | Comma-separated link types treated as dependencies |
| `portfolio_tasks_per_page` | `50` | Default pagination limit for task lists |
| `portfolio_milestone_weight_by` | `count` | Default weight mode for milestone progress: `count`, `score`, or `time_estimated` |
| `portfolio_workload_threshold` | `15` | Active-task count above which a user is flagged as overloaded in the Team view |
| `portfolio_auto_complete_milestones` | `1` | Auto-transition milestone to completed when all its tasks reach a done-pattern column |

All settings are stored in Kanboard's `settings` table (prefixed with `portfolio_`) and take effect immediately.

---

## Usage Guide

### Creating a Portfolio

1. Click **"+" → Create Portfolio** in the header dropdown, or navigate to **Portfolios → Create Portfolio**
2. Enter a name (must be unique) and optional description
3. On the portfolio settings page, add projects by selecting from the dropdown

### Managing Milestones

1. Navigate to a portfolio → **Milestones** tab
2. Click **Create Milestone** — set name, target date, color, and owner
3. Add tasks to the milestone from any project within the portfolio
4. Progress is computed automatically: `completed tasks / total tasks × 100%`

### Viewing Dependencies

- **Dependency Graph**: Portfolio → Dependencies — interactive D3.js visualization
- **Blocked Tasks**: Portfolio → Blocked — list of tasks waiting on unresolved cross-project dependencies
- **Critical Path**: Portfolio → Critical Path — the longest unresolved dependency chain

### API Access

All features are available via Kanboard's JSON-RPC API:

```bash
# Example: Create a portfolio
curl -X POST \
  -u "admin:admin" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"createPortfolio","id":1,"params":{"name":"Q2 Launch"}}' \
  http://localhost/kanboard/jsonrpc.php
```

See the [API specification](docs/specs/001-kanboard-portfolio.md#4-api-surface--interface-contract) for all 31 methods.

---

## Project Structure

```
plugins/Portfolio/
├── Plugin.php                      # Entry point: DI, routes, hooks, API, events
├── Schema/
│   ├── Sqlite.php                  # SQLite migrations
│   ├── Mysql.php                   # MySQL/MariaDB migrations
│   └── Postgres.php                # PostgreSQL migrations
├── Model/
│   ├── PortfolioModel.php          # Portfolio CRUD
│   ├── PortfolioProjectModel.php   # Portfolio ↔ Project junction
│   ├── MilestoneModel.php          # Milestone CRUD + progress
│   ├── MilestoneTaskModel.php      # Milestone ↔ Task junction
│   ├── DependencyModel.php         # Cross-project dependency queries
│   └── PortfolioTaskModel.php      # Unified cross-project task queries
├── Controller/
│   ├── PortfolioListController.php
│   ├── PortfolioViewController.php
│   ├── PortfolioModificationController.php
│   ├── MilestoneController.php
│   └── DependencyController.php
├── Action/                         # Automatic actions (event-driven)
├── Notification/                   # Custom notification types
├── Formatter/                      # Data formatters (task list, Gantt, graph)
├── Filter/                         # Custom task search filter
├── Helper/                         # Template helpers (progress bars, etc.)
├── Validator/                      # Input validation
├── Template/
│   ├── portfolio/                  # Portfolio views (list, dashboard, board, etc.)
│   ├── milestone/                  # Milestone views (list, detail, forms)
│   ├── dependency/                 # Dependency views (graph, blocked, critical path)
│   └── widget/                     # Hook-injected widgets (dashboard, board, task detail)
├── Asset/
│   ├── js/                         # D3.js (bundled), graph, Gantt, progress bar JS
│   └── css/                        # All plugin styles (portfolio-* prefixed classes)
├── Locale/
│   └── en_US/translations.php      # English strings
├── Test/
│   ├── Model/                      # Unit tests for models
│   └── Controller/                 # Integration tests for controllers
└── docs/
    └── specs/
        └── 001-kanboard-portfolio.md   # Complete implementation specification
```

---

## Development Workflow

### Local Development Environment

1. Set up a local Kanboard instance ([Kanboard docs](https://docs.kanboard.org/v1/admin/installation/))
2. Clone this repo into `plugins/Portfolio/`
3. Enable debug mode in Kanboard's `config.php`: `define('DEBUG', true);`

### Running Tests

```bash
# From the Kanboard root directory
# Run all plugin tests
./vendor/bin/phpunit plugins/Portfolio/Test/

# Run a specific test class
./vendor/bin/phpunit plugins/Portfolio/Test/Model/PortfolioModelTest.php

# Run a specific test method
./vendor/bin/phpunit --filter testCreatePortfolio plugins/Portfolio/Test/Model/PortfolioModelTest.php
```

### Code Quality

```bash
# PHP syntax check
find plugins/Portfolio/ -name "*.php" -exec php -l {} \;

# PHP_CodeSniffer (if available)
./vendor/bin/phpcs --standard=PSR12 plugins/Portfolio/
```

### Database Migrations

Migrations run automatically. To force a re-migration during development:

1. Delete the plugin's version entry from Kanboard's `plugin_schema_versions` table
2. Reload any Kanboard page — migrations will re-run

### Branching Strategy

- `main` — stable, release-ready code
- `develop` — integration branch for feature work
- `feature/*` — individual feature branches
- `bugfix/*` — bug fix branches

### Pull Request Process

1. Branch from `develop`
2. Implement changes with tests
3. Ensure all tests pass and linting is clean
4. Open a PR against `develop` with a clear description
5. All PRs require at least one review before merge

---

## Architecture Overview

```
┌──────────────────────────────────────────────────────────────────┐
│                       Kanboard Core                              │
│  projects · tasks · task_has_links · users · columns · links     │
│  Event Bus (Symfony Dispatcher)                                  │
└──────────────────┬───────────────────────────────────────────────┘
                   │
┌──────────────────▼───────────────────────────────────────────────┐
│                    Portfolio Plugin                               │
│                                                                  │
│  Plugin Tables:    portfolios · portfolio_has_projects            │
│                    milestones · milestone_has_tasks               │
│                                                                  │
│  Data Flow:                                                      │
│  1. Models query plugin tables + JOIN to core tables             │
│  2. Controllers call models, pass data to templates              │
│  3. API methods call models, return JSON-RPC responses           │
│  4. Template hooks inject widgets into Kanboard pages            │
│  5. Event listeners react to core events (task.close, etc.)      │
│     and fire portfolio.dependency.resolved                       │
└──────────────────────────────────────────────────────────────────┘
```

### Key Architectural Decisions

| Decision | Rationale |
|----------|-----------|
| Reuse `task_has_links` for dependencies | Zero data migration; dependencies from any source are visible |
| Milestones are first-class entities (not tasks) | Different semantics: no column, no assignee, aggregate progress |
| PicoDb for all queries | Cross-database compatibility (SQLite, MySQL, PostgreSQL) |
| API-first design | Every feature accessible via JSON-RPC; UI is one consumer |
| D3.js bundled (not CDN) | Supports air-gapped / firewalled installations |
| Template hooks only (no overrides) | Minimizes conflicts with other plugins and Kanboard upgrades |

---

## Troubleshooting / FAQ

### Plugin doesn't appear after installation

- Verify the directory is named exactly `Portfolio` (case-sensitive)
- Check that `Plugin.php` is at `plugins/Portfolio/Plugin.php`
- Check Kanboard's logs for PHP errors: `data/debug.log`

### Database tables not created

- Tables are created on first page load after install. Navigate to any Kanboard page.
- If still missing, check that your database user has `CREATE TABLE` permissions
- For SQLite, ensure the database file is writable

### Dependency graph is blank

- Ensure tasks in the portfolio have `blocks` / `is blocked by` links
- By default, only **cross-project** dependencies are shown. Toggle "All Links" to see intra-project links.
- If D3.js fails to load, a "Visualization unavailable" message is shown instead

### Board performance is slow

- The board hooks query blocking status per task card. For large boards, the plugin pre-loads data in bulk. If performance is still an issue, disable board indicators in **Settings → Portfolio Settings** (`portfolio_board_show_blockers = 0`)

### "Access denied" on create/edit operations

- Portfolio creation and modification requires `app-manager` or `app-admin` role
- Regular `app-user` accounts have read-only access to all portfolios

---

## Permissions Model

| Operation | Required Role |
|-----------|---------------|
| View portfolios, milestones, dependencies | `app-user` (any authenticated user) |
| Create portfolio | `app-manager` or `app-admin` |
| Edit/delete portfolio | `app-admin`, or `app-manager` who is the portfolio owner |
| Manage project membership | `app-admin`, or `app-manager` who is the portfolio owner |
| Create/edit/delete milestones | `app-admin`, or `app-manager` who is the portfolio owner |
| Add/remove tasks to milestones | `app-admin`, or `app-manager` who is the portfolio owner |

> **Note:** Portfolio views expose task titles and statuses from all member projects, regardless of the viewer's project-level access. Only add projects whose task data is acceptable for broad visibility.

---

## License

This project is licensed under the [MIT License](LICENSE).

---

## Related Documentation

| Document | Description |
|----------|-------------|
| [Implementation Specification](docs/specs/001-kanboard-portfolio.md) | Complete spec: data model, API surface, controllers, templates, events, testing |
| [CLAUDE.md](CLAUDE.md) | Agent context file for Claude Code sessions |
| [AGENTS.md](AGENTS.md) | Multi-agent development workflow guide |
