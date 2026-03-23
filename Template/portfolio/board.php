<section class="sidebar-container">
    <?php $sidebar_active = "board"; require __DIR__ . "/_sidebar.php"; ?>

    <div class="sidebar-content">
<div class="page-header">
    <h2 class="portfolio-board-title">
        <?= $this->text->e($title ?? t('Portfolio Board')) ?>:
        <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
    </h2>

    </div>

<div class="portfolio-task-list-summary">
    <span class="portfolio-badge">
        <?= $this->text->e(t('Active Tasks')) ?>: <?= $this->text->e((string) ((int) ($counts['active'] ?? 0))) ?>
    </span>
    <span class="portfolio-badge">
        <?= $this->text->e(t('Blocked Tasks')) ?>: <?= $this->text->e((string) ((int) ($counts['blocked'] ?? 0))) ?>
    </span>
</div>

<?php if (empty($board_columns)): ?>
    <p class="portfolio-empty-state"><?= $this->text->e(t('No active tasks found for this portfolio.')) ?></p>
<?php else: ?>
    <div class="portfolio-board-csrf" style="display:none">
        <?= $this->form->csrf() ?>
    </div>
    <div class="portfolio-board"
         data-move-task-url="<?= $this->url->href('PortfolioViewController', 'moveTask', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>"
         data-move-error-label="<?= $this->text->e(t('Failed to move task.')) ?>">
        <?php foreach ($board_columns as $column): ?>
            <div class="portfolio-board-column" data-column-id="<?= (int) ($column['id'] ?? 0) ?>">
                <h3 class="portfolio-board-column-title">
                    <?= $this->text->e((string) ($column['title'] ?? t('Unassigned'))) ?>
                </h3>
                <p class="portfolio-board-column-meta">
                    <?= $this->text->e(t('Tasks')) ?>:
                    <span class="portfolio-board-column-count"><?= (int) ($column['total'] ?? 0) ?></span>
                    ·
                    <?= $this->text->e(t('Blocked')) ?>: <?= $this->text->e((string) ((int) ($column['blocked'] ?? 0))) ?>
                </p>

                <?php if (empty($column['tasks'])): ?>
                    <p class="portfolio-empty-state"><?= $this->text->e(t('No tasks in this column.')) ?></p>
                <?php else: ?>
                    <?php foreach ($column['tasks'] as $task): ?>
                        <?php
                        $taskDueDate = (int) ($task['date_due'] ?? 0);
                        $assignee = (string) ($task['assignee_name'] ?? '');
                        if ($assignee === '') {
                            $assignee = (string) ($task['assignee_username'] ?? '');
                        }
                        if ($assignee === '') {
                            $assignee = t('Unassigned');
                        }
                        ?>
                        <article class="portfolio-board-card"
                                 draggable="true"
                                 data-task-id="<?= (int) ($task['id'] ?? 0) ?>">
                            <header class="portfolio-board-card-header">
                                <span class="portfolio-board-card-id">#<?= $this->text->e((string) ((int) ($task['id'] ?? 0))) ?></span>
                                <?php if ((bool) ($task['is_blocked'] ?? false)): ?>
                                    <span class="portfolio-badge portfolio-task-blocked">
                                        <?= $this->text->e(t('Blocked')) ?> (<?= $this->text->e((string) ((int) ($task['blocked_by_count'] ?? 0))) ?>)
                                    </span>
                                <?php endif ?>
                            </header>
                            <h4 class="portfolio-board-card-title"><?= $this->text->e((string) ($task['title'] ?? '')) ?></h4>
                            <p class="portfolio-board-card-meta">
                                <?= $this->text->e(t('Project')) ?>: <?= $this->text->e((string) ($task['project_name'] ?? '')) ?>
                            </p>
                            <p class="portfolio-board-card-meta">
                                <?= $this->text->e(t('Assignee')) ?>: <?= $this->text->e($assignee) ?>
                            </p>
                            <p class="portfolio-board-card-meta">
                                <?= $this->text->e(t('Due Date')) ?>:
                                <?= $this->text->e($taskDueDate > 0 ? date('Y-m-d', $taskDueDate) : t('No due date')) ?>
                            </p>
                        </article>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
    <?= $this->asset->js('plugins/Portfolio/Asset/js/portfolio-board.js') ?>
<?php endif ?>

    </div>
</section>
