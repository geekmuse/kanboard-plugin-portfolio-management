# Changelog

## [1.22.2] — 2026-03-31

### Fixed

- **URL encoding bug in all redirect flows** — all 21 `response->redirect()` calls across 5 controllers were using `url->href()` (which returns `&amp;`-encoded URLs, correct for HTML attributes) instead of `url->to()` (raw `&`, required for HTTP `Location` headers). Browsers received `Location` headers with literal `&amp;` and navigated to URLs where Kanboard's router saw `amp;action` and `amp;plugin` as garbage query parameters, breaking every post-form redirect. Fixed by switching all redirect calls to `url->to()`.
- **`data-move-task-url` in board template** — was using `url->href()` for a JS `data-*` attribute consumed by `getAttribute()`; functionally worked due to browser decoding but semantically wrong. Switched to `url->to()` + `htmlspecialchars()`.
- **`data-graph-data-url` double-encoding** — `DependencyController` generated this attribute using `url->href()` output then further wrapped in `text->e()`, producing `&amp;amp;`. Switched source to `url->to()` and template to `htmlspecialchars()`. (Attribute is currently unused by the graph JS but corrected for future use.)
- **Test harness `FakeLayoutUrlHelper`** — added `to()` method mirroring the real `UrlHelper::to()` signature; `href()` now uses `&amp;` as separator to accurately match Kanboard core behaviour.

### Added

- **MIT LICENSE file** — resolves `licenseInfo: null` on GitHub.
- **`SECURITY.md`** — vulnerability disclosure policy with contact and response-time commitments.
- **`.github/dependabot.yml`** — weekly Composer and GitHub Actions dependency bump PRs.
- **`.github/workflows/codeql.yml`** — PHP CodeQL SAST scanning on push, PR, and weekly schedule; all Actions SHA-pinned.
- **Screenshots** — `docs/screenshots.md` with 13 annotated screenshots covering every plugin view (portfolio list, dashboard, task list, board, timeline, Gantt, milestones, milestone detail, dependency graph, blocked tasks, critical path, team workload, roadmap).

### Security

- Enabled Dependabot vulnerability alerts and automated security-fix PRs.
- Branch protection on `main`: force-push and deletion blocked.
- Tag protection ruleset: `deletion` + `non_fast_forward` on `refs/tags/v*`.
- `release` environment created with required reviewer gate.
- Default GitHub Actions workflow token locked to `read` permissions; GITHUB_TOKEN cannot approve PRs.

---

## [1.22.1] — 2026-03-25

### Changed
- Updated stale documentation references: spec version header, API method counts (28 → 31), install verification version

## [1.22.0] — 2026-03-25

### Added
- **Milestone assignment on task creation** — task creation form shows a milestone dropdown when the task's project belongs to a portfolio; selecting a milestone auto-adds the task on save (`template:task:form:first-column` hook + `task.create` listener)
- **Portfolio context banner** — task detail pages show "This task is in Portfolio: {name}" when the task belongs to a portfolio project
- **Project membership badge** — project header shows which portfolios the project belongs to
- **Board blocked icon** — 🔴 icon on board task cards for blocked tasks (using PortfolioHelper lazy cache, no extra queries)
- **Auto-complete milestones** — when all tasks in a milestone move to a done-pattern column, the milestone status automatically transitions to completed (configurable via `portfolio_auto_complete_milestones` setting, default `1`)
- **`task.update` and `task.assignee_change` event listeners** — registered for future critical-path cache invalidation and workload cache invalidation respectively

### Changed
- **PortfolioTaskModel query scoping** — `buildFilteredTaskRows()` now queries only tasks from portfolio projects via `IN (project_ids)` instead of loading the full `tasks` table; `buildTaskDependencyStats()` similarly scopes `task_has_links` to portfolio task IDs
- **DependencyModel query scoping** — `getDependencies()` now scopes `task_has_links` to portfolio task IDs via a two-step fetch (with >1000 task fallback to full-table for correctness)

### Configuration

| New Setting | Default | Description |
|-------------|---------|-------------|
| `portfolio_auto_complete_milestones` | `1` | Auto-transition milestone to completed when all its tasks reach a done column |

## [1.21.0] — 2026-03-25

### Added

- **`getPortfolioStatusReport` API** — structured status summary for agentic workflows: milestone health, completed tasks in period, critical blockers, at-risk items, and dependency health in one call
- **Weighted milestone progress** — `getMilestoneProgress` now accepts `weight_by` parameter (`count` / `score` / `time_estimated`); responses include `score_total`, `score_completed`, `time_total`, `time_completed`; default mode configurable via `portfolio_milestone_weight_by` setting
- **Weight-mode selector** — milestone detail page (`/milestone/:id`) shows a Count / Story Points / Time Estimated toggle that reloads the progress view; portfolio dashboard milestone health table adds Score and Est. Hours columns when data exists
- **`getPortfolioActivity` API** — returns recent activity events across all portfolio projects (up to 100, DESC chronological); enriched with `project_name`
- **Activity feed on portfolio dashboard** — "Recent Activity" section below milestone health showing date, event type, task link, and project
- **`getPortfolioWorkload` API** — per-user task metrics across all portfolio projects: active, overdue, blocked, score, estimated hours, spent hours, and per-project breakdown; unassigned bucket included; configurable overload threshold
- **Team / Workload view** — new tab at `/portfolio/:id/workload` with overload indicators (⚠ row highlight when active tasks exceed `portfolio_workload_threshold`), per-user links to filtered task list
- **Roadmap view** — new tab at `/portfolio/:id/roadmap` with D3.js horizontal milestone bars, health-coded colours (green/yellow/red), percentage-fill progress fill, dashed "Today" line, and click-to-navigate to milestone detail

### Changed

- **API method count**: 28 → 31 (`getPortfolioStatusReport`, `getPortfolioActivity`, `getPortfolioWorkload`)
- **Sidebar navigation**: added Team and Roadmap tabs

### Configuration

| New Setting | Default | Description |
|-------------|---------|-------------|
| `portfolio_milestone_weight_by` | `count` | Default weight mode for milestone progress (`count`, `score`, `time_estimated`) |
| `portfolio_workload_threshold` | `15` | Active-task count above which a user is flagged as overloaded |

## [1.20.0] — 2026-03-24

### Fixed
- **Event listeners broken** — `Plugin\Base::on()` passes the DI container to callbacks instead of the event object, causing all four event listeners (`task.close`, `task.open`, `task_internal_link.create_update`, `task_internal_link.delete`) to receive `task_id=0`. Replaced with `$this->dispatcher->addListener()` which passes the actual `TaskEvent`. Dependency-resolution notifications now fire correctly.
- **`getPluginName()` returned version string** — was returning `'1.17.0'` instead of `'Portfolio'`

## [1.19.0] — 2026-03-23

### Fixed
- **Board view** — columns now map to three canonical lanes (Not Started / In Progress / Done) instead of duplicating per-project columns; tasks from all projects stack together
- **Drag-and-drop** — rewrote for canonical lane architecture with per-project column resolution, DOMContentLoaded init, and embedded lane-column map
- **Empty lanes** — all three board lanes render even when empty so drag-and-drop targets are always available

## [1.18.0] — 2026-03-23

### Fixed
- **Critical path calculation** — use time-weighted edges (blocker due dates) instead of hop count; correctly identifies the chain that delays the final task the most
- **Duplicate dependency edges** — Kanboard's bidirectional link storage produced duplicate edges in the dependency graph, Gantt arrows, and blocked task lists
- **Gantt chart not rendering** — inline `<script>` blocks were blocked by Kanboard's Content-Security-Policy; moved all init logic into external JS with DOMContentLoaded auto-init
- **Gantt blocked task start dates** — tasks blocked by dependencies now start at the latest blocker's due date, not their creation date
- **Critical flag not persisted** — `Request::getValue()` consumed CSRF token on first call, making subsequent calls return null for `is_critical` and `position`
- **Plugin homepage URL** — corrected to match actual repository name

### Added
- **Gantt chart view** — D3.js duration bars, milestone diamonds, and cubic Bézier dependency arrows
- **Milestone status diamonds on Gantt** — intended target date (milestone color) and actual finish date (green if on time, red if late) with dashed connecting line
- **Drag-and-drop board cards** — move tasks between columns via HTML5 DnD with AJAX persistence
- **Critical path horizontal flow** — L-to-R layout with wrapping, replacing vertical list
- **Dashboard stats grid** — compact responsive grid replacing vertical list
- **Task filter grid layout** — horizontal responsive grid replacing stacked vertical filters
- **Timeline milestone project column** — shows portfolio name instead of blank

### Changed
- Board view cards now stack vertically within swimlane columns

## [1.17.0] — 2026-03-23

Initial working release of the Kanboard Portfolio plugin.
