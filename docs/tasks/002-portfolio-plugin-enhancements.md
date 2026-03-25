# Portfolio Plugin Enhancement Plan — Planning Depth & Agentic Readiness

**Date:** 2026-03-24
**Status:** ✅ Complete — shipped in v1.21.0
**Priority:** High
**Depends on:** None (all enhancements are additive to the existing v1.19.0 codebase)

---

## Overview

This task plan addresses gaps identified in the comprehensive analysis that should be built **within the Portfolio plugin** (not as separate plugins). These are enhancements that extend the plugin's existing capabilities without changing its architectural boundaries.

> **VALIDATION UPDATE (2026-03-24):** Enhancement 0 (event listener fix) was added after cross-referencing Plugin.php against Kanboard core source code. See `VALIDATION-REPORT.md` for the full analysis. The fix has already been applied to Plugin.php.

---

## Enhancement 0: Critical Event Listener Fix ✅ DONE

**Severity:** 🔴 Critical — showstopper
**Effort:** Trivial (already applied)

### Root Cause

`Kanboard\Core\Plugin\Base::on()` wraps the callback and passes the **DI container** as the sole argument — NOT the Symfony event object. The plugin's four event listeners (`task.close`, `task.open`, `task_internal_link.create_update`, `task_internal_link.delete`) all access `$event['task_id']`, but `$event` was actually the Pimple container, so `task_id` resolved to `null`.

**This means ALL event-driven functionality (dependency resolution notifications, `onTaskClosed`, `onTaskOpened`, `onLinkChanged`) was silently broken since the plugin was first released.**

### Fix Applied

Replaced `$this->on(...)` calls with `$this->dispatcher->addListener(...)` which passes the actual `TaskEvent` object (implements `ArrayAccess`, contains `task_id`):

```php
// BEFORE (broken):
$this->on('task.close', function ($event) {
    $taskId = (int) ($event['task_id'] ?? 0); // $event = DI container, task_id = null
    $this->dependencyModel->onTaskClosed($taskId);
});

// AFTER (fixed):
$container = $this->container;
$this->dispatcher->addListener('task.close', function ($event) use ($container) {
    $taskId = (int) ($event['task_id'] ?? 0); // $event = TaskEvent, task_id = actual ID
    $container['dependencyModel']->onTaskClosed($taskId);
});
```

### Also Fixed

`Plugin.php::getPluginName()` returned `'1.17.0'` instead of `'Portfolio'`.

### Files Modified

- `Plugin.php` — 4 event listeners + getPluginName()

### Verification Needed

- [ ] Test in a running Kanboard instance: close a blocking task and verify the `portfolio.dependency.resolved` event fires
- [ ] Verify notification and comment automatic actions trigger correctly
- [ ] Add a test case that validates the event listener receives task_id correctly

---

## Enhancement 1: Weighted Milestone Progress ✅ DONE — v1.21.0

**Gap Ref:** GAP-03, GAP-13
**Severity:** Important
**Effort:** Small (1-2 iterations)

### Scope

Add a `weight_by` parameter to `getMilestoneProgress` and `MilestoneModel::getProgress()` that supports three weighting modes:

| Mode | Behavior |
|------|----------|
| `count` (default) | Current behavior: completed/total tasks |
| `score` | Sum of `tasks.score` for completed / total score |
| `time_estimated` | Sum of `tasks.time_estimated` for completed / total estimated time |

### Acceptance Criteria

- [ ] `getMilestoneProgress(milestone_id, weight_by?)` API method accepts optional `weight_by` parameter
- [ ] `getPortfolioOverview` milestone summaries include `score_total`, `score_completed`, `time_total`, `time_completed` fields
- [ ] Milestone detail template shows a weight-mode selector (count/score/time) with dynamic progress bar
- [ ] Portfolio dashboard milestone health section uses configured default weight mode
- [ ] Add `portfolio_milestone_weight_by` config setting (default: `count`)
- [ ] 4+ unit tests for each weight mode
- [ ] API backward compatible — omitting `weight_by` preserves current behavior

### Files to Modify

- `Model/MilestoneModel.php` — extend `getProgress()` with weight calculation
- `Model/PortfolioTaskModel.php` — extend `getOverviewMilestones()` with score/time aggregation
- `Plugin.php` — update `getMilestoneProgress` API callback to accept `weight_by`
- `Template/milestone/show.php` — weight mode selector
- `Template/portfolio/show.php` — show score/time columns in milestone health table
- `Template/config/settings.php` — add `portfolio_milestone_weight_by` setting
- `Controller/ConfigController.php` — handle new setting
- `Locale/en_US/translations.php` — new strings
- `Test/Model/MilestoneModelTest.php` — new test cases

### Dependencies

None.

### Risks

- Kanboard's `tasks.score` field may be 0 for most tasks if teams don't use it. The UI should show "No score data available" when total_score is 0.

---

## Enhancement 2: Status Report API Method ✅ DONE — v1.21.0

**Gap Ref:** GAP-10
**Severity:** Important
**Effort:** Small (1 iteration)

### Scope

Add a `getPortfolioStatusReport` API method that generates a structured status summary. This is the key enabler for agentic reporting workflows.

### API Contract

```
getPortfolioStatusReport(portfolio_id, period_days = 7)

Returns:
{
  "portfolio": { name, id },
  "generated_at": timestamp,
  "period_start": timestamp,
  "period_end": timestamp,
  "milestones": [
    { name, percent, health, blocked_count, target_date, days_remaining }
  ],
  "task_summary": {
    "total": N, "active": N, "closed": N, "blocked": N,
    "completed_this_period": N,
    "created_this_period": N
  },
  "completed_tasks": [
    { id, title, project_name, completed_at }  // tasks closed in period
  ],
  "critical_blockers": [
    { id, title, project_name, blocked_count, days_blocked }
  ],
  "at_risk_items": [
    { type, id, name, reason }  // milestones + overdue tasks
  ],
  "dependency_health": {
    "total_edges": N,
    "resolved": N,
    "unresolved": N,
    "critical_path_length": N
  }
}
```

### Acceptance Criteria

- [ ] `getPortfolioStatusReport` API method registered in Plugin.php
- [ ] Method computes "completed this period" by querying `tasks.date_completed` within the period
- [ ] Method includes all at-risk milestones and overdue tasks
- [ ] Method is read-only (no access map entry needed — defaults to APP_USER)
- [ ] 3+ unit tests (empty portfolio, portfolio with data, custom period)
- [ ] Result is JSON-serializable and suitable for AI agent consumption

### Files to Create/Modify

- `Model/PortfolioTaskModel.php` — add `getStatusReport()` method
- `Plugin.php` — register `getPortfolioStatusReport` API callback
- `Test/Model/PortfolioTaskModelTest.php` — new test cases
- `Locale/en_US/translations.php` — any new strings

### Dependencies

None.

---

## Enhancement 3: Portfolio Activity Stream ✅ DONE — v1.21.0

**Gap Ref:** GAP-14
**Severity:** Moderate
**Effort:** Medium (2 iterations)

### Scope

Aggregate task activity from all portfolio projects into a unified activity feed. Kanboard core provides `getProjectActivities(array $project_ids)` — a JSON-RPC method (in `ProjectProcedure.php`) that accepts an array of project IDs and returns combined activity. We can call this directly via the core model layer rather than querying the `project_activities` table.

### API Contract

```
getPortfolioActivity(portfolio_id, limit = 25, offset = 0)

Returns:
[
  {
    "id": activity_id,
    "project_id": N,
    "project_name": "...",
    "task_id": N,
    "task_title": "...",
    "event_name": "task.close",
    "creator_id": N,
    "creator_name": "...",
    "date_creation": timestamp,
    "data": { ... }  // event-specific data
  }
]
```

### Acceptance Criteria

- [ ] `getPortfolioActivity` API method registered
- [ ] Queries `project_activities` table joined to `portfolio_has_projects`
- [ ] Activity feed shown on portfolio dashboard (new section below overview)
- [ ] Paginated with configurable limit
- [ ] Template renders activity items with task links and timestamps
- [ ] 3+ unit tests

### Files to Create/Modify

- `Model/PortfolioTaskModel.php` — add `getActivity()` method
- `Plugin.php` — register API callback
- `Controller/PortfolioViewController.php` — add activity data to `show()` response
- `Template/portfolio/show.php` — new activity feed section
- `Locale/en_US/translations.php` — new strings
- Tests

### Dependencies

None.

### Risks

- Core's `projectActivityHelper->getProjectsEvents()` method may have different availability across Kanboard versions. Defensive try/catch + fallback to direct table query.
- Activity volume: large portfolios may generate hundreds of events per day. Default limit + pagination is essential.

---

## Enhancement 4: Resource / Workload Overview ✅ DONE — v1.21.0

**Gap Ref:** GAP-05, GAP-06
**Severity:** Important
**Effort:** Medium (2 iterations)

### Scope

Add a "Team" or "Workload" view to portfolios showing per-user task allocation across all portfolio projects.

### API Contract

```
getPortfolioWorkload(portfolio_id)

Returns:
{
  "users": [
    {
      "user_id": N,
      "username": "...",
      "name": "...",
      "task_count": N,
      "active_task_count": N,
      "overdue_task_count": N,
      "blocked_task_count": N,
      "total_score": N,
      "total_estimated_hours": F,
      "total_spent_hours": F,
      "projects": [
        { "project_id": N, "project_name": "...", "task_count": N }
      ]
    }
  ],
  "unassigned": {
    "task_count": N,
    "active_task_count": N
  }
}
```

### Acceptance Criteria

- [ ] `getPortfolioWorkload` API method registered
- [ ] New "Team" tab in portfolio sidebar navigation
- [ ] Template shows a table/grid with per-user metrics
- [ ] Color-coded indicators for overloaded users (e.g., >15 active tasks)
- [ ] Configurable overload threshold via `portfolio_workload_threshold` setting
- [ ] Click-through from user row to filtered task list (pre-filtered by assignee)
- [ ] 3+ unit tests

### Files to Create/Modify

- `Model/PortfolioTaskModel.php` — add `getWorkload()` method
- `Plugin.php` — register API callback + route
- `Controller/PortfolioViewController.php` — add `workload()` action
- `Template/portfolio/workload.php` — new template
- `Template/portfolio/_sidebar.php` — add "Team" link
- `Template/config/settings.php` — add threshold setting
- `Asset/css/portfolio.css` — workload styles
- `Locale/en_US/translations.php` — new strings
- Tests

### Dependencies

Enhancement 1 (weighted progress) is helpful but not required.

---

## Enhancement 5: Roadmap View ✅ DONE — v1.21.0

**Gap Ref:** GAP-07
**Severity:** Important
**Effort:** Medium (2 iterations)

### Scope

A high-level timeline showing milestones as horizontal bars (start date derived from earliest task, end date from target_date), with optional Epic bars if the Epic plugin is installed. This is distinct from the existing Gantt (which is task-level) — the Roadmap is for stakeholder communication.

### Acceptance Criteria

- [ ] New "Roadmap" tab in portfolio sidebar
- [ ] D3.js horizontal bars for each milestone (earliest task date → target_date)
- [ ] Milestone bars color-coded by health (on-track=green, at-risk=yellow, overdue=red)
- [ ] Progress fill within each bar (e.g., 60% filled means 60% complete)
- [ ] Today line
- [ ] Click-through to milestone detail page
- [ ] If Epic plugin detected: optional Epic-level bars above milestones
- [ ] Responsive: scales to narrow screens
- [ ] 2+ controller tests

### Files to Create/Modify

- `Controller/PortfolioViewController.php` — add `roadmap()` action
- `Plugin.php` — register route
- `Template/portfolio/roadmap.php` — new template
- `Template/portfolio/_sidebar.php` — add "Roadmap" link
- `Asset/js/portfolio-gantt.js` — add `renderRoadmap()` function
- `Asset/css/portfolio.css` — roadmap styles
- Tests

### Dependencies

None. Enhancement 1 (weighted progress) improves accuracy but isn't required.

---

## Enhancement 6: Plugin.php Bug Fix ✅ DONE — v1.20.0

**Severity:** Low (cosmetic)
**Effort:** Trivial

### Issue

`Plugin.php::getPluginName()` returns `'1.17.0'` instead of `'Portfolio'`.

### Fix

```php
public function getPluginName()
{
    return 'Portfolio';
}
```

### Files to Modify

- `Plugin.php` line ~364

---

## Implementation Order — Complete ✅

```
Enhancement 0 (event listener fix)   → ✅ v1.20.0
Enhancement 6 (getPluginName fix)    → ✅ v1.20.0
Enhancement 2 (status report)        → ✅ v1.21.0 (US-001)
Enhancement 1 (weighted progress)    → ✅ v1.21.0 (US-002, US-003)
Enhancement 3 (activity stream)      → ✅ v1.21.0 (US-004, US-005)
Enhancement 4 (workload)             → ✅ v1.21.0 (US-006, US-007)
Enhancement 5 (roadmap)              → ✅ v1.21.0 (US-008, US-009)
```

All enhancements complete. **Actual effort: 9 ralphi loop iterations** across 1 PRD run.

---

## Non-Goals (Belongs in Separate Plugins)

The following are explicitly NOT part of this task plan:

| Capability | Why Not Here |
|-----------|-------------|
| Epics | Different entity, different scope — see `PROPOSAL-epics-plugin.md` |
| Sprint management | Project-scoped, not portfolio-scoped — separate plugin |
| Agent access control | Auth infrastructure — see `PROPOSAL-agent-access-control.md` |
| MCP server | External service — see `PROPOSAL-mcp-kanboard-server.md` |
| Burndown/velocity charts | Requires historical snapshot infrastructure — future phase |
| Portfolio-level permissions | Significant complexity — future enhancement |
