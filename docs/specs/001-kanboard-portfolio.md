# Kanboard Portfolio Plugin — Complete Implementation Specification

**Version:** 1.0.0-draft
**Date:** 2026-03-22
**Status:** Implementation-Ready Specification
**Target Kanboard Version:** >= 1.2.20
**License:** MIT

---

## Table of Contents

1. [Overview & Purpose](#1-overview--purpose)
2. [Architecture & Design](#2-architecture--design)
3. [Data Model](#3-data-model)
4. [API Surface / Interface Contract](#4-api-surface--interface-contract)
5. [Controller & Route Specification](#5-controller--route-specification)
6. [Template & UI Specification](#6-template--ui-specification)
7. [Event System & Automatic Actions](#7-event-system--automatic-actions)
8. [Configuration & Defaults](#8-configuration--defaults)
9. [Dependencies & Requirements](#9-dependencies--requirements)
10. [Error Handling & Edge Cases](#10-error-handling--edge-cases)
11. [Security Considerations](#11-security-considerations)
12. [Testing Strategy](#12-testing-strategy)
13. [Localization](#13-localization)
14. [Future Considerations / Open Questions](#14-future-considerations--open-questions)

---

## 1. Overview & Purpose

### 1.1 What This Plugin Does

The **Kanboard Portfolio** plugin adds cross-project orchestration capabilities to Kanboard. It introduces three concepts that Kanboard lacks natively:

1. **Portfolios** — Named groups of related Kanboard projects managed as a coordinated program.
2. **Cross-Project Milestones** — First-class milestone entities with target dates that aggregate tasks from any project within a portfolio, with computed progress tracking.
3. **Cross-Project Dependency Visualization** — Views that surface and analyze the dependency relationships between tasks in different projects, using Kanboard's existing internal task link system.

### 1.2 The Problem It Solves

Kanboard's internal task links (`task_has_links`) already support cross-project dependencies — the `blocks`/`is blocked by` relationship works across project boundaries because task IDs are global. However, Kanboard provides **no visualization, aggregation, or management tooling** for these cross-project relationships. Users cannot:

- See a unified view of tasks across multiple projects
- Visualize dependency chains that cross project boundaries
- Define milestones that span projects and track aggregate progress
- Identify which tasks are blocked by work in other projects
- Reason about critical paths through a multi-project program

This forces users into workarounds (collapsing everything into one project, manual tracking in spreadsheets) that defeat the purpose of Kanboard's project-level organization.

### 1.3 Target Users

- **Project managers** coordinating multiple interrelated projects (product launches, cross-team initiatives)
- **Team leads** who need visibility into how their project's work connects to and depends on other projects
- **Stakeholders** who need a high-level view of program health across multiple workstreams
- **Automation engineers** who build tooling on top of Kanboard's API (the plugin exposes all features as JSON-RPC endpoints)

### 1.4 Design Philosophy

1. **Enhance, don't replace.** The plugin layers on top of Kanboard's existing data model. Cross-project dependencies use the existing `task_has_links` table — the plugin adds queries and visualization, not a parallel storage system.
2. **API-first.** Every feature is available as a JSON-RPC endpoint. The web UI is a consumer of the same data model that external tools (CLIs, dashboards) can access.
3. **Minimal footprint.** Four new database tables, no core patches, no template overrides (hooks only). The plugin can be installed and removed cleanly.
4. **Respect Kanboard's architecture.** Follow the same patterns as Kanboard core: PicoDb for queries, Symfony EventDispatcher for events, Pimple for DI, pure PHP templates, jQuery for JS interactions.

---

## 2. Architecture & Design

### 2.1 Directory Structure

```
plugins/Portfolio/
├── Plugin.php                          # Plugin registration (entry point)
├── Schema/
│   ├── Sqlite.php                      # SQLite migrations
│   ├── Mysql.php                       # MySQL/MariaDB migrations
│   └── Postgres.php                    # PostgreSQL migrations
├── Model/
│   ├── PortfolioModel.php              # Portfolio CRUD
│   ├── PortfolioProjectModel.php       # Portfolio ↔ Project junction
│   ├── MilestoneModel.php              # Milestone CRUD
│   ├── MilestoneTaskModel.php          # Milestone ↔ Task junction
│   ├── DependencyModel.php             # Cross-project dependency queries
│   └── PortfolioTaskModel.php          # Unified cross-project task queries
├── Controller/
│   ├── PortfolioListController.php     # Portfolio list view
│   ├── PortfolioViewController.php     # Portfolio dashboard, task list, board
│   ├── PortfolioModificationController.php  # Portfolio create/edit/delete forms
│   ├── MilestoneController.php         # Milestone views + forms
│   └── DependencyController.php        # Dependency graph + blocked views
├── Action/
│   ├── NotifyDependencyResolved.php    # Auto-action: notify on unblock
│   └── CommentDependencyResolved.php   # Auto-action: comment on unblock
├── Notification/
│   └── DependencyResolvedType.php      # Notification type for dependency events
├── Formatter/
│   ├── PortfolioTaskListFormatter.php  # Task list rendering
│   ├── PortfolioGanttFormatter.php     # Timeline data formatting
│   └── DependencyGraphFormatter.php    # Graph JSON for D3.js
├── Filter/
│   └── TaskPortfolioFilter.php         # "portfolio:" search filter
├── Helper/
│   └── PortfolioHelper.php             # Template helpers (progress bars, indicators)
├── Validator/
│   └── PortfolioValidator.php          # Input validation for forms/API
├── Template/
│   ├── portfolio/
│   │   ├── index.php                   # Portfolio list page
│   │   ├── show.php                    # Portfolio dashboard
│   │   ├── create.php                  # Create form
│   │   ├── edit.php                    # Edit form
│   │   ├── tasks.php                   # Unified task list
│   │   ├── board.php                   # Aggregate board
│   │   ├── settings.php                # Portfolio settings (projects)
│   │   └── remove.php                  # Delete confirmation
│   ├── milestone/
│   │   ├── index.php                   # Milestones list within portfolio
│   │   ├── show.php                    # Milestone detail
│   │   ├── create.php                  # Create form
│   │   ├── edit.php                    # Edit form
│   │   └── remove.php                  # Delete confirmation
│   ├── dependency/
│   │   ├── graph.php                   # Interactive D3 graph page
│   │   ├── blocked.php                 # Blocked tasks list
│   │   └── critical_path.php           # Critical path view
│   └── widget/
│       ├── dashboard_sidebar.php       # Dashboard sidebar: "My Portfolios"
│       ├── dashboard_milestones.php    # Dashboard: at-risk milestones
│       ├── board_task_icons.php        # Board: blocked indicator icon
│       ├── board_task_footer.php       # Board: "Blocked by" text
│       ├── task_sidebar.php            # Task detail: milestones
│       ├── task_dependencies.php       # Task detail: cross-project deps
│       ├── project_sidebar.php         # Project settings: portfolios
│       └── header_dropdown.php         # Header "+": create portfolio
├── Asset/
│   ├── js/
│   │   ├── d3.v7.min.js               # D3.js library (bundled)
│   │   ├── dependency-graph.js         # Force-directed graph component
│   │   ├── portfolio-gantt.js          # Multi-project timeline
│   │   └── milestone-progress.js       # Progress bar interactions
│   └── css/
│       └── portfolio.css               # All plugin styles
├── Locale/
│   └── en_US/
│       └── translations.php            # English strings
└── Test/
    ├── Model/
    │   ├── PortfolioModelTest.php
    │   ├── MilestoneModelTest.php
    │   ├── DependencyModelTest.php
    │   └── PortfolioTaskModelTest.php
    └── Controller/
        ├── PortfolioControllerTest.php
        └── MilestoneControllerTest.php
```

### 2.2 Component Interaction Diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│                           Kanboard Core                                  │
│                                                                          │
│  ┌─────────────┐  ┌─────────────┐  ┌────────────┐  ┌────────────────┐  │
│  │   projects   │  │    tasks    │  │task_has_    │  │    users       │  │
│  │   (table)    │  │   (table)   │  │links (tbl)  │  │   (table)      │  │
│  └──────┬───────┘  └──────┬──────┘  └──────┬─────┘  └───────┬────────┘  │
│         │                 │                │                 │           │
│         │        ┌────────┴────────────────┘                 │           │
│         │        │  Kanboard Event Bus (Symfony Dispatcher)  │           │
│         │        │  • task.close  • task.open                │           │
│         │        │  • task_internal_link.create_update        │           │
│         │        └─────────┬─────────────────────────────────┘           │
└─────────┼──────────────────┼─────────────────────────────────────────────┘
          │                  │
┌─────────┼──────────────────┼─────────────────────────────────────────────┐
│         │     Portfolio Plugin                                            │
│         │                  │                                              │
│  ┌──────▼───────┐   ┌─────▼──────────┐   ┌───────────────┐              │
│  │ portfolios   │   │ Event Listener │   │  JSON-RPC API │              │
│  │ (new table)  │   │                │   │  (~28 methods)│              │
│  └──────┬───────┘   │ on task.close: │   └───────┬───────┘              │
│         │           │  check deps →  │           │                      │
│  ┌──────▼───────┐   │  fire event →  │   ┌───────▼───────┐              │
│  │ portfolio_   │   │  notify        │   │  Controllers  │              │
│  │ has_projects │   └────────────────┘   │  (5 classes)  │              │
│  │ (new table)  │                        └───────┬───────┘              │
│  └──────────────┘                                │                      │
│                                          ┌───────▼───────┐              │
│  ┌──────────────┐   ┌──────────────┐     │   Templates   │              │
│  │  milestones  │   │ milestone_   │     │   + Hooks     │              │
│  │ (new table)  │   │ has_tasks    │     │   + Assets    │              │
│  └──────────────┘   │ (new table)  │     └───────────────┘              │
│                     └──────────────┘                                     │
│                                                                          │
│  Data Flow:                                                              │
│  1. Models query plugin tables + JOIN to core tables (tasks, projects)  │
│  2. Controllers call models, pass data to templates                     │
│  3. API methods call models, return JSON-RPC responses                  │
│  4. Template hooks inject widgets into Kanboard's existing pages        │
│  5. Event listeners react to core events, compute dependency status     │
└──────────────────────────────────────────────────────────────────────────┘
```

### 2.3 Key Architectural Decisions

| # | Decision | Rationale |
|---|----------|-----------|
| AD-1 | **No new dependency storage.** Reuse `task_has_links` with the existing "blocks"/"is blocked by" link types. | Dependencies created through any channel (Kanboard UI, API, other plugins) are automatically visible. Zero migration of existing data. |
| AD-2 | **Milestones are first-class entities, not tasks.** Store in a dedicated `milestones` table. | Milestones have different semantics than tasks (no column, no assignee, aggregate progress). Using tasks as milestones (as the existing Milestone plugin does) conflates distinct concepts. |
| AD-3 | **PicoDb for all queries.** Use Kanboard's built-in query builder (`$this->db->table(...)`) rather than raw SQL. | Cross-database compatibility (SQLite, MySQL, PostgreSQL) without maintaining three SQL dialects per query. |
| AD-4 | **All features accessible via JSON-RPC API.** Every model method has a corresponding API endpoint. | Enables the companion CLI/SDK, third-party integrations, and testing. Kanboard's own API is designed this way. |
| AD-5 | **D3.js bundled, not CDN.** Ship `d3.v7.min.js` in `Asset/js/`. | Kanboard instances may be air-gapped or behind firewalls. No external CDN dependencies. |
| AD-6 | **Template hooks only, no template overrides.** Never call `setTemplateOverride()`. | Minimizes conflict risk with other plugins and Kanboard upgrades. All UI integration via documented hook points. |

---

## 3. Data Model

### 3.1 Schema Version Constant

```php
const VERSION = 1;
```

### 3.2 Table: `portfolios`

Stores portfolio definitions. Portfolios are global entities owned by a user.

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | INTEGER | PRIMARY KEY, AUTOINCREMENT | — | Unique identifier |
| `name` | TEXT | NOT NULL, UNIQUE | — | Display name (e.g., "Q2 2026 Launch") |
| `description` | TEXT | — | `''` | Optional description/purpose |
| `owner_id` | INTEGER | NOT NULL | `0` | User ID of the portfolio creator |
| `is_active` | INTEGER | NOT NULL | `1` | `1` = active, `0` = archived |
| `created_at` | INTEGER | NOT NULL | `0` | Unix timestamp of creation |
| `updated_at` | INTEGER | NOT NULL | `0` | Unix timestamp of last modification |

**DDL (SQLite):**

```sql
CREATE TABLE IF NOT EXISTS portfolios (
    "id" INTEGER PRIMARY KEY,
    "name" TEXT NOT NULL,
    "description" TEXT DEFAULT '',
    "owner_id" INTEGER NOT NULL DEFAULT 0,
    "is_active" INTEGER NOT NULL DEFAULT 1,
    "created_at" INTEGER NOT NULL DEFAULT 0,
    "updated_at" INTEGER NOT NULL DEFAULT 0,
    UNIQUE(name)
);
```

**DDL (MySQL):**

```sql
CREATE TABLE IF NOT EXISTS portfolios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    owner_id INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at INT NOT NULL DEFAULT 0,
    updated_at INT NOT NULL DEFAULT 0,
    UNIQUE KEY uk_portfolio_name (name)
) ENGINE=InnoDB CHARSET=utf8mb4;
```

**DDL (PostgreSQL):**

```sql
CREATE TABLE IF NOT EXISTS portfolios (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT '',
    owner_id INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at INTEGER NOT NULL DEFAULT 0,
    updated_at INTEGER NOT NULL DEFAULT 0,
    UNIQUE(name)
);
```

### 3.3 Table: `portfolio_has_projects`

Junction table. Many-to-many between portfolios and projects. A project may appear in multiple portfolios. A portfolio contains multiple projects.

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `portfolio_id` | INTEGER | NOT NULL, FK → `portfolios(id)` ON DELETE CASCADE | — | Parent portfolio |
| `project_id` | INTEGER | NOT NULL, FK → `projects(id)` ON DELETE CASCADE | — | Member project |
| `position` | INTEGER | NOT NULL | `0` | Display order within portfolio |
| `added_at` | INTEGER | NOT NULL | `0` | Unix timestamp when membership was created |

**Primary key:** `(portfolio_id, project_id)`

**DDL (SQLite):**

```sql
CREATE TABLE IF NOT EXISTS portfolio_has_projects (
    "portfolio_id" INTEGER NOT NULL,
    "project_id" INTEGER NOT NULL,
    "position" INTEGER NOT NULL DEFAULT 0,
    "added_at" INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (portfolio_id, project_id),
    FOREIGN KEY(portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_php_portfolio ON portfolio_has_projects(portfolio_id);
CREATE INDEX IF NOT EXISTS idx_php_project ON portfolio_has_projects(project_id);
```

### 3.4 Table: `milestones`

First-class cross-project milestone entities, scoped to a portfolio.

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `id` | INTEGER | PRIMARY KEY, AUTOINCREMENT | — | Unique identifier |
| `portfolio_id` | INTEGER | NOT NULL, FK → `portfolios(id)` ON DELETE CASCADE | — | Owning portfolio |
| `name` | TEXT | NOT NULL | — | Milestone name (e.g., "v2.0 Feature Complete") |
| `description` | TEXT | — | `''` | Description |
| `target_date` | INTEGER | — | `0` | Target completion date as Unix timestamp |
| `status` | INTEGER | NOT NULL | `1` | `1` = active, `0` = completed, `2` = cancelled |
| `color_id` | TEXT | — | `'blue'` | Kanboard color identifier for display |
| `owner_id` | INTEGER | NOT NULL | `0` | Responsible user ID |
| `created_at` | INTEGER | NOT NULL | `0` | Unix timestamp |
| `updated_at` | INTEGER | NOT NULL | `0` | Unix timestamp |

**Constraint:** Within a portfolio, milestone names must be unique. Enforced at the application layer (not DB unique constraint, since the combination is `portfolio_id` + `name`).

**DDL (SQLite):**

```sql
CREATE TABLE IF NOT EXISTS milestones (
    "id" INTEGER PRIMARY KEY,
    "portfolio_id" INTEGER NOT NULL,
    "name" TEXT NOT NULL,
    "description" TEXT DEFAULT '',
    "target_date" INTEGER DEFAULT 0,
    "status" INTEGER NOT NULL DEFAULT 1,
    "color_id" TEXT DEFAULT 'blue',
    "owner_id" INTEGER NOT NULL DEFAULT 0,
    "created_at" INTEGER NOT NULL DEFAULT 0,
    "updated_at" INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY(portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_milestones_portfolio ON milestones(portfolio_id);
CREATE INDEX IF NOT EXISTS idx_milestones_status ON milestones(status);
```

### 3.5 Table: `milestone_has_tasks`

Junction table. Many-to-many between milestones and tasks. A task may appear in multiple milestones (even across portfolios). This is intentional — a task could be relevant to both "v2.0 Feature Complete" and "Q2 Launch Ready".

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| `milestone_id` | INTEGER | NOT NULL, FK → `milestones(id)` ON DELETE CASCADE | — | Parent milestone |
| `task_id` | INTEGER | NOT NULL, FK → `tasks(id)` ON DELETE CASCADE | — | Member task |
| `position` | INTEGER | NOT NULL | `0` | Display/sequence order within milestone |
| `is_critical` | INTEGER | NOT NULL | `0` | `1` = task is on the critical path for this milestone |
| `added_at` | INTEGER | NOT NULL | `0` | Unix timestamp |

**Primary key:** `(milestone_id, task_id)`

**DDL (SQLite):**

```sql
CREATE TABLE IF NOT EXISTS milestone_has_tasks (
    "milestone_id" INTEGER NOT NULL,
    "task_id" INTEGER NOT NULL,
    "position" INTEGER NOT NULL DEFAULT 0,
    "is_critical" INTEGER NOT NULL DEFAULT 0,
    "added_at" INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (milestone_id, task_id),
    FOREIGN KEY(milestone_id) REFERENCES milestones(id) ON DELETE CASCADE,
    FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_mht_milestone ON milestone_has_tasks(milestone_id);
CREATE INDEX IF NOT EXISTS idx_mht_task ON milestone_has_tasks(task_id);
```

### 3.6 Entity Relationships

```
portfolios         ──1:N──>  portfolio_has_projects  ──N:1──>  projects (core)
portfolios         ──1:N──>  milestones
milestones         ──1:N──>  milestone_has_tasks     ──N:1──>  tasks (core)
tasks (core)       ──N:N──>  tasks (core)            via       task_has_links (core)
```

The plugin **reads** from the core `tasks`, `projects`, `users`, `columns`, `task_has_links`, and `links` tables via JOINs. It **writes** only to its own four tables.

### 3.7 Migration File Structure

Each database driver file (`Schema/Sqlite.php`, `Schema/Mysql.php`, `Schema/Postgres.php`) follows this structure:

```php
<?php
namespace Kanboard\Plugin\Portfolio\Schema;

const VERSION = 1;

function version_1(\PDO $pdo)
{
    // Create all four tables and indexes
    // (DDL appropriate for the database driver)
}
```

Future migrations increment the version and add `version_2()`, `version_3()`, etc.

---

## 4. API Surface / Interface Contract

All methods are registered as JSON-RPC 2.0 procedures via `$this->api->getProcedureHandler()->withCallback()` in `Plugin.php`. Method names use `camelCase` consistent with Kanboard's core API.

### 4.1 Portfolio CRUD (6 methods)

#### `createPortfolio`

| | |
|---|---|
| **Purpose** | Create a new portfolio |
| **Auth** | Application API: `app-manager` or `app-admin` role required |

**Parameters:**

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `name` | string | **yes** | — | Unique portfolio name. Max 255 characters. |
| `description` | string | no | `""` | Portfolio description |
| `owner_id` | integer | no | Authenticated user ID, or `0` for app-level API | Owning user |

**Returns:** `integer` (portfolio ID) on success, `false` on failure.

**Failure conditions:**
- Name is empty or exceeds 255 characters → `false`
- Name already exists → `false`
- Caller lacks `app-manager` or `app-admin` role → JSON-RPC error

---

#### `getPortfolio`

| | |
|---|---|
| **Purpose** | Retrieve a single portfolio by ID |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `portfolio_id` | integer | **yes** | Portfolio ID |

**Returns:** Portfolio dict on success, `null` if not found.

**Response dict shape:**

```json
{
    "id": "1",
    "name": "Q2 2026 Launch",
    "description": "Coordinated product and marketing launch",
    "owner_id": "1",
    "is_active": "1",
    "created_at": "1711100000",
    "updated_at": "1711100000"
}
```

Note: All values are returned as strings, following Kanboard's convention for SQLite compatibility.

---

#### `getPortfolioByName`

| | |
|---|---|
| **Purpose** | Retrieve a portfolio by its unique name |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `name` | string | **yes** | Portfolio name (exact match, case-sensitive) |

**Returns:** Portfolio dict on success, `null` if not found.

---

#### `getAllPortfolios`

| | |
|---|---|
| **Purpose** | List all portfolios |
| **Auth** | Any authenticated user (returns all portfolios; filtering by access is done client-side) |

**Parameters:** None.

**Returns:** `array` of portfolio dicts. Returns an empty array `[]` if none exist (never `false`).

---

#### `updatePortfolio`

| | |
|---|---|
| **Purpose** | Update an existing portfolio |
| **Auth** | `app-manager` or `app-admin` role, OR user is the portfolio owner |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `portfolio_id` | integer | **yes** | Portfolio ID |
| `name` | string | no | New name (must be unique if changed) |
| `description` | string | no | New description |
| `owner_id` | integer | no | New owner |
| `is_active` | integer | no | `1` = active, `0` = archived |

**Returns:** `true` on success, `false` on failure.

**Failure conditions:**
- Portfolio does not exist → `false`
- New name conflicts with existing portfolio → `false`
- Caller lacks permission → JSON-RPC error

---

#### `removePortfolio`

| | |
|---|---|
| **Purpose** | Delete a portfolio and all associated milestones |
| **Auth** | `app-admin` role, OR `app-manager` who is the portfolio owner |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `portfolio_id` | integer | **yes** | Portfolio ID |

**Returns:** `true` on success, `false` on failure.

**Cascade:** Deleting a portfolio cascades to `portfolio_has_projects`, `milestones`, and `milestone_has_tasks`. Core `projects` and `tasks` tables are unaffected.

---

### 4.2 Portfolio ↔ Project Membership (4 methods)

#### `addProjectToPortfolio`

| | |
|---|---|
| **Purpose** | Add a project to a portfolio |
| **Auth** | `app-manager` or `app-admin`, OR portfolio owner |

**Parameters:**

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `portfolio_id` | integer | **yes** | — | Portfolio ID |
| `project_id` | integer | **yes** | — | Project ID (must exist in `projects` table) |
| `position` | integer | no | `0` | Display order position |

**Returns:** `true` on success, `false` on failure.

**Failure conditions:**
- Portfolio does not exist → `false`
- Project does not exist → `false`
- Project is already in the portfolio → `false`

---

#### `removeProjectFromPortfolio`

| | |
|---|---|
| **Purpose** | Remove a project from a portfolio |
| **Auth** | `app-manager` or `app-admin`, OR portfolio owner |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `portfolio_id` | integer | **yes** | Portfolio ID |
| `project_id` | integer | **yes** | Project ID |

**Returns:** `true` on success, `false` on failure.

**Side effect:** When a project is removed from a portfolio, all tasks from that project are also removed from all milestones within that portfolio. This is implemented in the model layer, not via DB cascade (since `milestone_has_tasks` references `tasks.id`, not `projects.id`).

---

#### `getPortfolioProjects`

| | |
|---|---|
| **Purpose** | List all projects in a portfolio with membership metadata |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `portfolio_id` | integer | **yes** | Portfolio ID |

**Returns:** Array of project dicts with additional fields. Returns `[]` if portfolio is empty.

**Response dict shape (per project):**

```json
{
    "id": "3",
    "name": "Product Alpha",
    "is_active": "1",
    "description": "...",
    "position": "1",
    "added_at": "1711100000"
}
```

The response JOINs `portfolio_has_projects` with `projects` to include both project fields and membership fields (`position`, `added_at`).

**SQL (PicoDb):**

```php
$this->db->table('portfolio_has_projects')
    ->columns(
        'projects.id',
        'projects.name',
        'projects.is_active',
        'projects.description',
        'portfolio_has_projects.position',
        'portfolio_has_projects.added_at'
    )
    ->join('projects', 'id', 'project_id')
    ->eq('portfolio_has_projects.portfolio_id', $portfolioId)
    ->asc('portfolio_has_projects.position')
    ->findAll();
```

---

#### `getProjectPortfolios`

| | |
|---|---|
| **Purpose** | List all portfolios that contain a given project |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `project_id` | integer | **yes** | Project ID |

**Returns:** Array of portfolio dicts. Returns `[]` if project belongs to no portfolios.

---

### 4.3 Milestone CRUD (5 methods)

#### `createMilestone`

| | |
|---|---|
| **Purpose** | Create a milestone within a portfolio |
| **Auth** | `app-manager` or `app-admin`, OR portfolio owner |

**Parameters:**

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `portfolio_id` | integer | **yes** | — | Owning portfolio ID |
| `name` | string | **yes** | — | Milestone name (unique within portfolio) |
| `description` | string | no | `""` | Description |
| `target_date` | string | no | `null` | Target date as `"YYYY-MM-DD"` or Unix timestamp string |
| `color_id` | string | no | `"blue"` | Kanboard color identifier |
| `owner_id` | integer | no | `0` | Responsible user ID |

**Returns:** `integer` (milestone ID) on success, `false` on failure.

**Failure conditions:**
- Portfolio does not exist → `false`
- Name already exists within the same portfolio → `false`
- Name is empty → `false`

**Date handling:** If `target_date` is provided as `"YYYY-MM-DD"`, convert to Unix timestamp for storage. If provided as a numeric string, store directly.

---

#### `getMilestone`

| | |
|---|---|
| **Purpose** | Retrieve a single milestone by ID |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `milestone_id` | integer | **yes** | Milestone ID |

**Returns:** Milestone dict on success, `null` if not found.

**Response dict shape:**

```json
{
    "id": "1",
    "portfolio_id": "1",
    "name": "v2.0 Feature Complete",
    "description": "All v2.0 features implemented and tested",
    "target_date": "1717200000",
    "status": "1",
    "color_id": "blue",
    "owner_id": "1",
    "created_at": "1711100000",
    "updated_at": "1711100000"
}
```

---

#### `getPortfolioMilestones`

| | |
|---|---|
| **Purpose** | List all milestones in a portfolio |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `portfolio_id` | integer | **yes** | Portfolio ID |

**Returns:** Array of milestone dicts, ordered by `target_date` ascending. Returns `[]` if none exist.

---

#### `updateMilestone`

| | |
|---|---|
| **Purpose** | Update an existing milestone |
| **Auth** | `app-manager` or `app-admin`, OR portfolio owner |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `milestone_id` | integer | **yes** | Milestone ID |
| `name` | string | no | New name (must be unique within portfolio if changed) |
| `description` | string | no | New description |
| `target_date` | string | no | New target date (`"YYYY-MM-DD"` or timestamp) |
| `color_id` | string | no | New color |
| `owner_id` | integer | no | New owner |
| `status` | integer | no | `1` = active, `0` = completed, `2` = cancelled |

**Returns:** `true` on success, `false` on failure.

---

#### `removeMilestone`

| | |
|---|---|
| **Purpose** | Delete a milestone |
| **Auth** | `app-admin`, OR `app-manager` who is the portfolio owner |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `milestone_id` | integer | **yes** | Milestone ID |

**Returns:** `true` on success, `false` on failure.

**Cascade:** Deleting a milestone cascades to `milestone_has_tasks`. Core `tasks` are unaffected.

---

### 4.4 Milestone ↔ Task Membership (5 methods)

#### `addTaskToMilestone`

| | |
|---|---|
| **Purpose** | Add a task to a milestone |
| **Auth** | `app-manager` or `app-admin`, OR portfolio owner |

**Parameters:**

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `milestone_id` | integer | **yes** | — | Milestone ID |
| `task_id` | integer | **yes** | — | Task ID |
| `is_critical` | integer | no | `0` | `1` = critical path task |
| `position` | integer | no | `0` | Display order |

**Returns:** `true` on success, `false` on failure.

**Validation:** The task's `project_id` must be a member of the milestone's portfolio. If not, return `false`.

**Validation query (PicoDb):**

```php
$exists = $this->db->table('portfolio_has_projects')
    ->eq('portfolio_id', $milestone['portfolio_id'])
    ->eq('project_id', $task['project_id'])
    ->exists();
```

---

#### `removeTaskFromMilestone`

| | |
|---|---|
| **Purpose** | Remove a task from a milestone |
| **Auth** | `app-manager` or `app-admin`, OR portfolio owner |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `milestone_id` | integer | **yes** | Milestone ID |
| `task_id` | integer | **yes** | Task ID |

**Returns:** `true` on success, `false` on failure.

---

#### `getMilestoneTasks`

| | |
|---|---|
| **Purpose** | List all tasks in a milestone with enriched task data |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `milestone_id` | integer | **yes** | Milestone ID |

**Returns:** Array of task dicts with additional fields. Returns `[]` if empty.

**Response dict shape (per task):**

```json
{
    "id": "42",
    "title": "Publish product page",
    "project_id": "8",
    "project_name": "Site Project",
    "column_id": "15",
    "column_title": "Backlog",
    "owner_id": "5",
    "assignee_username": "eve",
    "assignee_name": "Eve Smith",
    "is_active": "1",
    "date_due": "1717200000",
    "priority": "2",
    "color_id": "yellow",
    "position": "0",
    "is_critical": "1",
    "added_at": "1711100000"
}
```

**SQL (PicoDb):**

```php
$this->db->table('milestone_has_tasks')
    ->columns(
        'tasks.id',
        'tasks.title',
        'tasks.project_id',
        'projects.name AS project_name',
        'tasks.column_id',
        'columns.title AS column_title',
        'tasks.owner_id',
        'users.username AS assignee_username',
        'users.name AS assignee_name',
        'tasks.is_active',
        'tasks.date_due',
        'tasks.priority',
        'tasks.color_id',
        'milestone_has_tasks.position',
        'milestone_has_tasks.is_critical',
        'milestone_has_tasks.added_at'
    )
    ->join('tasks', 'id', 'task_id')
    ->join('projects', 'id', 'project_id', 'tasks')
    ->join('columns', 'id', 'column_id', 'tasks')
    ->left('users', 'id', 'owner_id', 'tasks')
    ->eq('milestone_has_tasks.milestone_id', $milestoneId)
    ->asc('milestone_has_tasks.position')
    ->findAll();
```

---

#### `getTaskMilestones`

| | |
|---|---|
| **Purpose** | List all milestones that contain a given task |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `task_id` | integer | **yes** | Task ID |

**Returns:** Array of milestone dicts with membership metadata. Returns `[]` if task has no milestones.

---

#### `getMilestoneProgress`

| | |
|---|---|
| **Purpose** | Compute progress metrics for a milestone |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `milestone_id` | integer | **yes** | Milestone ID |

**Returns:** Progress dict, or `null` if milestone not found.

**Response dict shape:**

```json
{
    "milestone_id": "1",
    "milestone_name": "v2.0 Feature Complete",
    "portfolio_id": "1",
    "total": 5,
    "completed": 3,
    "percent": 60.0,
    "is_at_risk": true,
    "is_overdue": false,
    "blocked_count": 1,
    "target_date": "1717200000"
}
```

**Computation logic:**

```
total       = COUNT(milestone_has_tasks WHERE milestone_id = ?)
completed   = COUNT(milestone_has_tasks JOIN tasks WHERE tasks.is_active = 0)
percent     = (completed / total) * 100   (0.0 if total = 0)
is_overdue  = target_date > 0 AND target_date < NOW() AND percent < 100
is_at_risk  = NOT is_overdue
              AND target_date > 0
              AND target_date < (NOW() + 7 days)
              AND percent < 80
blocked_count = COUNT of milestone tasks that have unresolved "is blocked by" links
```

The `blocked_count` query:

```php
// For each task in the milestone, check if it has any "is blocked by" links
// where the opposite task is still active (is_active = 1)
$this->db->table('milestone_has_tasks')
    ->join('task_has_links', 'task_id', 'task_id', 'milestone_has_tasks')
    ->join('tasks', 'id', 'opposite_task_id', 'task_has_links')
    ->eq('milestone_has_tasks.milestone_id', $milestoneId)
    ->eq('task_has_links.link_id', $blockedByLinkId)  // link_id for "is blocked by"
    ->eq('tasks.is_active', 1)
    ->count();
```

---

### 4.5 Cross-Project Dependency Queries (5 methods)

These methods query the **existing** `task_has_links` table, scoped to a portfolio's projects.

#### `getPortfolioDependencies`

| | |
|---|---|
| **Purpose** | List all dependency edges in a portfolio |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `portfolio_id` | integer | **yes** | — | Portfolio ID |
| `cross_project_only` | boolean | no | `true` | If true, only return links where tasks are in different projects |

**Returns:** Array of dependency edge dicts. Returns `[]` if none.

**Response dict shape (per edge):**

```json
{
    "link_id": "5",
    "link_label": "blocks",
    "task_id": "15",
    "task_title": "Finalize branding",
    "task_project_id": "3",
    "task_project_name": "Product Alpha",
    "task_is_active": "1",
    "opposite_task_id": "42",
    "opposite_task_title": "Publish product page",
    "opposite_task_project_id": "8",
    "opposite_task_project_name": "Site Project",
    "opposite_task_is_active": "1",
    "is_cross_project": true,
    "is_resolved": false
}
```

**SQL strategy:**

```php
// Get all project IDs in portfolio
$projectIds = $this->portfolioProjectModel->getProjectIds($portfolioId);

// Query task_has_links for tasks in those projects
$query = $this->db->table('task_has_links')
    ->columns(
        'task_has_links.id AS link_id',
        'links.label AS link_label',
        't1.id AS task_id',
        't1.title AS task_title',
        't1.project_id AS task_project_id',
        'p1.name AS task_project_name',
        't1.is_active AS task_is_active',
        't2.id AS opposite_task_id',
        't2.title AS opposite_task_title',
        't2.project_id AS opposite_task_project_id',
        'p2.name AS opposite_task_project_name',
        't2.is_active AS opposite_task_is_active'
    )
    ->join('links', 'id', 'link_id')
    ->join('tasks AS t1', 'id', 'task_id', 'task_has_links')
    ->join('tasks AS t2', 'id', 'opposite_task_id', 'task_has_links')
    ->join('projects AS p1', 'id', 'project_id', 't1')
    ->join('projects AS p2', 'id', 'project_id', 't2')
    ->in('t1.project_id', $projectIds)
    ->in('links.id', [$blocksLinkId, $blockedByLinkId]);  // Only dependency link types

if ($crossProjectOnly) {
    $query->addCondition('t1.project_id != t2.project_id');
}

return $query->findAll();
```

**Note on PicoDb table aliasing:** PicoDb's `join()` method may not support table aliases for all database drivers. If aliasing is problematic, use raw SQL via `$this->db->execute()` with parameterized queries. Test all three drivers.

---

#### `getBlockedTasks`

| | |
|---|---|
| **Purpose** | List tasks in the portfolio that are blocked by unresolved dependencies |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `portfolio_id` | integer | **yes** | Portfolio ID |

**Returns:** Array of task dicts with blocker information. Each dict includes the task's fields plus a `blockers` array listing the blocking tasks.

**Response dict shape (per task):**

```json
{
    "id": "42",
    "title": "Publish product page",
    "project_id": "8",
    "project_name": "Site Project",
    "is_active": "1",
    "blockers": [
        {
            "task_id": "15",
            "task_title": "Finalize branding",
            "project_id": "3",
            "project_name": "Product Alpha",
            "is_active": "1"
        }
    ]
}
```

**Logic:** A task is "blocked" if it has a `task_has_links` entry with `link_id` = "is blocked by" (link ID 3) where the `opposite_task` is still active (`is_active = 1`).

---

#### `getBlockingTasks`

| | |
|---|---|
| **Purpose** | List open tasks in the portfolio that are blocking other tasks |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `portfolio_id` | integer | **yes** | Portfolio ID |

**Returns:** Array of task dicts with a `blocking` array listing the tasks they block.

---

#### `getPortfolioCriticalPath`

| | |
|---|---|
| **Purpose** | Compute the critical path through the portfolio's dependency graph |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `portfolio_id` | integer | **yes** | Portfolio ID |

**Returns:** Ordered array of task dicts representing the longest unresolved dependency chain. Returns `[]` if no dependencies exist.

**Algorithm:**

1. Build a directed graph from all "blocks" edges where both tasks are active.
2. Perform topological sort (Kahn's algorithm). If cycles are detected, log a warning and break the cycle at the last-added edge.
3. Compute longest path in the DAG using dynamic programming.
4. Return the path as an ordered list of task dicts (first task = root blocker, last task = final dependent).

**Response includes:** Each task dict plus `chain_position` (1-indexed) and `downstream_count` (number of tasks transitively blocked by this task).

---

#### `getPortfolioDependencyGraph`

| | |
|---|---|
| **Purpose** | Return a structured graph suitable for client-side rendering |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `portfolio_id` | integer | **yes** | — | Portfolio ID |
| `cross_project_only` | boolean | no | `true` | Only cross-project edges |

**Returns:** Dict with `nodes` and `edges` arrays.

```json
{
    "nodes": [
        {
            "id": 15,
            "title": "Finalize branding",
            "project_id": 3,
            "project_name": "Product Alpha",
            "is_active": 1,
            "priority": 2,
            "color_id": "green",
            "assignee": "alice"
        }
    ],
    "edges": [
        {
            "source": 15,
            "target": 42,
            "label": "blocks",
            "is_resolved": false
        }
    ],
    "critical_path": [15, 42, 88, 95]
}
```

---

### 4.6 Unified Task Queries (3 methods)

#### `getPortfolioTasks`

| | |
|---|---|
| **Purpose** | Unified, filterable task list across all projects in a portfolio |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `portfolio_id` | integer | **yes** | — | Portfolio ID |
| `status_id` | integer | no | — | `1` = active, `0` = closed. If omitted, return all. |
| `assignee_id` | integer | no | — | Filter by task owner |
| `project_id` | integer | no | — | Filter to a single project within the portfolio |
| `milestone_id` | integer | no | — | Filter to tasks in a specific milestone |
| `has_dependencies` | boolean | no | — | If `true`, only tasks with cross-project dependency links |
| `sort` | string | no | `"priority"` | Sort field: `"priority"`, `"date_due"`, `"project"`, `"date_creation"` |
| `direction` | string | no | `"DESC"` | `"ASC"` or `"DESC"` |
| `limit` | integer | no | `50` | Max results per page |
| `offset` | integer | no | `0` | Pagination offset |

**Returns:** Array of enriched task dicts. Returns `[]` if empty.

**Response dict shape (per task):**

```json
{
    "id": "42",
    "title": "Publish product page",
    "project_id": "8",
    "project_name": "Site Project",
    "column_id": "15",
    "column_title": "Backlog",
    "owner_id": "5",
    "assignee_username": "eve",
    "assignee_name": "Eve Smith",
    "is_active": "1",
    "date_due": "1717200000",
    "date_creation": "1711100000",
    "priority": "2",
    "score": "0",
    "color_id": "yellow",
    "category_id": "0",
    "swimlane_id": "0",
    "is_blocked": true,
    "blocked_by_count": 1
}
```

Note the computed fields `is_blocked` and `blocked_by_count` — these are added by a subquery.

---

#### `getPortfolioTaskCount`

| | |
|---|---|
| **Purpose** | Aggregate task counts for a portfolio |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `portfolio_id` | integer | **yes** | Portfolio ID |

**Returns:** Count dict.

```json
{
    "total": 47,
    "active": 35,
    "closed": 12,
    "blocked": 8
}
```

---

#### `getPortfolioOverview`

| | |
|---|---|
| **Purpose** | Comprehensive portfolio summary for dashboard views |
| **Auth** | Any authenticated user |

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `portfolio_id` | integer | **yes** | Portfolio ID |

**Returns:** Summary dict.

```json
{
    "portfolio": { /* portfolio dict */ },
    "project_count": 3,
    "projects": [ /* abbreviated project dicts */ ],
    "task_counts": { "total": 47, "active": 35, "closed": 12, "blocked": 8 },
    "milestones": [
        {
            "id": "1",
            "name": "v2.0 Feature Complete",
            "target_date": "1717200000",
            "percent": 60.0,
            "is_at_risk": true,
            "is_overdue": false
        }
    ],
    "at_risk_milestones": 1,
    "overdue_milestones": 0,
    "critical_path_length": 4
}
```

---

## 5. Controller & Route Specification

### 5.1 Route Registration

All routes are registered in `Plugin.php::initialize()`:

```php
// Portfolio routes
$this->route->addRoute('/portfolios', 'PortfolioListController', 'index', 'Portfolio');
$this->route->addRoute('/portfolio/create', 'PortfolioModificationController', 'create', 'Portfolio');
$this->route->addRoute('/portfolio/:portfolio_id', 'PortfolioViewController', 'show', 'Portfolio');
$this->route->addRoute('/portfolio/:portfolio_id/tasks', 'PortfolioViewController', 'tasks', 'Portfolio');
$this->route->addRoute('/portfolio/:portfolio_id/board', 'PortfolioViewController', 'board', 'Portfolio');
$this->route->addRoute('/portfolio/:portfolio_id/timeline', 'PortfolioViewController', 'timeline', 'Portfolio');
$this->route->addRoute('/portfolio/:portfolio_id/dependencies', 'DependencyController', 'graph', 'Portfolio');
$this->route->addRoute('/portfolio/:portfolio_id/blocked', 'DependencyController', 'blocked', 'Portfolio');
$this->route->addRoute('/portfolio/:portfolio_id/critical-path', 'DependencyController', 'criticalPath', 'Portfolio');
$this->route->addRoute('/portfolio/:portfolio_id/milestones', 'MilestoneController', 'index', 'Portfolio');
$this->route->addRoute('/portfolio/:portfolio_id/settings', 'PortfolioModificationController', 'settings', 'Portfolio');
$this->route->addRoute('/portfolio/:portfolio_id/edit', 'PortfolioModificationController', 'edit', 'Portfolio');
$this->route->addRoute('/portfolio/:portfolio_id/remove', 'PortfolioModificationController', 'remove', 'Portfolio');

// Milestone routes
$this->route->addRoute('/milestone/create/:portfolio_id', 'MilestoneController', 'create', 'Portfolio');
$this->route->addRoute('/milestone/:milestone_id', 'MilestoneController', 'show', 'Portfolio');
$this->route->addRoute('/milestone/:milestone_id/edit', 'MilestoneController', 'edit', 'Portfolio');
$this->route->addRoute('/milestone/:milestone_id/remove', 'MilestoneController', 'remove', 'Portfolio');
```

### 5.2 Access Map Registration

```php
// Application-level access (portfolio is not project-scoped)
$this->applicationAccessMap->add('PortfolioListController', '*', Role::APP_USER);
$this->applicationAccessMap->add('PortfolioViewController', '*', Role::APP_USER);
$this->applicationAccessMap->add('DependencyController', '*', Role::APP_USER);
$this->applicationAccessMap->add('MilestoneController', array('index', 'show'), Role::APP_USER);
$this->applicationAccessMap->add('MilestoneController', array('create', 'edit', 'remove', 'save'), Role::APP_MANAGER);
$this->applicationAccessMap->add('PortfolioModificationController', '*', Role::APP_MANAGER);

// API access map
$this->apiAccessMap->add('PortfolioProcedure', array('createPortfolio', 'updatePortfolio', 'removePortfolio'), Role::APP_MANAGER);
$this->apiAccessMap->add('PortfolioProcedure', array('addProjectToPortfolio', 'removeProjectFromPortfolio'), Role::APP_MANAGER);
$this->apiAccessMap->add('MilestoneProcedure', array('createMilestone', 'updateMilestone', 'removeMilestone'), Role::APP_MANAGER);
$this->apiAccessMap->add('MilestoneProcedure', array('addTaskToMilestone', 'removeTaskFromMilestone'), Role::APP_MANAGER);
```

Read-only API methods (`getPortfolio`, `getAllPortfolios`, `getPortfolioTasks`, etc.) use the default role `APP_USER` and are not listed explicitly in the access map.

### 5.3 Controller Classes

All controllers extend `Kanboard\Core\Base` (via the plugin's own base or directly) and access models through the DI container.

#### `PortfolioListController`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /portfolios` | Render list of all portfolios |

#### `PortfolioViewController`

| Method | Route | Description |
|--------|-------|-------------|
| `show()` | `GET /portfolio/:portfolio_id` | Portfolio dashboard |
| `tasks()` | `GET /portfolio/:portfolio_id/tasks` | Unified task list |
| `board()` | `GET /portfolio/:portfolio_id/board` | Aggregate board |
| `timeline()` | `GET /portfolio/:portfolio_id/timeline` | Gantt timeline |

All methods: fetch `portfolio_id` via `$this->request->getIntegerParam('portfolio_id')`, validate portfolio exists, render template with model data.

#### `PortfolioModificationController`

| Method | Route | Description |
|--------|-------|-------------|
| `create()` | `GET /portfolio/create` | Show create form |
| `save()` | `POST` | Process create form (redirects to `/portfolio/:id`) |
| `edit()` | `GET /portfolio/:portfolio_id/edit` | Show edit form |
| `update()` | `POST` | Process edit form |
| `settings()` | `GET /portfolio/:portfolio_id/settings` | Project membership management |
| `addProject()` | `POST` | Add project to portfolio |
| `removeProject()` | `POST` | Remove project from portfolio |
| `remove()` | `GET /portfolio/:portfolio_id/remove` | Show delete confirmation |
| `delete()` | `POST` | Process deletion |

All write methods validate CSRF token: `$this->checkCSRFParam()`.

#### `MilestoneController`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /portfolio/:portfolio_id/milestones` | Milestone list |
| `show()` | `GET /milestone/:milestone_id` | Milestone detail with task list + progress |
| `create()` | `GET /milestone/create/:portfolio_id` | Create form |
| `save()` | `POST` | Process create |
| `edit()` | `GET /milestone/:milestone_id/edit` | Edit form |
| `update()` | `POST` | Process edit |
| `remove()` | `GET /milestone/:milestone_id/remove` | Delete confirmation |
| `delete()` | `POST` | Process deletion |
| `addTask()` | `POST` | Add task to milestone (AJAX) |
| `removeTask()` | `POST` | Remove task from milestone (AJAX) |

#### `DependencyController`

| Method | Route | Description |
|--------|-------|-------------|
| `graph()` | `GET /portfolio/:portfolio_id/dependencies` | D3.js dependency graph page |
| `graphData()` | `GET` (AJAX) | JSON graph data for D3 rendering |
| `blocked()` | `GET /portfolio/:portfolio_id/blocked` | Blocked tasks list |
| `criticalPath()` | `GET /portfolio/:portfolio_id/critical-path` | Critical path view |

---

## 6. Template & UI Specification

### 6.1 Template Hook Registrations

Registered in `Plugin.php::initialize()`:

```php
// Dashboard
$this->template->hook->attach(
    'template:dashboard:sidebar',
    'Portfolio:widget/dashboard_sidebar'
);
$this->template->hook->attachCallable(
    'template:dashboard:show',
    'Portfolio:widget/dashboard_milestones',
    function () {
        return ['milestones' => $this->container['portfolioModel']->getAtRiskMilestones()];
    }
);

// Board task indicators
$this->template->hook->attachCallable(
    'template:board:task:icons',
    'Portfolio:widget/board_task_icons',
    function (array $task) {
        return ['is_blocked' => $this->container['dependencyModel']->isTaskBlocked($task['id'])];
    }
);
$this->template->hook->attachCallable(
    'template:board:task:footer',
    'Portfolio:widget/board_task_footer',
    function (array $task) {
        return ['blockers' => $this->container['dependencyModel']->getTaskBlockers($task['id'])];
    }
);

// Task detail
$this->template->hook->attachCallable(
    'template:task:sidebar:information',
    'Portfolio:widget/task_sidebar',
    function (array $task) {
        return ['milestones' => $this->container['milestoneTaskModel']->getTaskMilestones($task['id'])];
    }
);
$this->template->hook->attachCallable(
    'template:task:show:before-internal-links',
    'Portfolio:widget/task_dependencies',
    function (array $task) {
        return ['cross_deps' => $this->container['dependencyModel']->getCrossProjectDeps($task['id'])];
    }
);

// Project sidebar
$this->template->hook->attachCallable(
    'template:project:sidebar',
    'Portfolio:widget/project_sidebar',
    function (array $project) {
        return ['portfolios' => $this->container['portfolioProjectModel']->getProjectPortfolios($project['id'])];
    }
);

// Header creation dropdown
$this->template->hook->attach(
    'template:header:creation-dropdown',
    'Portfolio:widget/header_dropdown'
);

// Assets
$this->hook->on('template:layout:css', array('template' => 'plugins/Portfolio/Asset/css/portfolio.css'));
$this->hook->on('template:layout:js', array('template' => 'plugins/Portfolio/Asset/js/d3.v7.min.js'));
$this->hook->on('template:layout:js', array('template' => 'plugins/Portfolio/Asset/js/dependency-graph.js'));
$this->hook->on('template:layout:js', array('template' => 'plugins/Portfolio/Asset/js/portfolio-gantt.js'));
$this->hook->on('template:layout:js', array('template' => 'plugins/Portfolio/Asset/js/milestone-progress.js'));
```

### 6.2 Board Task Blocking Indicator

**File:** `Template/widget/board_task_icons.php`

```php
<?php if (!empty($is_blocked)): ?>
    <span class="portfolio-blocked-icon" title="<?= t('This task is blocked by a cross-project dependency') ?>">🔴</span>
<?php endif ?>
```

**File:** `Template/widget/board_task_footer.php`

```php
<?php if (!empty($blockers)): ?>
    <div class="portfolio-blocked-footer">
        <?php foreach ($blockers as $blocker): ?>
            <small class="portfolio-blocker-text">
                ⛔ <?= t('Blocked by') ?>:
                <a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $blocker['task_id'], 'project_id' => $blocker['project_id'])) ?>">
                    #<?= $blocker['task_id'] ?>
                </a>
                (<?= $this->text->e($blocker['project_name']) ?>)
            </small>
        <?php endforeach ?>
    </div>
<?php endif ?>
```

### 6.3 Performance Consideration for Board Hooks

Board pages render many tasks. The `board:task:icons` and `board:task:footer` hooks are called **per task card**. Naive implementation would produce N+1 queries.

**Mitigation:** Use `formatter:board:query` reference hook to pre-fetch all blocking data for the board in a single query:

```php
$this->hook->on('formatter:board:query', function (\PicoDb\Table &$query) {
    // This hook is called once before the board renders.
    // We can't modify the query shape, but we can use it to pre-load
    // blocking data into a cache keyed by task ID.
    // The cache is consumed by the board:task:* hooks.
});
```

Alternatively, implement `DependencyModel::preloadBlockedStatus(array $taskIds)` that bulk-loads blocking status for a set of task IDs in a single query, storing results in an in-memory cache (`memoryCache`). The board task hooks then read from cache.

---

## 7. Event System & Automatic Actions

### 7.1 Events Listened To

| Event | Source | Plugin Reaction |
|-------|--------|-----------------|
| `task.close` | Kanboard core | Check if the closed task was blocking other tasks. If any dependency is now resolved, fire `portfolio.dependency.resolved`. |
| `task.open` | Kanboard core | Check if the reopened task blocks any tasks. Update blocking indicators. |
| `task_internal_link.create_update` | Kanboard core | If a new "blocks"/"is blocked by" link is created between tasks in different projects, update the dependency cache. |
| `task_internal_link.delete` | Kanboard core | Recalculate blocking status for affected tasks. |

**Registration in `Plugin.php::initialize()`:**

```php
$this->on('task.close', function ($event) {
    $this->container['dependencyModel']->onTaskClosed($event['task_id']);
});

$this->on('task.open', function ($event) {
    $this->container['dependencyModel']->onTaskOpened($event['task_id']);
});

$this->on('task_internal_link.create_update', function ($event) {
    $this->container['dependencyModel']->onLinkChanged($event['task_id'] ?? 0);
});

$this->on('task_internal_link.delete', function ($event) {
    $this->container['dependencyModel']->onLinkChanged($event['task_id'] ?? 0);
});
```

### 7.2 Events Fired

#### `portfolio.dependency.resolved`

Fired by `DependencyModel::onTaskClosed()` when closing a task resolves one or more cross-project blocking relationships.

**Event data:**

```php
[
    'resolved_task_id' => 15,       // The task that was closed
    'resolved_task_title' => 'Finalize branding',
    'resolved_project_id' => 3,
    'resolved_project_name' => 'Product Alpha',
    'unblocked_tasks' => [          // Tasks that are now unblocked
        [
            'task_id' => 42,
            'task_title' => 'Publish product page',
            'project_id' => 8,
            'project_name' => 'Site Project',
            'owner_id' => 5,
        ],
    ],
]
```

**Registration:**

```php
$this->eventManager->register('portfolio.dependency.resolved', t('Cross-project dependency resolved'));
```

### 7.3 Automatic Actions

#### `NotifyDependencyResolved`

**Class:** `Action/NotifyDependencyResolved.php`
**Extends:** `Kanboard\Action\Base`

| Property | Value |
|----------|-------|
| Event | `portfolio.dependency.resolved` |
| Description | "Send a notification when a cross-project dependency is resolved" |
| Compatible events | `['portfolio.dependency.resolved']` |
| Parameters | None (notifies the assignee of each unblocked task) |

**`doAction()` logic:**

```php
foreach ($event['unblocked_tasks'] as $unblockedTask) {
    if ($unblockedTask['owner_id'] > 0) {
        $this->userNotificationModel->sendUserNotification(
            $unblockedTask['owner_id'],
            'dependency_resolved',
            [
                'task' => $this->taskFinderModel->getDetails($unblockedTask['task_id']),
                'resolved_task' => [
                    'id' => $event['resolved_task_id'],
                    'title' => $event['resolved_task_title'],
                    'project_name' => $event['resolved_project_name'],
                ],
            ]
        );
    }
}
```

#### `CommentDependencyResolved`

**Class:** `Action/CommentDependencyResolved.php`
**Extends:** `Kanboard\Action\Base`

| Property | Value |
|----------|-------|
| Event | `portfolio.dependency.resolved` |
| Description | "Add a comment when a cross-project dependency is resolved" |
| Compatible events | `['portfolio.dependency.resolved']` |
| Parameters | None |

**`doAction()` logic:**

```php
foreach ($event['unblocked_tasks'] as $unblockedTask) {
    $comment = sprintf(
        '✅ **Dependency resolved**: Task #%d "%s" in project "%s" has been completed. This task is no longer blocked.',
        $event['resolved_task_id'],
        $event['resolved_task_title'],
        $event['resolved_project_name']
    );

    $this->commentModel->create([
        'task_id' => $unblockedTask['task_id'],
        'user_id' => 0,  // System comment
        'comment' => $comment,
    ]);
}
```

### 7.4 Notification Type

**Class:** `Notification/DependencyResolvedType.php`

Registered via:

```php
$this->userNotificationTypeModel->setType(
    'dependency_resolved',
    t('Cross-project dependency resolved'),
    'Portfolio:notification/dependency_resolved'
);
```

**Template:** `Template/notification/dependency_resolved.php` — Renders the notification text for email and web notification channels.

---

## 8. Configuration & Defaults

### 8.1 Plugin-Level Configuration

The plugin uses Kanboard's `settings` table (via `$this->configModel`) for persistent configuration. Settings are prefixed with `portfolio_` to avoid namespace collisions.

| Setting Key | Type | Default | Description |
|-------------|------|---------|-------------|
| `portfolio_milestone_at_risk_days` | integer | `7` | Number of days before target date to flag a milestone as "at risk" |
| `portfolio_milestone_at_risk_threshold` | integer | `80` | Percentage threshold below which a milestone is "at risk" when within the risk window |
| `portfolio_board_show_blockers` | integer | `1` | Whether to show blocking indicators on board task cards. `1` = yes, `0` = no. |
| `portfolio_dashboard_widget_enabled` | integer | `1` | Whether to show portfolio widget on the dashboard. `1` = yes, `0` = no. |
| `portfolio_dependency_link_types` | string | `"blocks"` | Comma-separated list of link type labels to treat as dependencies. Default is "blocks" only. Could be extended to "is a child of" etc. |
| `portfolio_tasks_per_page` | integer | `50` | Default pagination limit for `getPortfolioTasks` |

### 8.2 Configuration UI

A settings page at `template:config:sidebar` → "Portfolio Settings":

- All settings above are editable via a simple form.
- Setting changes take effect immediately (no restart required).
- Settings are stored in Kanboard's `settings` table and retrieved via `$this->configModel->get('portfolio_...', $default)`.

### 8.3 Accessing Configuration in Code

```php
$atRiskDays = (int) $this->configModel->get('portfolio_milestone_at_risk_days', 7);
$atRiskThreshold = (int) $this->configModel->get('portfolio_milestone_at_risk_threshold', 80);
```

---

## 9. Dependencies & Requirements

### 9.1 Runtime Requirements

| Requirement | Version | Notes |
|-------------|---------|-------|
| **Kanboard** | >= 1.2.20 | Required for all template hooks used. Pin via `getCompatibleVersion()`. |
| **PHP** | >= 7.4 | Match Kanboard's own minimum. Use PHP 7.4 syntax (typed properties, arrow functions). |
| **Database** | SQLite 3.x, MySQL 5.7+ / MariaDB 10.2+, PostgreSQL 9.5+ | All three supported via separate migration files. |

### 9.2 Bundled Libraries

| Library | Version | License | Size | Purpose |
|---------|---------|---------|------|---------|
| **D3.js** | 7.x | ISC | ~280KB minified | Force-directed dependency graph, timeline chart |

D3.js is the **only external JavaScript dependency**. It is bundled in `Asset/js/d3.v7.min.js`. No CDN references.

### 9.3 No External PHP Dependencies

The plugin uses only Kanboard's built-in libraries:
- PicoDb (database query builder)
- Pimple (DI container)
- Symfony EventDispatcher (events)
- JsonRPC PHP library (API)

No Composer dependencies are introduced.

### 9.4 Optional Peer Plugins

| Plugin | Benefit |
|--------|---------|
| **Gantt** (kanboard/plugin-gantt) | If installed, the portfolio timeline view can link to per-project Gantt views |
| **Milestone** (oliviermaridat) | If installed, the plugin detects and respects "is a milestone of" link types for backward compatibility |

These plugins are not required and the Portfolio plugin operates fully without them.

---

## 10. Error Handling & Edge Cases

### 10.1 API Error Responses

All API methods follow Kanboard's convention:

| Condition | Return Value | JSON-RPC Error |
|-----------|-------------|----------------|
| Successful creation | `int` (new ID) | — |
| Successful update/delete | `true` | — |
| Validation failure (bad input) | `false` | — |
| Entity not found (get by ID) | `null` | — |
| Authorization failure | — | `{"code": -32603, "message": "Access denied"}` |
| Internal error | — | `{"code": -32603, "message": "Internal error"}` |

The plugin does **not** introduce custom JSON-RPC error codes. It uses `false`/`null` returns consistent with Kanboard's core API.

### 10.2 Edge Cases

#### Deleted Entities

| Scenario | Behavior |
|----------|----------|
| **Project deleted from Kanboard** (that was in a portfolio) | `ON DELETE CASCADE` on `portfolio_has_projects.project_id` removes the membership row. Tasks from that project are also removed from milestones in the model layer's cleanup listener. |
| **Task deleted from Kanboard** (that was in a milestone) | `ON DELETE CASCADE` on `milestone_has_tasks.task_id` removes the membership row. Milestone progress recalculates automatically on next query. |
| **Portfolio deleted** | Cascades to `portfolio_has_projects`, `milestones`, and `milestone_has_tasks`. No core entities affected. |
| **Link type deleted** (e.g., "blocks" removed) | Dependency queries will return empty results. The plugin does not prevent link type deletion but will log a warning if the configured dependency link types are missing. |

#### Concurrent Modifications

| Scenario | Behavior |
|----------|----------|
| **Two users add the same project to the same portfolio** | Primary key constraint `(portfolio_id, project_id)` prevents duplicates. Second insert returns `false`. |
| **Task closed while dependency graph is being computed** | The graph reflects the state at query time. No locking. Eventual consistency is acceptable. |

#### Large Portfolios

| Scenario | Mitigation |
|----------|------------|
| **Portfolio with 50+ projects** | Pagination on `getPortfolioTasks`. Dependency graph filtered to cross-project-only by default. |
| **Portfolio with 1000+ tasks** | `getPortfolioTasks` returns paginated results (default limit 50). Board view shows only active tasks. |
| **Dependency graph with 500+ nodes** | D3.js renderer implements client-side filtering (by project, by milestone, cross-project only). Server returns full graph; client filters. |

#### Data Integrity Validation

| Validation | Where Enforced | Error |
|------------|----------------|-------|
| Portfolio name uniqueness | DB `UNIQUE` constraint + model-layer check | `createPortfolio` returns `false` |
| Milestone name uniqueness within portfolio | Model-layer check before insert | `createMilestone` returns `false` |
| Task must belong to a portfolio project to be added to a milestone | Model-layer check (see §4.4) | `addTaskToMilestone` returns `false` |
| Project must exist to be added to portfolio | Model-layer check via `$this->projectModel->getById()` | `addProjectToPortfolio` returns `false` |
| Task must exist to be added to milestone | Model-layer check via `$this->taskFinderModel->getById()` | `addTaskToMilestone` returns `false` |

### 10.3 Graceful Degradation

| Condition | Behavior |
|-----------|----------|
| **Plugin installed but schema not migrated** | Kanboard runs migrations automatically on next page load. No manual step required. |
| **Plugin disabled/removed** | Plugin tables remain in the database but are inert. No core functionality is affected. Re-enabling the plugin restores all data. |
| **Kanboard upgraded to incompatible version** | `getCompatibleVersion()` prevents loading. Plugin is disabled with a message. |
| **D3.js fails to load** | Dependency graph page shows a "Visualization unavailable" message. All data is still accessible via the task list and API. |

---

## 11. Security Considerations

### 11.1 Permissions Model

Portfolios are **application-level entities** (not project-scoped). Access control follows this model:

| Operation | Required Role |
|-----------|---------------|
| **View** any portfolio, milestone, task list, dependency graph | `app-user` (any authenticated user) |
| **Create** portfolio | `app-manager` or `app-admin` |
| **Edit/delete** portfolio | `app-admin`, OR `app-manager` who is the portfolio owner |
| **Manage** project membership | `app-admin`, OR `app-manager` who is the portfolio owner |
| **Create/edit/delete** milestones | `app-admin`, OR `app-manager` who is the portfolio owner |
| **Add/remove** tasks to milestones | `app-admin`, OR `app-manager` who is the portfolio owner |

**Rationale:** Portfolios are organizational constructs that span projects. Making them visible to all users enables cross-team transparency. Write operations are restricted to managers and admins to prevent accidental modification.

**Project-level visibility:** The task list and dependency views show task data from all projects in the portfolio. Users who do not have access to a specific project in Kanboard **can still see task titles and statuses** through the portfolio view. This is intentional — portfolio views are designed for cross-project visibility. If this is a concern, the portfolio owner should only add projects whose tasks are acceptable for broad visibility.

### 11.2 Input Validation

All user input is validated in the `Validator/PortfolioValidator.php` class and in the model layer:

| Input | Validation |
|-------|------------|
| Portfolio name | Not empty, max 255 chars, trimmed, unique |
| Milestone name | Not empty, max 255 chars, trimmed, unique within portfolio |
| Target date | Valid `YYYY-MM-DD` format or valid Unix timestamp |
| Color ID | Must match a Kanboard color ID (validated against `$this->colorModel->getList()`) |
| Integer IDs | Must be positive integers. Validated with `(int)` cast and `> 0` check. |
| Pagination params | `limit` clamped to `[1, 200]`. `offset` must be `>= 0`. |
| Sort/direction | Validated against whitelist of allowed values. |

### 11.3 CSRF Protection

All form submissions in controllers are protected with `$this->checkCSRFParam()`, which validates the CSRF token automatically inserted by Kanboard's form helpers.

API methods (JSON-RPC) do not use CSRF — they rely on HTTP Basic Auth authentication which is validated per-request by Kanboard's core API layer.

### 11.4 SQL Injection Prevention

All database queries use PicoDb's parameterized query builder. No raw string interpolation into SQL. Any raw SQL executed via `$this->db->execute()` uses PDO prepared statements with bound parameters.

### 11.5 XSS Prevention

All template output is escaped using Kanboard's built-in helpers:
- `$this->text->e($value)` for HTML text content
- `$this->url->href(...)` for URL generation
- `<?= ... ?>` short echo tags with escaped values only

Raw user content (descriptions, names) is always passed through `$this->text->e()` or `$this->text->markdown()` (which sanitizes HTML).

### 11.6 Trust Boundaries

| Boundary | Trust Level |
|----------|-------------|
| Kanboard database | Trusted (plugin reads/writes with same privileges as Kanboard core) |
| JSON-RPC API input | Untrusted (validated by model layer) |
| Form input | Untrusted (validated by controller + validator) |
| D3.js graph data | Semi-trusted (generated server-side, but rendered client-side; all text values are HTML-escaped before injection into DOM) |

---

## 12. Testing Strategy

### 12.1 Test Framework

Tests use Kanboard's built-in test framework, which is PHPUnit-based. Test files are placed in `Test/` within the plugin directory.

### 12.2 Unit Tests — Model Layer

**Directory:** `Test/Model/`

#### `PortfolioModelTest.php`

| Test Case | What It Verifies |
|-----------|-----------------|
| `testCreatePortfolio` | Successful creation returns integer ID. `created_at` and `updated_at` are set. |
| `testCreateDuplicateName` | Creating a portfolio with an existing name returns `false`. |
| `testCreateEmptyName` | Empty or whitespace-only name returns `false`. |
| `testGetPortfolio` | Retrieves correct portfolio dict by ID. |
| `testGetPortfolioNotFound` | Returns `null` for non-existent ID. |
| `testGetPortfolioByName` | Retrieves by exact name match. |
| `testGetAllPortfolios` | Returns all portfolios. Returns `[]` when none exist. |
| `testUpdatePortfolio` | Updates fields. Verifies `updated_at` changes. |
| `testUpdateNameConflict` | Updating to an existing name returns `false`. |
| `testRemovePortfolio` | Deletes portfolio. Cascades to projects, milestones, tasks. |
| `testRemoveNonExistent` | Returns `false` for non-existent ID. |

#### `PortfolioProjectModelTest.php` (not shown in detail — same pattern)

Key scenarios: add/remove project, duplicate add returns `false`, remove cascades to milestone tasks, getProjectIds, position ordering.

#### `MilestoneModelTest.php`

Key scenarios: CRUD, name uniqueness within portfolio, date parsing, status transitions.

#### `MilestoneTaskModelTest.php`

Key scenarios: add/remove task, task-must-be-in-portfolio validation, duplicate add returns `false`, getMilestoneTasks enrichment, getTaskMilestones reverse lookup.

#### `DependencyModelTest.php`

| Test Case | What It Verifies |
|-----------|-----------------|
| `testGetPortfolioDependenciesNone` | Returns `[]` when no links exist. |
| `testGetPortfolioDependenciesCrossProjectOnly` | Filters out same-project links. |
| `testGetPortfolioDependenciesAllLinks` | Returns both same-project and cross-project links. |
| `testGetBlockedTasks` | Correctly identifies tasks with unresolved "is blocked by" links. |
| `testGetBlockedTasksResolvedDep` | Task whose blocker is closed is NOT in blocked list. |
| `testGetBlockingTasks` | Identifies open tasks that block others. |
| `testCriticalPathLinearChain` | A→B→C→D returns `[A, B, C, D]`. |
| `testCriticalPathDiamond` | A→B, A→C, B→D, C→D returns the longest path `[A, B, D]` or `[A, C, D]`. |
| `testCriticalPathNoDeps` | Returns `[]`. |
| `testCriticalPathSingleTask` | Returns `[A]` (or `[]` — define expected behavior). |
| `testOnTaskClosedFiresEvent` | Closing a blocking task dispatches `portfolio.dependency.resolved`. |
| `testOnTaskClosedNoDeps` | Closing a task with no dependents does NOT fire the event. |

#### `PortfolioTaskModelTest.php`

Key scenarios: unified task query with filters (status, assignee, project, milestone), pagination, sort ordering, blocked flag computation, task count aggregation.

### 12.3 Unit Tests — Validator

| Test Case | Validates |
|-----------|-----------|
| `testValidPortfolioName` | Non-empty, <= 255 chars passes |
| `testEmptyPortfolioName` | Empty string fails |
| `testLongPortfolioName` | > 255 chars fails |
| `testValidTargetDate` | `"2026-06-01"` parses correctly |
| `testInvalidTargetDate` | `"not-a-date"` fails |
| `testValidColorId` | Known color passes |
| `testInvalidColorId` | Unknown color fails |

### 12.4 Integration Tests — Controller Layer

These tests use Kanboard's functional test helpers to simulate HTTP requests through the controller layer.

| Test Case | What It Verifies |
|-----------|-----------------|
| `testPortfolioListPage` | `/portfolios` returns 200, renders portfolio list template. |
| `testCreatePortfolioForm` | `/portfolio/create` returns 200, shows form. |
| `testCreatePortfolioSubmit` | POST with valid data creates portfolio, redirects to show page. |
| `testCreatePortfolioAccessDenied` | `app-user` role gets 403 on create. |
| `testPortfolioDashboard` | `/portfolio/:id` renders dashboard with milestones and task counts. |
| `testPortfolioTasks` | `/portfolio/:id/tasks` renders unified task list. |
| `testDependencyGraph` | `/portfolio/:id/dependencies` renders graph page and returns graph JSON via AJAX endpoint. |
| `testMilestoneCRUD` | Full create → edit → delete lifecycle for a milestone. |
| `testBoardTaskBlockerIndicator` | Board page for a project in a portfolio shows blocking indicators on blocked tasks. |

### 12.5 Acceptance Criteria Summary

| Criterion | Verification |
|-----------|-------------|
| Four new DB tables created on install | Schema migration test |
| All 28 JSON-RPC methods callable | API integration test per method |
| Portfolio CRUD works end-to-end | Model + API + Controller tests |
| Milestones with cross-project tasks | Model test with tasks from 3 projects |
| Milestone progress computed correctly | Model test: 0%, 50%, 100%, at-risk, overdue |
| Blocked tasks identified correctly | Dependency model test with known graph |
| Critical path computed correctly | Dependency model test with known topologies |
| Dashboard widget shows at-risk milestones | Controller integration test |
| Board shows blocking indicators | Template hook integration test |
| D3.js graph renders with correct data | Controller returns valid JSON; JS tests deferred to Phase 3 |
| Notifications fire on dependency resolution | Event listener test |
| CSRF protection on all forms | Controller test: POST without token returns 403 |
| Plugin uninstallable without data loss to core | Manual verification |

---

## 13. Localization

### 13.1 Translation File

**File:** `Locale/en_US/translations.php`

```php
<?php

return [
    // Navigation
    'Portfolios' => 'Portfolios',
    'Portfolio' => 'Portfolio',
    'Create Portfolio' => 'Create Portfolio',
    'Edit Portfolio' => 'Edit Portfolio',
    'Remove Portfolio' => 'Remove Portfolio',
    'Portfolio Settings' => 'Portfolio Settings',

    // Portfolio fields
    'Portfolio Name' => 'Portfolio Name',
    'Portfolio Description' => 'Portfolio Description',

    // Milestones
    'Milestones' => 'Milestones',
    'Milestone' => 'Milestone',
    'Create Milestone' => 'Create Milestone',
    'Edit Milestone' => 'Edit Milestone',
    'Remove Milestone' => 'Remove Milestone',
    'Target Date' => 'Target Date',
    'Milestone Progress' => 'Milestone Progress',

    // Status
    'Active' => 'Active',
    'Completed' => 'Completed',
    'Cancelled' => 'Cancelled',
    'At Risk' => 'At Risk',
    'Overdue' => 'Overdue',
    'On Track' => 'On Track',

    // Dependencies
    'Dependencies' => 'Dependencies',
    'Dependency Graph' => 'Dependency Graph',
    'Blocked Tasks' => 'Blocked Tasks',
    'Blocking Tasks' => 'Blocking Tasks',
    'Critical Path' => 'Critical Path',
    'Blocked by' => 'Blocked by',
    'Blocks' => 'Blocks',
    'Cross-project dependencies' => 'Cross-project dependencies',
    'This task is blocked by a cross-project dependency' => 'This task is blocked by a cross-project dependency',

    // Views
    'Dashboard' => 'Dashboard',
    'Task List' => 'Task List',
    'Board' => 'Board',
    'Timeline' => 'Timeline',
    'All Projects' => 'All Projects',
    'Cross-Project Only' => 'Cross-Project Only',
    'Blocked Only' => 'Blocked Only',

    // Actions
    'Add Project' => 'Add Project',
    'Remove Project' => 'Remove Project',
    'Add Task' => 'Add Task',
    'Remove Task' => 'Remove Task',
    'Mark as Critical' => 'Mark as Critical',

    // Notifications
    'Cross-project dependency resolved' => 'Cross-project dependency resolved',

    // Messages
    'Portfolio created successfully.' => 'Portfolio created successfully.',
    'Portfolio updated successfully.' => 'Portfolio updated successfully.',
    'Portfolio removed successfully.' => 'Portfolio removed successfully.',
    'Milestone created successfully.' => 'Milestone created successfully.',
    'Milestone updated successfully.' => 'Milestone updated successfully.',
    'Milestone removed successfully.' => 'Milestone removed successfully.',
    'Project added to portfolio.' => 'Project added to portfolio.',
    'Project removed from portfolio.' => 'Project removed from portfolio.',
    'Task added to milestone.' => 'Task added to milestone.',
    'Task removed from milestone.' => 'Task removed from milestone.',
    'Unable to create portfolio.' => 'Unable to create portfolio.',
    'Unable to update portfolio.' => 'Unable to update portfolio.',
    'Unable to remove portfolio.' => 'Unable to remove portfolio.',
    'Do you really want to remove this portfolio? All milestones will also be removed.' =>
        'Do you really want to remove this portfolio? All milestones will also be removed.',
    'Visualization unavailable. Please ensure JavaScript is enabled.' =>
        'Visualization unavailable. Please ensure JavaScript is enabled.',
    'No milestones defined.' => 'No milestones defined.',
    'No dependencies found.' => 'No dependencies found.',
    'No blocked tasks.' => 'No blocked tasks.',
    'Task does not belong to a project in this portfolio.' =>
        'Task does not belong to a project in this portfolio.',
];
```

### 13.2 Adding Translations

Translations are loaded in `onStartup()`:

```php
public function onStartup()
{
    Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');
}
```

All user-visible strings in templates and controllers use `t('string key')`.

---

## 14. Future Considerations / Open Questions

### 14.1 Known Limitations (Phase 1)

| Limitation | Impact | Resolution Timeline |
|-----------|--------|---------------------|
| **No drag-and-drop on aggregate board** | Tasks on the portfolio board are read-only — users must navigate to the project board to move tasks between columns. | Phase 3: investigate feasibility of cross-project drag-and-drop via AJAX. |
| **Dependency graph is client-rendered** | Large graphs (500+ nodes) may be slow in the browser. | Phase 3: implement server-side graph simplification (collapse linear chains, hide resolved deps). |
| **No weighted milestone progress** | Progress is simple task count ratio. Task complexity/score is not factored in. | Phase 2 or later: add `weight_by` option to `getMilestoneProgress` (options: `count`, `score`, `time_estimated`). |
| **No dependency metadata** | Cannot annotate a dependency with additional information (e.g., "expected resolution date", "notes"). | Would require a supplementary table `dependency_metadata` keyed by `task_has_links.id`. Deferred. |
| **Portfolio visibility is all-or-nothing** | All authenticated users can see all portfolios. No per-portfolio access control. | Future: add `portfolio_has_users` table for fine-grained access. Significant complexity. |

### 14.2 Open Design Questions

#### Q1: Should the portfolio board support column mapping?

Different projects may have different column names for the same workflow stage (e.g., "To Do" vs. "Backlog", "Done" vs. "Completed"). The aggregate board needs to group these.

**Options:**
- (A) Define a "column mapping" in portfolio settings: `{"Backlog": ["To Do", "Backlog", "New"], "Done": ["Done", "Completed", "Closed"]}`
- (B) Use column position as a proxy: all first columns map to "Stage 1", etc.
- (C) Show raw column names without mapping, grouped by project.

**Current plan:** Option (C) for Phase 1 (simplest). Option (A) for Phase 2.

#### Q2: PicoDb table alias support

PicoDb's `join()` method may not support table aliases (`AS t1`) consistently across all database drivers. Several queries in the dependency model require joining the `tasks` table twice (once for the source task, once for the opposite task).

**Mitigation options:**
- (A) Use raw SQL with PDO prepared statements for complex queries. Maintain three SQL variants (SQLite, MySQL, PostgreSQL) where syntax differs.
- (B) Execute two separate queries and combine results in PHP.
- (C) Use PicoDb's `addCondition()` to inject raw SQL fragments into the query builder.

**Resolution needed before implementation of `DependencyModel`.**

#### Q3: How should the plugin handle Kanboard's "default swimlane" quirk?

Kanboard's default swimlane has `swimlane_id = 0` in the tasks table but doesn't exist in the `swimlanes` table. The unified task list and board view need to handle this without errors in JOINs.

**Plan:** Use `LEFT JOIN` for swimlanes and display "Default" when `swimlane_id = 0`.

#### Q4: Should milestone completion auto-close the milestone?

When all tasks in a milestone reach `is_active = 0` (closed), should the milestone status automatically change from `1` (active) to `0` (completed)?

**Plan:** Yes, implement as an optional behavior controlled by a configuration setting (`portfolio_auto_complete_milestones`, default `1`). Check on each task close event.

### 14.3 Deferred Features

| Feature | Reason for Deferral |
|---------|---------------------|
| **Portfolio-level permissions** | Significant complexity; requires new junction table and access checking throughout |
| **Portfolio activity stream** | Requires aggregating activity across projects; performance concerns |
| **Portfolio-level Gantt with drag scheduling** | Complex JS interaction; requires computing cascading date shifts |
| **Email digest: weekly portfolio summary** | Requires cron integration and email template |
| **Import/export portfolios** | Low priority; can be done via API |
| **Portfolio duplication** | Low priority; can be done via API scripting |

---

## Appendix A: Plugin.php — Complete Registration

```php
<?php

namespace Kanboard\Plugin\Portfolio;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Translator;
use Kanboard\Core\Security\Role;

class Plugin extends Base
{
    public function initialize()
    {
        // --- DI Container: Register Models ---
        $this->container['portfolioModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\PortfolioModel($c);
        };
        $this->container['portfolioProjectModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\PortfolioProjectModel($c);
        };
        $this->container['milestoneModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\MilestoneModel($c);
        };
        $this->container['milestoneTaskModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\MilestoneTaskModel($c);
        };
        $this->container['dependencyModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\DependencyModel($c);
        };
        $this->container['portfolioTaskModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\PortfolioTaskModel($c);
        };
        $this->container['portfolioHelper'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Helper\PortfolioHelper($c);
        };
        $this->container['portfolioValidator'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Validator\PortfolioValidator($c);
        };

        // --- Routes ---
        $this->route->addRoute('/portfolios', 'PortfolioListController', 'index', 'Portfolio');
        $this->route->addRoute('/portfolio/create', 'PortfolioModificationController', 'create', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id', 'PortfolioViewController', 'show', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/tasks', 'PortfolioViewController', 'tasks', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/board', 'PortfolioViewController', 'board', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/timeline', 'PortfolioViewController', 'timeline', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/dependencies', 'DependencyController', 'graph', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/blocked', 'DependencyController', 'blocked', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/critical-path', 'DependencyController', 'criticalPath', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/milestones', 'MilestoneController', 'index', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/settings', 'PortfolioModificationController', 'settings', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/edit', 'PortfolioModificationController', 'edit', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/remove', 'PortfolioModificationController', 'remove', 'Portfolio');
        $this->route->addRoute('/milestone/create/:portfolio_id', 'MilestoneController', 'create', 'Portfolio');
        $this->route->addRoute('/milestone/:milestone_id', 'MilestoneController', 'show', 'Portfolio');
        $this->route->addRoute('/milestone/:milestone_id/edit', 'MilestoneController', 'edit', 'Portfolio');
        $this->route->addRoute('/milestone/:milestone_id/remove', 'MilestoneController', 'remove', 'Portfolio');

        // --- Access Maps ---
        $this->applicationAccessMap->add('PortfolioListController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('PortfolioViewController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('DependencyController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('MilestoneController', array('index', 'show'), Role::APP_USER);
        $this->applicationAccessMap->add('MilestoneController', array('create', 'save', 'edit', 'update', 'remove', 'delete', 'addTask', 'removeTask'), Role::APP_MANAGER);
        $this->applicationAccessMap->add('PortfolioModificationController', '*', Role::APP_MANAGER);

        // --- API Methods ---
        // Portfolio CRUD
        $this->api->getProcedureHandler()
            ->withCallback('createPortfolio', function ($name, $description = '', $owner_id = 0) {
                return $this->portfolioModel->create($name, $description, $owner_id);
            })
            ->withCallback('getPortfolio', function ($portfolio_id) {
                return $this->portfolioModel->getById($portfolio_id);
            })
            ->withCallback('getPortfolioByName', function ($name) {
                return $this->portfolioModel->getByName($name);
            })
            ->withCallback('getAllPortfolios', function () {
                return $this->portfolioModel->getAll();
            })
            ->withCallback('updatePortfolio', function ($portfolio_id, $name = null, $description = null, $owner_id = null, $is_active = null) {
                return $this->portfolioModel->update($portfolio_id, compact('name', 'description', 'owner_id', 'is_active'));
            })
            ->withCallback('removePortfolio', function ($portfolio_id) {
                return $this->portfolioModel->remove($portfolio_id);
            })
            // Portfolio-Project Membership
            ->withCallback('addProjectToPortfolio', function ($portfolio_id, $project_id, $position = 0) {
                return $this->portfolioProjectModel->add($portfolio_id, $project_id, $position);
            })
            ->withCallback('removeProjectFromPortfolio', function ($portfolio_id, $project_id) {
                return $this->portfolioProjectModel->remove($portfolio_id, $project_id);
            })
            ->withCallback('getPortfolioProjects', function ($portfolio_id) {
                return $this->portfolioProjectModel->getProjects($portfolio_id);
            })
            ->withCallback('getProjectPortfolios', function ($project_id) {
                return $this->portfolioProjectModel->getPortfolios($project_id);
            })
            // Milestone CRUD
            ->withCallback('createMilestone', function ($portfolio_id, $name, $description = '', $target_date = null, $color_id = 'blue', $owner_id = 0) {
                return $this->milestoneModel->create($portfolio_id, $name, $description, $target_date, $color_id, $owner_id);
            })
            ->withCallback('getMilestone', function ($milestone_id) {
                return $this->milestoneModel->getById($milestone_id);
            })
            ->withCallback('getPortfolioMilestones', function ($portfolio_id) {
                return $this->milestoneModel->getByPortfolioId($portfolio_id);
            })
            ->withCallback('updateMilestone', function ($milestone_id, $name = null, $description = null, $target_date = null, $color_id = null, $owner_id = null, $status = null) {
                return $this->milestoneModel->update($milestone_id, compact('name', 'description', 'target_date', 'color_id', 'owner_id', 'status'));
            })
            ->withCallback('removeMilestone', function ($milestone_id) {
                return $this->milestoneModel->remove($milestone_id);
            })
            // Milestone-Task Membership
            ->withCallback('addTaskToMilestone', function ($milestone_id, $task_id, $is_critical = 0, $position = 0) {
                return $this->milestoneTaskModel->add($milestone_id, $task_id, $is_critical, $position);
            })
            ->withCallback('removeTaskFromMilestone', function ($milestone_id, $task_id) {
                return $this->milestoneTaskModel->remove($milestone_id, $task_id);
            })
            ->withCallback('getMilestoneTasks', function ($milestone_id) {
                return $this->milestoneTaskModel->getTasks($milestone_id);
            })
            ->withCallback('getTaskMilestones', function ($task_id) {
                return $this->milestoneTaskModel->getMilestones($task_id);
            })
            ->withCallback('getMilestoneProgress', function ($milestone_id) {
                return $this->milestoneModel->getProgress($milestone_id);
            })
            // Dependency Queries
            ->withCallback('getPortfolioDependencies', function ($portfolio_id, $cross_project_only = true) {
                return $this->dependencyModel->getDependencies($portfolio_id, $cross_project_only);
            })
            ->withCallback('getBlockedTasks', function ($portfolio_id) {
                return $this->dependencyModel->getBlockedTasks($portfolio_id);
            })
            ->withCallback('getBlockingTasks', function ($portfolio_id) {
                return $this->dependencyModel->getBlockingTasks($portfolio_id);
            })
            ->withCallback('getPortfolioCriticalPath', function ($portfolio_id) {
                return $this->dependencyModel->getCriticalPath($portfolio_id);
            })
            ->withCallback('getPortfolioDependencyGraph', function ($portfolio_id, $cross_project_only = true) {
                return $this->dependencyModel->getGraph($portfolio_id, $cross_project_only);
            })
            // Unified Task Queries
            ->withCallback('getPortfolioTasks', function ($portfolio_id, $status_id = null, $assignee_id = null, $project_id = null, $milestone_id = null, $has_dependencies = null, $sort = 'priority', $direction = 'DESC', $limit = 50, $offset = 0) {
                return $this->portfolioTaskModel->getTasks($portfolio_id, compact('status_id', 'assignee_id', 'project_id', 'milestone_id', 'has_dependencies', 'sort', 'direction', 'limit', 'offset'));
            })
            ->withCallback('getPortfolioTaskCount', function ($portfolio_id, $status_id = null) {
                return $this->portfolioTaskModel->getCounts($portfolio_id, $status_id);
            })
            ->withCallback('getPortfolioOverview', function ($portfolio_id) {
                return $this->portfolioTaskModel->getOverview($portfolio_id);
            });

        // --- Events ---
        $this->on('task.close', function ($event) {
            $this->dependencyModel->onTaskClosed($event['task_id']);
        });
        $this->on('task.open', function ($event) {
            $this->dependencyModel->onTaskOpened($event['task_id']);
        });
        $this->on('task_internal_link.create_update', function ($event) {
            $this->dependencyModel->onLinkChanged($event['task_id'] ?? 0);
        });
        $this->on('task_internal_link.delete', function ($event) {
            $this->dependencyModel->onLinkChanged($event['task_id'] ?? 0);
        });

        // --- Register Custom Event ---
        $this->eventManager->register('portfolio.dependency.resolved', t('Cross-project dependency resolved'));

        // --- Automatic Actions ---
        $this->actionManager->register(new \Kanboard\Plugin\Portfolio\Action\NotifyDependencyResolved($this->container));
        $this->actionManager->register(new \Kanboard\Plugin\Portfolio\Action\CommentDependencyResolved($this->container));

        // --- Task Filter ---
        $this->container->extend('taskLexer', function ($taskLexer, $c) {
            $taskLexer->withFilter(new \Kanboard\Plugin\Portfolio\Filter\TaskPortfolioFilter($c));
            return $taskLexer;
        });

        // --- Template Hooks ---
        $this->template->hook->attach('template:dashboard:sidebar', 'Portfolio:widget/dashboard_sidebar');
        $this->template->hook->attachCallable('template:dashboard:show', 'Portfolio:widget/dashboard_milestones', function () {
            return ['at_risk' => $this->container['milestoneModel']->getAtRiskMilestones()];
        });
        $this->template->hook->attachCallable('template:board:task:icons', 'Portfolio:widget/board_task_icons', function (array $task) {
            return ['is_blocked' => $this->container['dependencyModel']->isTaskBlocked($task['id'])];
        });
        $this->template->hook->attachCallable('template:board:task:footer', 'Portfolio:widget/board_task_footer', function (array $task) {
            return ['blockers' => $this->container['dependencyModel']->getTaskBlockers($task['id'])];
        });
        $this->template->hook->attachCallable('template:task:sidebar:information', 'Portfolio:widget/task_sidebar', function (array $task) {
            return ['milestones' => $this->container['milestoneTaskModel']->getMilestones($task['id'])];
        });
        $this->template->hook->attachCallable('template:task:show:before-internal-links', 'Portfolio:widget/task_dependencies', function (array $task) {
            return ['cross_deps' => $this->container['dependencyModel']->getCrossProjectDeps($task['id'])];
        });
        $this->template->hook->attachCallable('template:project:sidebar', 'Portfolio:widget/project_sidebar', function () {
            $projectId = $this->request->getIntegerParam('project_id');
            return ['portfolios' => $this->container['portfolioProjectModel']->getPortfolios($projectId)];
        });
        $this->template->hook->attach('template:header:creation-dropdown', 'Portfolio:widget/header_dropdown');
        $this->template->hook->attach('template:config:sidebar', 'Portfolio:config/sidebar');

        // --- Assets ---
        $this->hook->on('template:layout:css', array('template' => 'plugins/Portfolio/Asset/css/portfolio.css'));
        $this->hook->on('template:layout:js', array('template' => 'plugins/Portfolio/Asset/js/d3.v7.min.js'));
        $this->hook->on('template:layout:js', array('template' => 'plugins/Portfolio/Asset/js/dependency-graph.js'));
        $this->hook->on('template:layout:js', array('template' => 'plugins/Portfolio/Asset/js/portfolio-gantt.js'));
        $this->hook->on('template:layout:js', array('template' => 'plugins/Portfolio/Asset/js/milestone-progress.js'));
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');
    }

    public function getPluginName()
    {
        return 'Portfolio';
    }

    public function getPluginDescription()
    {
        return t('Cross-project portfolio management: portfolios, milestones, dependency visualization');
    }

    public function getPluginAuthor()
    {
        return 'Geekmuse';
    }

    public function getPluginVersion()
    {
        return '1.0.0';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/geekmuse/kanboard-plugin-portfolio';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.20';
    }
}
```

---

## Appendix B: Method Count Summary

| Category | Method Count |
|----------|-------------|
| Portfolio CRUD | 6 |
| Portfolio ↔ Project Membership | 4 |
| Milestone CRUD | 5 |
| Milestone ↔ Task Membership | 5 |
| Dependency Queries | 5 |
| Unified Task Queries | 3 |
| **Total JSON-RPC methods** | **28** |

---

## Appendix C: CSS Class Naming Convention

All CSS classes are prefixed with `portfolio-` to avoid collisions with Kanboard core and other plugins.

```css
.portfolio-list { }
.portfolio-dashboard { }
.portfolio-milestone-bar { }
.portfolio-milestone-progress { }
.portfolio-milestone-at-risk { }
.portfolio-milestone-overdue { }
.portfolio-blocked-icon { }
.portfolio-blocked-footer { }
.portfolio-blocker-text { }
.portfolio-dependency-graph { }
.portfolio-graph-node { }
.portfolio-graph-edge { }
.portfolio-graph-node--blocked { }
.portfolio-graph-node--resolved { }
.portfolio-graph-node--open { }
.portfolio-board { }
.portfolio-board-card { }
.portfolio-board-project-header { }
.portfolio-task-list { }
.portfolio-view-switcher { }
.portfolio-badge { }
```
