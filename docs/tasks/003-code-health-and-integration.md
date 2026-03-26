# Code Health & Integration Improvements

**Date:** 2026-03-24
**Status:** ✅ Complete — shipped in v1.22.0
**Priority:** High (Enhancement 0 already applied)
**Source:** Derived from codebase audit and cross-reference against Kanboard core source

---

## Enhancement 0: Event Listener Fix ✅ DONE

**Severity:** Critical
**Status:** Applied 2026-03-24

Kanboard's `Plugin\Base::on()` passes the DI container to callbacks, not the event object. All four event listeners (`task.close`, `task.open`, `task_internal_link.create_update`, `task_internal_link.delete`) were silently receiving `task_id = 0`. Replaced with `$this->dispatcher->addListener()` which passes the actual `TaskEvent`.

Also fixed `getPluginName()` returning `'1.17.0'` instead of `'Portfolio'`.

### Verification Still Needed

- [ ] Test in a running Kanboard instance: close a blocking task → verify `portfolio.dependency.resolved` event fires
- [ ] Verify `NotifyDependencyResolved` and `CommentDependencyResolved` actions trigger correctly
- [ ] Add regression test that validates the event listener receives a real task_id

---

## Task 1: Leverage Unused Template Hooks ✅ DONE — v1.22.0

**Severity:** Low
**Effort:** Small (1-2 iterations)

The following Kanboard template hooks are available but not currently used by the plugin. Each offers incremental UX improvement.

| Hook | Proposed Use | Value |
|------|-------------|-------|
| `template:board:task:icons` | Add a blocked icon (🔴) alongside the existing footer indicator | Redundant with footer indicator but more visible; low effort |
| `template:task:show:top` | Portfolio context banner on task detail page ("This task is in Portfolio: Q2 Launch") | Improves cross-project awareness |
| `template:project:header:after` | Portfolio membership badge in project header | Quick visual confirmation |
| `template:task:form:first-column` | Portfolio/milestone assignment during task creation | Reduces steps for adding tasks to milestones |

### Acceptance Criteria

- [ ] At least `template:task:form:first-column` implemented (highest value — lets users assign tasks to milestones during creation)
- [ ] Each hook uses `attachCallable` with defensive null checks (same pattern as existing hooks)
- [ ] No N+1 queries introduced

---

## Task 2: Leverage Unused Core Events ✅ DONE — v1.22.0

**Severity:** Medium
**Effort:** Medium (2 iterations)

The plugin currently listens to 4 events. Additional core events could improve milestone progress tracking and dependency awareness.

| Event | Proposed Reaction | Value |
|-------|------------------|-------|
| `task.update` | If `date_due` or `priority` changed on a milestone task, recalculate critical path cache | Keeps dependency views fresh without requiring page reload |
| `task.move.column` | If a task moves to a "done"-pattern column, check if milestone should auto-complete | Enables auto-milestone-completion (spec §14.2 Q4) |
| `task.assignee_change` | If reassigned, update workload calculations (when Enhancement 4 from task 002 is built) | Prerequisite for workload view |

### Acceptance Criteria

- [ ] Use `$this->dispatcher->addListener()` (NOT `$this->on()`) per the fix in Enhancement 0
- [ ] `task.move.column` handler checks configurable `portfolio_auto_complete_milestones` setting
- [ ] Event handlers are defensive: fail silently if the task isn't in any portfolio
- [ ] Tests verify each event handler with mock event data

---

## Task 3: Query Performance Optimization ✅ DONE — v1.22.0

**Severity:** Medium (becomes High at scale)
**Effort:** Medium

Two model methods load full tables into memory for cross-driver compatibility:

| Method | Issue | Current Scale Limit |
|--------|-------|-------------------|
| `PortfolioTaskModel::buildFilteredTaskRows()` | `$this->db->table('tasks')->findAll()` loads ALL tasks in the DB | ~10K tasks before memory pressure |
| `DependencyModel::getDependencies()` | `$this->db->table('task_has_links')->findAll()` loads ALL links | ~50K links before memory pressure |

### Proposed Fix

Add a `getPortfolioProjectIds()` pre-filter step, then use `->in('project_id', $projectIds)` to scope the initial task query. For links, fetch only links where `task_id` is in the project-scoped task set.

This is safe for all three DB drivers (PicoDb's `->in()` generates standard SQL `IN (...)` clauses).

### Acceptance Criteria

- [ ] `buildFilteredTaskRows` queries only tasks from portfolio projects, not the full `tasks` table
- [ ] `getDependencies` queries only links involving portfolio tasks
- [ ] Existing test suite passes unchanged (behavior is identical, just fewer rows scanned)
- [ ] Add a benchmark test or note expected improvement for 10K+ task installations

---

## Task 4: Missing Test Coverage for Event System ✅ DONE — v1.22.0

**Severity:** Medium
**Effort:** Small (1 iteration)

The event listener fix (Enhancement 0) changed how events are registered but no existing test validates that the `dispatcher->addListener()` registration actually works correctly with real `TaskEvent` objects.

### Acceptance Criteria

- [ ] Test that `onTaskClosed` receives a valid task_id when `task.close` fires with a `TaskEvent`
- [ ] Test that `portfolio.dependency.resolved` is dispatched when a blocking task is closed
- [ ] Test that `onTaskOpened` and `onLinkChanged` are callable with expected parameters
- [ ] Tests use the existing test harness (lightweight stubs), not a running Kanboard instance

---

## Implementation Order — Complete ✅

```
Enhancement 0 (event listener fix)    → ✅ v1.20.0
Task 4 (event system tests)           → ✅ v1.22.0 (US-001)
Task 1 (template hooks)               → ✅ v1.22.0 (US-002, US-003)
Task 2 (core events)                  → ✅ v1.22.0 (US-004, US-005)
Task 3 (query optimization)           → ✅ v1.22.0 (US-006, US-007)
```

All tasks complete. **Actual effort: 7 ralphi loop iterations** across 1 PRD run.
