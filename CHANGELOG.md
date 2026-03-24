# Changelog

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
