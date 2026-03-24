/**
 * Portfolio Gantt — timeline dot markers (legacy, used by timeline.php)
 * and D3.js Gantt chart renderer (used by gantt.php).
 *
 * Legacy API:  window.PortfolioGantt.render(container)
 * Gantt API:   window.PortfolioGantt.renderGantt(container, data)
 *
 * data shape for renderGantt:
 * {
 *   tasks:      [{ id, title, project_name, date_start, date_end, is_active }],
 *   milestones: [{ id, name, portfolio_name, date }],
 *   edges:      [{ from, to, is_resolved }],
 *   projects:   ['Project A', 'Project B']
 * }
 * All date values are Unix timestamps (seconds).
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  Legacy dot-marker renderer (timeline.php)                          */
    /* ------------------------------------------------------------------ */

    function parseItems(container) {
        if (!container) {
            return [];
        }

        var raw = container.getAttribute('data-items') || '[]';

        try {
            var items = JSON.parse(raw);
            return Array.isArray(items) ? items : [];
        } catch (error) {
            return [];
        }
    }

    function normalizeDate(value) {
        var timestamp = parseInt(value, 10);
        if (isNaN(timestamp) || timestamp <= 0) {
            return 0;
        }

        return timestamp;
    }

    function render(container) {
        if (!container) {
            return;
        }

        var items = parseItems(container);
        container.innerHTML = '';

        if (!items.length) {
            return;
        }

        var minDate = 0;
        var maxDate = 0;
        var i;

        for (i = 0; i < items.length; i++) {
            var itemDate = normalizeDate(items[i].date);
            if (itemDate === 0) {
                continue;
            }

            if (minDate === 0 || itemDate < minDate) {
                minDate = itemDate;
            }

            if (maxDate === 0 || itemDate > maxDate) {
                maxDate = itemDate;
            }
        }

        if (minDate === 0 || maxDate === 0) {
            return;
        }

        var range = maxDate - minDate;
        if (range <= 0) {
            range = 1;
        }

        for (i = 0; i < items.length; i++) {
            var item = items[i];
            var dateValue = normalizeDate(item.date);
            if (dateValue === 0) {
                continue;
            }

            var row = document.createElement('div');
            row.className = 'portfolio-timeline-row';

            var label = document.createElement('span');
            label.className = 'portfolio-timeline-label';
            label.textContent = '#' + (item.id || 0) + ' ' + (item.name || '');
            row.appendChild(label);

            var track = document.createElement('div');
            track.className = 'portfolio-timeline-track';

            var marker = document.createElement('span');
            marker.className = 'portfolio-timeline-marker portfolio-timeline-marker--' + (item.type || 'task');
            marker.style.left = (((dateValue - minDate) / range) * 100).toFixed(2) + '%';
            marker.title = (item.date_label || '') + ' - ' + (item.status || '');

            track.appendChild(marker);
            row.appendChild(track);
            container.appendChild(row);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  D3 Gantt chart renderer (gantt.php)                                */
    /* ------------------------------------------------------------------ */

    var PROJECT_COLORS = [
        '#4d7cfe', '#0f766e', '#d97706', '#7c3aed', '#db2777',
        '#059669', '#dc2626', '#2563eb', '#9333ea', '#d97706'
    ];

    function projectColor(projects, projectName) {
        var idx = projects.indexOf(projectName);
        if (idx < 0) {
            return '#9ca3af';
        }

        return PROJECT_COLORS[idx % PROJECT_COLORS.length];
    }

    function formatDateLabel(timestamp) {
        if (!timestamp || timestamp <= 0) { return ''; }
        var d = new Date(timestamp * 1000);
        var yyyy = d.getFullYear();
        var mm = ('0' + (d.getMonth() + 1)).slice(-2);
        var dd = ('0' + d.getDate()).slice(-2);
        return yyyy + '-' + mm + '-' + dd;
    }

    function renderGantt(container, data) {
        if (!container || typeof d3 === 'undefined') {
            return;
        }

        var tasks      = (data && Array.isArray(data.tasks))      ? data.tasks      : [];
        var milestones = (data && Array.isArray(data.milestones))  ? data.milestones : [];
        var edges      = (data && Array.isArray(data.edges))       ? data.edges      : [];
        var projects   = (data && Array.isArray(data.projects))    ? data.projects   : [];

        if (tasks.length === 0 && milestones.length === 0) {
            return;
        }

        /* -------------------------------------------------------------- */
        /*  Build rows (one per task + one per milestone)                  */
        /* -------------------------------------------------------------- */

        var rows = [];
        var rowIndexById = {};   // task/milestone id → row index

        var i;
        for (i = 0; i < tasks.length; i++) {
            var t = tasks[i];
            rowIndexById['t' + t.id] = rows.length;
            rows.push({ kind: 'task', item: t });
        }

        for (i = 0; i < milestones.length; i++) {
            var m = milestones[i];
            rowIndexById['m' + m.id] = rows.length;
            rows.push({ kind: 'milestone', item: m });
        }

        /* -------------------------------------------------------------- */
        /*  Compute time extent                                            */
        /* -------------------------------------------------------------- */

        var allDates = [];
        for (i = 0; i < tasks.length; i++) {
            if (tasks[i].date_start > 0) { allDates.push(tasks[i].date_start * 1000); }
            if (tasks[i].date_end   > 0) { allDates.push(tasks[i].date_end   * 1000); }
        }

        for (i = 0; i < milestones.length; i++) {
            if (milestones[i].date > 0) { allDates.push(milestones[i].date * 1000); }
        }

        if (allDates.length === 0) {
            return;
        }

        var minMs = d3.min(allDates);
        var maxMs = d3.max(allDates);
        var padMs = Math.max((maxMs - minMs) * 0.06, 7 * 24 * 3600 * 1000); // min 7-day pad
        var domainMin = new Date(minMs - padMs);
        var domainMax = new Date(maxMs + padMs);

        /* -------------------------------------------------------------- */
        /*  Layout constants                                               */
        /* -------------------------------------------------------------- */

        var ROW_H     = 34;
        var LABEL_W   = 200;
        var MARGIN    = { top: 24, right: 20, bottom: 36, left: LABEL_W };
        var containerW = Math.max(container.offsetWidth || 900, 500);
        var innerW    = containerW - MARGIN.left - MARGIN.right;
        var innerH    = rows.length * ROW_H;
        var totalH    = innerH + MARGIN.top + MARGIN.bottom;

        /* -------------------------------------------------------------- */
        /*  Scales                                                         */
        /* -------------------------------------------------------------- */

        var xScale = d3.scaleTime()
            .domain([domainMin, domainMax])
            .range([0, innerW]);

        /* -------------------------------------------------------------- */
        /*  SVG root                                                       */
        /* -------------------------------------------------------------- */

        d3.select(container).selectAll('*').remove();

        var svg = d3.select(container)
            .append('svg')
            .attr('width', containerW)
            .attr('height', totalH)
            .attr('class', 'portfolio-gantt-svg');

        /* Arrow marker definition */
        var defs = svg.append('defs');

        defs.append('marker')
            .attr('id', 'gantt-arrow')
            .attr('viewBox', '0 -5 10 10')
            .attr('refX', 8)
            .attr('refY', 0)
            .attr('markerWidth', 6)
            .attr('markerHeight', 6)
            .attr('orient', 'auto')
            .append('path')
            .attr('d', 'M0,-5L10,0L0,5')
            .attr('class', 'portfolio-gantt-arrow-head');

        defs.append('marker')
            .attr('id', 'gantt-arrow-resolved')
            .attr('viewBox', '0 -5 10 10')
            .attr('refX', 8)
            .attr('refY', 0)
            .attr('markerWidth', 6)
            .attr('markerHeight', 6)
            .attr('orient', 'auto')
            .append('path')
            .attr('d', 'M0,-5L10,0L0,5')
            .attr('class', 'portfolio-gantt-arrow-head portfolio-gantt-arrow-head--resolved');

        var g = svg.append('g')
            .attr('transform', 'translate(' + MARGIN.left + ',' + MARGIN.top + ')');

        /* -------------------------------------------------------------- */
        /*  Horizontal grid lines                                          */
        /* -------------------------------------------------------------- */

        for (i = 0; i <= rows.length; i++) {
            g.append('line')
                .attr('x1', 0).attr('x2', innerW)
                .attr('y1', i * ROW_H).attr('y2', i * ROW_H)
                .attr('class', 'portfolio-gantt-grid-line');
        }

        /* -------------------------------------------------------------- */
        /*  X axis                                                         */
        /* -------------------------------------------------------------- */

        g.append('g')
            .attr('transform', 'translate(0,' + innerH + ')')
            .attr('class', 'portfolio-gantt-axis')
            .call(d3.axisBottom(xScale).ticks(Math.min(8, Math.floor(innerW / 80))));

        /* -------------------------------------------------------------- */
        /*  Row labels (left panel)                                        */
        /* -------------------------------------------------------------- */

        var labelG = svg.append('g')
            .attr('transform', 'translate(0,' + MARGIN.top + ')');

        for (i = 0; i < rows.length; i++) {
            var row = rows[i];
            var y = i * ROW_H + ROW_H / 2;

            if (row.kind === 'task') {
                var taskItem = row.item;
                var color = projectColor(projects, taskItem.project_name || '');

                // Colour swatch
                labelG.append('rect')
                    .attr('x', 4)
                    .attr('y', y - 6)
                    .attr('width', 8)
                    .attr('height', 12)
                    .attr('rx', 2)
                    .attr('fill', color)
                    .attr('opacity', taskItem.is_active ? 1 : 0.45);

                // Label text
                labelG.append('text')
                    .attr('x', 16)
                    .attr('y', y + 4)
                    .attr('class', 'portfolio-gantt-row-label' + (taskItem.is_active ? '' : ' portfolio-gantt-row-label--closed'))
                    .text(('#' + taskItem.id + ' ' + (taskItem.title || '')).substring(0, 30));
            } else {
                // Milestone diamond swatch
                labelG.append('path')
                    .attr('d', function () {
                        var cx = 8; var cy = y;
                        return 'M' + cx + ',' + (cy - 7) + ' L' + (cx + 7) + ',' + cy + ' L' + cx + ',' + (cy + 7) + ' L' + (cx - 7) + ',' + cy + ' Z';
                    })
                    .attr('class', 'portfolio-gantt-milestone-diamond');

                labelG.append('text')
                    .attr('x', 20)
                    .attr('y', y + 4)
                    .attr('class', 'portfolio-gantt-row-label portfolio-gantt-row-label--milestone')
                    .text((row.item.name || '').substring(0, 30));
            }
        }

        /* Clipping rect for bars area */
        defs.append('clipPath')
            .attr('id', 'gantt-clip')
            .append('rect')
            .attr('width', innerW)
            .attr('height', innerH);

        var barsG = g.append('g').attr('clip-path', 'url(#gantt-clip)');

        /* -------------------------------------------------------------- */
        /*  Dependency arrows (draw before bars so bars appear on top)     */
        /* -------------------------------------------------------------- */

        for (i = 0; i < edges.length; i++) {
            var edge = edges[i];
            var fromRow = rowIndexById['t' + edge.from];
            var toRow   = rowIndexById['t' + edge.to];

            if (fromRow === undefined || toRow === undefined) {
                continue;
            }

            var fromTaskItem = rows[fromRow].item;
            var toTaskItem   = rows[toRow].item;

            if (!fromTaskItem.date_end || !toTaskItem.date_start) {
                continue;
            }

            var x1 = xScale(new Date(fromTaskItem.date_end * 1000));
            var y1 = fromRow * ROW_H + ROW_H / 2;
            var x2 = xScale(new Date(toTaskItem.date_start * 1000));
            var y2 = toRow * ROW_H + ROW_H / 2;

            // Simple elbow path: right from x1, down to y2, right to x2
            var midX = (x1 + x2) / 2;
            var pathD = 'M' + x1 + ',' + y1
                + ' C' + midX + ',' + y1 + ' ' + midX + ',' + y2 + ' ' + x2 + ',' + y2;

            barsG.append('path')
                .attr('d', pathD)
                .attr('class', 'portfolio-gantt-dep-edge' + (edge.is_resolved ? ' portfolio-gantt-dep-edge--resolved' : ''))
                .attr('marker-end', edge.is_resolved ? 'url(#gantt-arrow-resolved)' : 'url(#gantt-arrow)');
        }

        /* -------------------------------------------------------------- */
        /*  Task bars                                                       */
        /* -------------------------------------------------------------- */

        for (i = 0; i < rows.length; i++) {
            var rowEntry = rows[i];
            var yMid = i * ROW_H + ROW_H / 2;

            if (rowEntry.kind === 'task') {
                var task = rowEntry.item;
                if (!task.date_start || !task.date_end) {
                    continue;
                }

                var barX = xScale(new Date(task.date_start * 1000));
                var barX2 = xScale(new Date(task.date_end * 1000));
                var barW = Math.max(barX2 - barX, 4);
                var barH = 16;
                var barY = yMid - barH / 2;
                var taskColor = projectColor(projects, task.project_name || '');

                barsG.append('rect')
                    .attr('x', barX)
                    .attr('y', barY)
                    .attr('width', barW)
                    .attr('height', barH)
                    .attr('rx', 3)
                    .attr('fill', taskColor)
                    .attr('opacity', task.is_active ? 0.85 : 0.35)
                    .attr('class', 'portfolio-gantt-bar' + (task.is_active ? '' : ' portfolio-gantt-bar--closed'))
                    .append('title')
                    .text('#' + task.id + ' ' + (task.title || '') + '\n' + formatDateLabel(task.date_start) + ' → ' + formatDateLabel(task.date_end));

            } else {
                /* Milestone diamond */
                var ms = rowEntry.item;
                if (!ms.date) {
                    continue;
                }

                var mx = xScale(new Date(ms.date * 1000));
                var mSize = 9;

                barsG.append('path')
                    .attr('d', 'M' + mx + ',' + (yMid - mSize)
                        + ' L' + (mx + mSize) + ',' + yMid
                        + ' L' + mx + ',' + (yMid + mSize)
                        + ' L' + (mx - mSize) + ',' + yMid + ' Z')
                    .attr('class', 'portfolio-gantt-milestone-marker')
                    .append('title')
                    .text((ms.name || '') + '\n' + formatDateLabel(ms.date));
            }
        }

        /* -------------------------------------------------------------- */
        /*  Legend                                                         */
        /* -------------------------------------------------------------- */

        if (projects.length === 0 && milestones.length === 0) {
            return;
        }

        var legendG = svg.append('g')
            .attr('transform', 'translate(' + MARGIN.left + ',' + (totalH - 2) + ')')
            .attr('class', 'portfolio-gantt-legend');

        // (Legend items are built into the row labels; no separate legend needed unless
        //  there are many projects. For now a compact key is sufficient.)
    }

    /* ------------------------------------------------------------------ */
    /*  Public API                                                         */
    /* ------------------------------------------------------------------ */

    window.PortfolioGantt = {
        render: render,
        renderGantt: renderGantt
    };

    /* ------------------------------------------------------------------ */
    /*  Auto-init on DOMContentLoaded                                      */
    /*  (inline scripts are blocked by Kanboard's CSP)                     */
    /* ------------------------------------------------------------------ */

    document.addEventListener('DOMContentLoaded', function () {
        // Gantt chart (gantt.php) — reads from data-gantt attribute
        var ganttEl = document.getElementById('portfolio-gantt-chart');
        if (ganttEl) {
            var raw = ganttEl.getAttribute('data-gantt') || '{}';
            try {
                renderGantt(ganttEl, JSON.parse(raw));
            } catch (e) {
                ganttEl.innerHTML = '<p class="portfolio-empty-state">Failed to parse Gantt data.</p>';
            }
        }

        // Legacy timeline (timeline.php) — reads from data-items attribute
        var timelineEl = document.getElementById('portfolio-timeline-chart');
        if (timelineEl) {
            render(timelineEl);
        }
    });
})();
