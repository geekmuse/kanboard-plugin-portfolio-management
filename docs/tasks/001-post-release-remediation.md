# Issue Analysis & Remediation Plan

## Issue Classification & Root Cause Analysis

### ~~ISSUE-1: Bump to 1.17.0~~ ✅ DONE

---

### ISSUE-2: Update Plugin.php with correct user/URL refs

| Field | Value |
|-------|-------|
| **Type** | Configuration defect |
| **Severity** | Low |
| **Effort** | Small |

**Root cause:** `Plugin.php:371` returns `https://github.com/geekmuse/kanboard-plugin-portfolio` but the actual repo is `kanboard-plugin-portfolio-management`.

**Files:**
- `Plugin.php` line 371 — `getPluginHomepage()` returns wrong URL

**Fix:** Update the return value to the correct repo URL.

**Blast radius:** None — cosmetic only, affects the plugin info page in Kanboard Settings.

---

### ISSUE-3: Critical flag for milestone tasks doesn't render

| Field | Value |
|-------|-------|
| **Type** | Bug — data integrity |
| **Severity** | High |
| **Effort** | Small |

**Root cause:** `MilestoneController::addTask()` calls `$this->request->getValue()` three times:

```php
$taskId     = (int) $this->request->getValue('task_id', 0);     // 1st call
$isCritical = (int) $this->request->getValue('is_critical', 0); // 2nd call
$position   = (int) $this->request->getValue('position', 0);    // 3rd call
```

Kanboard's `Request::getValue($name)` internally calls `getValues()`, which validates and **consumes** the CSRF token on the first call. Subsequent `getValues()` calls see the token already consumed and return an **empty array**. Therefore `$isCritical` and `$position` are always `0`.

**Files:**
- `Controller/MilestoneController.php` lines 231-233

**Fix:** Call `getValues()` once, then read all fields from the result:

```php
$values = $this->request->getValues();
$taskId     = (int) ($values['task_id'] ?? 0);
$isCritical = (int) ($values['is_critical'] ?? 0);
$position   = (int) ($values['position'] ?? 0);
```

Apply the same pattern to `removeTask()` (line 252).

**Blast radius:** Also affects `position` on task add, and `task_id` on task remove. The `task_id` on `addTask()` works only because it's the first `getValue()` call.

**Verification:** Add a task to a milestone with "Mark as Critical" checked. The task list should show "Yes" in the Critical column.

---

### ISSUE-4: Board stacks items horizontally instead of vertical swimlanes

| Field | Value |
|-------|-------|
| **Type** | UX defect |
| **Severity** | High |
| **Effort** | Medium |

**Root cause:** `buildBoardColumns()` groups tasks by `column_id` (Kanboard column — "Backlog", "In Progress", "Done") and the template renders columns as `display: flex; flex-wrap: wrap` with `flex: 1 1 280px` — this produces side-by-side cards within each column. However, the real problem is the **column containers themselves** stack horizontally like a Kanban board, but the cards within each column are also horizontal instead of vertical.

**Files:**
- `Asset/css/portfolio.css` — `.portfolio-board` and `.portfolio-board-column`
- `Template/portfolio/board.php` — card layout within columns

**Fix:**
1. `.portfolio-board` stays `display: flex` (columns side by side = correct Kanban layout)
2. Each `.portfolio-board-column` should stack cards vertically — remove `flex-wrap` from the column's card container, ensure cards are block-level
3. Add `.portfolio-board-card { display: block; margin-bottom: 0.5rem; }` to stack cards vertically within a column

**Verification:** Board page should show columns L-to-R (Backlog | In Progress | Done) with cards stacked vertically within each column.

---

### ISSUE-5: Movable cards in board view

| Field | Value |
|-------|-------|
| **Type** | Feature request |
| **Severity** | Medium |
| **Effort** | Large |

**Analysis:** This requires drag-and-drop between columns (changing a task's `column_id`), which means writing to the core `tasks` table — violating the plugin's "write only to plugin tables" constraint. Two approaches:

**Option A — Use Kanboard's existing task update API:**
The plugin can call `$this->taskModificationModel->update(['id' => $taskId, 'column_id' => $newColumnId])` which goes through Kanboard's core update pipeline (triggers events, respects permissions). This is safe because it uses Kanboard's own model, not raw SQL.

**Option B — Link to native board:**
Add a link on each card that opens the task in the project's native board (which already supports drag-and-drop).

**Recommended:** Option A with sortable.js or HTML5 drag-and-drop. Requires a new AJAX endpoint in the controller.

**Dependencies:** ISSUE-4 must be fixed first (vertical card layout).

**Blast radius:** Touches core task data. Must respect project-level permissions and fire task events.

---

### ISSUE-6: Timeline milestone "Project" column

| Field | Value |
|-------|-------|
| **Type** | Verify / UX question |
| **Severity** | Low |
| **Effort** | Small |

**Analysis:** Milestones are scoped to **portfolios**, not projects. The timeline table has a "Project" column, but for milestone rows, `project_name` is set to `''` (empty string) in `buildTimelineData()`. This displays as a blank cell, which is confusing.

**Fix:** For milestone rows, display the portfolio name instead, or show "—" with a tooltip explaining milestones span projects. Update `buildTimelineData()` to pass `portfolio['name']` for milestone items.

**Verification:** Timeline table should show meaningful data in the Project column for both tasks and milestones.

---

### ISSUE-7: Critical path should flow L-to-R, top-to-bottom

| Field | Value |
|-------|-------|
| **Type** | UX defect |
| **Severity** | Medium |
| **Effort** | Medium |

**Root cause:** The critical path template (`Template/dependency/critical_path.php`) renders as a vertical `<ol>` with `↓` connectors between items. The user expects a left-to-right flow for short paths, wrapping top-to-bottom for longer ones.

**Files:**
- `Template/dependency/critical_path.php` — markup structure
- `Asset/css/portfolio.css` — critical path styles

**Fix:** Restructure as a horizontal flow layout:
1. Use `display: flex; flex-wrap: wrap` for the path container
2. Each node is an inline card with `→` connectors between items
3. Items wrap to the next line naturally when the row fills
4. Use `→` between items on the same row; the wrap itself provides the top-to-bottom flow

**Verification:** A critical path of 6 items should display as e.g., 4 items L-to-R, then 2 items on the next row.

---

### ISSUE-8: Gantt view needed

| Field | Value |
|-------|-------|
| **Type** | Feature request |
| **Severity** | Medium |
| **Effort** | Large |

**Analysis:** The current timeline is a dot-marker chart (single-point per item). A proper Gantt view needs:
- Horizontal bars representing duration (start date → due date / target date)
- D3.js or a dedicated Gantt library
- Task dependencies shown as arrows between bars

The existing `portfolio-gantt.js` is a simple marker renderer. It would need to be replaced with a proper Gantt implementation using D3.js (already bundled).

**Dependencies:** D3.js is already bundled. The data pipeline (`buildTimelineData`) needs to include start dates and task dependencies.

---

### ISSUE-9: Task list filters stacked vertically, consuming too much page space

| Field | Value |
|-------|-------|
| **Type** | UX defect |
| **Severity** | Medium |
| **Effort** | Small |

**Root cause:** The filter form in `Template/portfolio/tasks.php` uses `.portfolio-form-row` divs which are block-level (full width). Each filter takes one full row, pushing the actual task table far down the page.

**Files:**
- `Template/portfolio/tasks.php` — filter form markup
- `Asset/css/portfolio.css` — `.portfolio-form-row` and `.portfolio-task-filter-form`

**Fix:** Wrap the filters in a CSS grid or flexbox layout:

```css
.portfolio-task-filter-form {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.5rem 1rem;
    align-items: end;
    margin-bottom: 1rem;
}
```

This makes filters flow L-to-R in a responsive grid, collapsing to fewer columns on narrow screens.

**Verification:** Filters should occupy 2-3 rows maximum on a typical 1280px-wide screen instead of 8+ rows.

---

### ISSUE-10: Dashboard stats as vertical list — should be a compact table

| Field | Value |
|-------|-------|
| **Type** | UX defect |
| **Severity** | Medium |
| **Effort** | Small |

**Root cause:** `Template/portfolio/show.php` renders overview metrics as a `<ul>` list with one metric per line, taking significant vertical space.

**Files:**
- `Template/portfolio/show.php` — overview metrics section

**Fix:** Replace the `<ul>` with a compact grid or horizontal stats bar:

```css
.portfolio-dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 0.5rem;
}
```

Each stat becomes a card-style cell showing the label and value.

**Verification:** Dashboard metrics should display as a compact 2-4 column grid instead of an 8-line list.

---

## Prioritized Remediation Plan

| # | Issue | Type | Severity | Effort | Dependencies |
|---|-------|------|----------|--------|-------------|
| 1 | ISSUE-3: Critical flag not persisted | Bug | High | Small | None |
| 2 | ISSUE-4: Board vertical card layout | UX | High | Medium | None |
| 3 | ISSUE-9: Task filter grid layout | UX | Medium | Small | None |
| 4 | ISSUE-10: Dashboard stats grid | UX | Medium | Small | None |
| 5 | ISSUE-2: Plugin URL/author refs | Config | Low | Small | None |
| 6 | ISSUE-7: Critical path L-to-R flow | UX | Medium | Medium | None |
| 7 | ISSUE-6: Timeline milestone project col | UX | Low | Small | None |
| 8 | ISSUE-5: Movable board cards | Feature | Medium | Large | ISSUE-4 |
| 9 | ISSUE-8: Gantt view | Feature | Medium | Large | None |

### Implementation Order

**Phase 1 — Quick wins (Small effort, can be done in one pass):**
1. ISSUE-3 (critical flag) — data corruption bug, highest priority
2. ISSUE-2 (plugin URL)
3. ISSUE-9 (filter grid CSS)
4. ISSUE-10 (dashboard stats CSS)
5. ISSUE-6 (timeline milestone project column)

**Phase 2 — Medium effort:**
6. ISSUE-4 (board card stacking)
7. ISSUE-7 (critical path L-to-R)

**Phase 3 — Large features:**
8. ISSUE-5 (movable board cards) — depends on ISSUE-4
9. ISSUE-8 (Gantt view)

### Risks

| Fix | Risk | Mitigation |
|-----|------|------------|
| ISSUE-3 | Changing `getValue()` to `getValues()` affects CSRF flow | Already validated: `getValues()` handles CSRF internally |
| ISSUE-4 | CSS changes could break other board-like views | Scope CSS to `.portfolio-board-column` children only |
| ISSUE-5 | Writing to core `tasks` table | Use `taskModificationModel->update()` through Kanboard's API |
| ISSUE-8 | D3 Gantt is complex; risk of scope creep | Start with read-only bars, add dependency arrows later |
