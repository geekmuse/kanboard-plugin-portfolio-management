(function () {
    'use strict';

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

    window.PortfolioGantt = {
        render: render
    };
})();
