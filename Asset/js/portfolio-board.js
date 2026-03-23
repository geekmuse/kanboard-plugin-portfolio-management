/**
 * Portfolio Board — HTML5 drag-and-drop card movement between columns.
 *
 * ES5-compatible; no external libraries required.
 * Relies on .portfolio-board[data-move-task-url] for the AJAX endpoint,
 * .portfolio-board-csrf [name="csrf_token"] for the CSRF token,
 * .portfolio-board-column[data-column-id] as drop zones, and
 * .portfolio-board-card[data-task-id] as draggable items.
 */
/* global window, document, XMLHttpRequest, FormData */
(function () {
    'use strict';

    var board = document.querySelector('.portfolio-board[data-move-task-url]');
    if (!board) { return; }

    var moveUrl = board.getAttribute('data-move-task-url') || '';
    var errorLabel = board.getAttribute('data-move-error-label') || 'Failed to move task.';

    var csrfInput = document.querySelector('.portfolio-board-csrf [name="csrf_token"]');
    var csrfToken = csrfInput ? csrfInput.value : '';

    /** @type {Element|null} */
    var draggedCard = null;
    /** @type {Element|null} */
    var draggedSourceColumn = null;

    // -----------------------------------------------------------------------
    // Initialisation
    // -----------------------------------------------------------------------

    function init() {
        var cards = board.querySelectorAll('.portfolio-board-card[data-task-id]');
        var columns = board.querySelectorAll('.portfolio-board-column[data-column-id]');
        var i;
        for (i = 0; i < cards.length; i++) {
            initCard(cards[i]);
        }
        for (i = 0; i < columns.length; i++) {
            initColumn(columns[i]);
        }
    }

    // -----------------------------------------------------------------------
    // Card events
    // -----------------------------------------------------------------------

    function initCard(card) {
        card.addEventListener('dragstart', function (e) {
            draggedCard = card;
            draggedSourceColumn = findParentColumn(card);
            card.classList.add('portfolio-board-card--dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.getAttribute('data-task-id') || '');
            }
        });

        card.addEventListener('dragend', function () {
            card.classList.remove('portfolio-board-card--dragging');
            clearDropTargets();
            draggedCard = null;
            draggedSourceColumn = null;
        });
    }

    function findParentColumn(el) {
        var node = el.parentNode;
        while (node && node !== board) {
            if (node.classList && node.classList.contains('portfolio-board-column')) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    // -----------------------------------------------------------------------
    // Column (drop zone) events
    // -----------------------------------------------------------------------

    function initColumn(column) {
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
            var columnId = column.getAttribute('data-column-id') || '';
            if (!taskId || !columnId || columnId === '0') { return; }

            var card = draggedCard;
            var sourceCol = draggedSourceColumn;

            // Optimistic DOM move — revert on AJAX failure.
            column.appendChild(card);
            updateColumnCount(sourceCol, -1);
            updateColumnCount(column, 1);

            sendMoveRequest(taskId, columnId, card, sourceCol, column);
        });
    }

    // -----------------------------------------------------------------------
    // AJAX persistence
    // -----------------------------------------------------------------------

    function sendMoveRequest(taskId, columnId, card, sourceColumn, targetColumn) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', moveUrl, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }

            var success = false;
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    success = !!(data && data.success === true);
                } catch (ignore) {
                    success = false;
                }
            }

            if (!success) {
                // Revert card to its original column.
                sourceColumn.appendChild(card);
                updateColumnCount(targetColumn, -1);
                updateColumnCount(sourceColumn, 1);
                showError(errorLabel);
            }
        };

        var formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('column_id', columnId);
        if (csrfToken) { formData.append('csrf_token', csrfToken); }
        xhr.send(formData);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    function updateColumnCount(column, delta) {
        var countEl = column.querySelector('.portfolio-board-column-count');
        if (!countEl) { return; }
        var current = parseInt(countEl.textContent || '0', 10);
        if (isNaN(current)) { current = 0; }
        countEl.textContent = String(Math.max(0, current + delta));
    }

    function clearDropTargets() {
        var targets = board.querySelectorAll('.portfolio-board-column--drop-target');
        var i;
        for (i = 0; i < targets.length; i++) {
            targets[i].classList.remove('portfolio-board-column--drop-target');
        }
    }

    function showError(message) {
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

    init();
}());
