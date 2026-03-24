/**
 * Portfolio Board — HTML5 drag-and-drop card movement between canonical lanes.
 *
 * ES5-compatible; no external libraries required.
 *
 * Data attributes:
 *   .portfolio-board[data-move-task-url]          AJAX endpoint
 *   .portfolio-board[data-lane-column-map]        JSON: { projectId: { lane: columnId } }
 *   .portfolio-board-csrf [name="csrf_token"]     CSRF token
 *   .portfolio-board-column[data-lane]            Drop zone lane name (not_started|in_progress|done)
 *   .portfolio-board-card[data-task-id]           Draggable card
 *   .portfolio-board-card[data-project-id]        Card's project (for column lookup)
 *   .portfolio-board-card[data-column-id]         Card's current Kanboard column_id
 */
(function () {
    'use strict';

    // Lane position → lane key (matches controller output)
    var LANE_KEYS = { '1': 'not_started', '2': 'in_progress', '3': 'done' };

    function init() {
        var board = document.querySelector('.portfolio-board[data-move-task-url]');
        if (!board) { return; }

        var moveUrl = board.getAttribute('data-move-task-url') || '';
        var errorLabel = board.getAttribute('data-move-error-label') || 'Failed to move task.';

        var laneColumnMap = {};
        try {
            laneColumnMap = JSON.parse(board.getAttribute('data-lane-column-map') || '{}');
        } catch (e) { laneColumnMap = {}; }

        var csrfInput = document.querySelector('.portfolio-board-csrf [name="csrf_token"]');
        var csrfToken = csrfInput ? csrfInput.value : '';

        var draggedCard = null;
        var draggedSourceColumn = null;

        // -- Card events --
        var cards = board.querySelectorAll('.portfolio-board-card[data-task-id]');
        var i;
        for (i = 0; i < cards.length; i++) {
            (function (card) {
                card.addEventListener('dragstart', function (e) {
                    draggedCard = card;
                    draggedSourceColumn = findParentColumn(card, board);
                    card.classList.add('portfolio-board-card--dragging');
                    if (e.dataTransfer) {
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', card.getAttribute('data-task-id') || '');
                    }
                });

                card.addEventListener('dragend', function () {
                    card.classList.remove('portfolio-board-card--dragging');
                    clearDropTargets(board);
                    draggedCard = null;
                    draggedSourceColumn = null;
                });
            })(cards[i]);
        }

        // -- Column (drop zone) events --
        var columns = board.querySelectorAll('.portfolio-board-column[data-lane]');
        for (i = 0; i < columns.length; i++) {
            (function (column) {
                column.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (e.dataTransfer) { e.dataTransfer.dropEffect = 'move'; }
                    column.classList.add('portfolio-board-column--drop-target');
                });

                column.addEventListener('dragleave', function (e) {
                    var related = e.relatedTarget;
                    if (!related || !column.contains(related)) {
                        column.classList.remove('portfolio-board-column--drop-target');
                    }
                });

                column.addEventListener('drop', function (e) {
                    e.preventDefault();
                    column.classList.remove('portfolio-board-column--drop-target');

                    if (!draggedCard || !draggedSourceColumn) { return; }
                    if (column === draggedSourceColumn) { return; }

                    var taskId = draggedCard.getAttribute('data-task-id') || '';
                    var projectId = draggedCard.getAttribute('data-project-id') || '';
                    var lanePosition = column.getAttribute('data-lane') || '';
                    var laneKey = LANE_KEYS[lanePosition] || '';

                    if (!taskId || !laneKey || !projectId) { return; }

                    // Resolve the target column_id for this task's project + lane
                    var projectMap = laneColumnMap[projectId] || {};
                    var targetColumnId = projectMap[laneKey];

                    if (!targetColumnId) {
                        showError(board, errorLabel + ' (No matching column for this project.)');
                        return;
                    }

                    var card = draggedCard;
                    var sourceCol = draggedSourceColumn;

                    // Optimistic DOM move
                    column.appendChild(card);
                    card.setAttribute('data-column-id', String(targetColumnId));
                    updateColumnCount(sourceCol, -1);
                    updateColumnCount(column, 1);

                    sendMoveRequest(moveUrl, taskId, targetColumnId, csrfToken, card, sourceCol, column, errorLabel, board);
                });
            })(columns[i]);
        }
    }

    function findParentColumn(el, board) {
        var node = el.parentNode;
        while (node && node !== board) {
            if (node.classList && node.classList.contains('portfolio-board-column')) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    function sendMoveRequest(moveUrl, taskId, columnId, csrfToken, card, sourceColumn, targetColumn, errorLabel, board) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', moveUrl, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }

            var success = false;
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    success = !!(data && data.success === true);
                } catch (ignore) { success = false; }
            }

            if (!success) {
                sourceColumn.appendChild(card);
                updateColumnCount(targetColumn, -1);
                updateColumnCount(sourceColumn, 1);
                showError(board, errorLabel);
            }
        };

        var formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('column_id', String(columnId));
        if (csrfToken) { formData.append('csrf_token', csrfToken); }
        xhr.send(formData);
    }

    function updateColumnCount(column, delta) {
        var countEl = column.querySelector('.portfolio-board-column-count');
        if (!countEl) { return; }
        var current = parseInt(countEl.textContent || '0', 10);
        if (isNaN(current)) { current = 0; }
        countEl.textContent = String(Math.max(0, current + delta));
    }

    function clearDropTargets(board) {
        var targets = board.querySelectorAll('.portfolio-board-column--drop-target');
        for (var i = 0; i < targets.length; i++) {
            targets[i].classList.remove('portfolio-board-column--drop-target');
        }
    }

    function showError(board, message) {
        var parent = board.parentNode;
        if (!parent) { return; }

        var existing = parent.querySelector('.portfolio-board-error');
        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
        }

        var errorEl = document.createElement('div');
        errorEl.className = 'portfolio-board-error alert alert-error';
        errorEl.textContent = message;
        parent.insertBefore(errorEl, board);

        window.setTimeout(function () {
            if (errorEl.parentNode) { errorEl.parentNode.removeChild(errorEl); }
        }, 5000);
    }

    // Defer init — script is loaded with "defer" attribute
    document.addEventListener('DOMContentLoaded', init);
}());
