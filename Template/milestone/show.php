<?php
$portfolioId = (int) ($milestone['portfolio_id'] ?? ($portfolio['id'] ?? 0));
$milestoneId = (int) ($milestone['id'] ?? 0);
$targetDate = (int) ($milestone['target_date'] ?? 0);
$statusId = (int) ($milestone['status'] ?? 1);
$statusLabel = t('Active');
if ($statusId === 0) {
    $statusLabel = t('Completed');
} elseif ($statusId === 2) {
    $statusLabel = t('Archived');
}
?>

<div class="page-header">
    <h2 class="portfolio-milestone-show-title">
        <?= $this->text->e($title ?? t('Milestone Details')) ?>:
        <?= $this->text->e((string) ($milestone['name'] ?? '')) ?>
    </h2>

    <div class="portfolio-milestone-show-actions">
        <a href="<?= $this->url->href('MilestoneController', 'index', ['portfolio_id' => $portfolioId], 'Portfolio') ?>">
            <?= $this->text->e(t('Milestones')) ?>
        </a>
        ·
        <a href="<?= $this->url->href('MilestoneController', 'edit', ['milestone_id' => $milestoneId], 'Portfolio') ?>">
            <?= $this->text->e(t('Edit')) ?>
        </a>
        ·
        <a href="<?= $this->url->href('MilestoneController', 'remove', ['milestone_id' => $milestoneId], 'Portfolio') ?>">
            <?= $this->text->e(t('Remove')) ?>
        </a>
        ·
        <a href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => $portfolioId], 'Portfolio') ?>">
            <?= $this->text->e(t('Dashboard')) ?>
        </a>
    </div>
</div>

<div class="portfolio-milestone-summary">
    <p>
        <strong><?= $this->text->e(t('Portfolio Name')) ?>:</strong>
        <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
    </p>
    <p>
        <strong><?= $this->text->e(t('Milestone Description')) ?>:</strong>
        <?= $this->text->e((string) ($milestone['description'] ?? '')) ?>
    </p>
    <p>
        <strong><?= $this->text->e(t('Target Date')) ?>:</strong>
        <?= $this->text->e($targetDate > 0 ? date('Y-m-d', $targetDate) : t('No target date')) ?>
    </p>
    <p>
        <strong><?= $this->text->e(t('Status')) ?>:</strong>
        <?= $this->text->e($statusLabel) ?>
    </p>
</div>

<div class="portfolio-milestone-progress">
    <h3><?= $this->text->e(t('Progress')) ?></h3>
    <ul class="portfolio-milestone-progress-list">
        <li><?= $this->text->e(t('Total Tasks')) ?>: <?= $this->text->e((string) ((int) ($progress['total'] ?? 0))) ?></li>
        <li><?= $this->text->e(t('Closed Tasks')) ?>: <?= $this->text->e((string) ((int) ($progress['completed'] ?? 0))) ?></li>
        <li><?= $this->text->e(t('Progress')) ?>: <?= $this->text->e((string) ((float) ($progress['percent'] ?? 0))) ?>%</li>
        <li><?= $this->text->e(t('Blocked Tasks')) ?>: <?= $this->text->e((string) ((int) ($progress['blocked_count'] ?? 0))) ?></li>
        <li><?= $this->text->e(t('At Risk')) ?>: <?= $this->text->e(((bool) ($progress['is_at_risk'] ?? false)) ? t('Yes') : t('No')) ?></li>
        <li><?= $this->text->e(t('Overdue')) ?>: <?= $this->text->e(((bool) ($progress['is_overdue'] ?? false)) ? t('Yes') : t('No')) ?></li>
    </ul>
</div>

<div class="portfolio-milestone-task-actions">
    <h3><?= $this->text->e(t('Add Task')) ?></h3>

    <form method="post" action="<?= $this->url->href('MilestoneController', 'addTask', ['milestone_id' => $milestoneId], 'Portfolio') ?>" class="portfolio-milestone-add-task-form">
        <?= $this->form->csrf() ?>

        <div class="portfolio-form-row">
            <label for="form-task-id"><?= $this->text->e(t('Task ID')) ?></label>
            <input type="number" id="form-task-id" name="task_id" min="1" required>
        </div>

        <div class="portfolio-form-row">
            <label for="form-task-position"><?= $this->text->e(t('Position')) ?></label>
            <input type="number" id="form-task-position" name="position" min="0" value="0">
        </div>

        <div class="portfolio-form-row">
            <label>
                <input type="checkbox" name="is_critical" value="1">
                <?= $this->text->e(t('Mark as Critical')) ?>
            </label>
        </div>

        <div class="portfolio-form-actions">
            <button type="submit" class="btn btn-blue"><?= $this->text->e(t('Add Task')) ?></button>
        </div>
    </form>
</div>

<div class="portfolio-milestone-task-list">
    <h3><?= $this->text->e(t('Tasks')) ?></h3>

    <?php if (empty($tasks)): ?>
        <p class="portfolio-empty-state"><?= $this->text->e(t('No tasks assigned to this milestone.')) ?></p>
    <?php else: ?>
        <table class="table-striped portfolio-milestone-task-table">
            <thead>
            <tr>
                <th><?= $this->text->e(t('ID')) ?></th>
                <th><?= $this->text->e(t('Task')) ?></th>
                <th><?= $this->text->e(t('Project')) ?></th>
                <th><?= $this->text->e(t('Assignee')) ?></th>
                <th><?= $this->text->e(t('Status')) ?></th>
                <th><?= $this->text->e(t('Critical')) ?></th>
                <th><?= $this->text->e(t('Position')) ?></th>
                <th><?= $this->text->e(t('Actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $task): ?>
                <?php
                $taskStatus = ((int) ($task['is_active'] ?? 1) === 1) ? t('Active') : t('Closed');
                $assignee = (string) ($task['assignee_name'] ?? '');
                if ($assignee === '') {
                    $assignee = (string) ($task['assignee_username'] ?? '');
                }
                if ($assignee === '') {
                    $assignee = t('Unassigned');
                }
                ?>
                <tr>
                    <td>#<?= $this->text->e((string) ((int) ($task['id'] ?? 0))) ?></td>
                    <td><?= $this->text->e((string) ($task['title'] ?? '')) ?></td>
                    <td><?= $this->text->e((string) ($task['project_name'] ?? '')) ?></td>
                    <td><?= $this->text->e($assignee) ?></td>
                    <td><?= $this->text->e($taskStatus) ?></td>
                    <td><?= $this->text->e(((int) ($task['is_critical'] ?? 0) === 1) ? t('Yes') : t('No')) ?></td>
                    <td><?= $this->text->e((string) ((int) ($task['position'] ?? 0))) ?></td>
                    <td>
                        <form method="post" action="<?= $this->url->href('MilestoneController', 'removeTask', ['milestone_id' => $milestoneId], 'Portfolio') ?>" class="portfolio-milestone-remove-task-form">
                            <?= $this->form->csrf() ?>
                            <input type="hidden" name="task_id" value="<?= $this->text->e((string) ((int) ($task['id'] ?? 0))) ?>">
                            <button type="submit" class="btn btn-red btn-sm"><?= $this->text->e(t('Remove Task')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
