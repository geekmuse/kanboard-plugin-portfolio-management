<section class="sidebar-container">
    <?php $sidebar_active = "tasks"; require __DIR__ . "/_sidebar.php"; ?>

    <div class="sidebar-content">
<div class="page-header">
    <h2 class="portfolio-task-list-title">
        <?= $this->text->e($title ?? t('Portfolio Tasks')) ?>:
        <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
    </h2>

    </div>

<div class="portfolio-task-list-summary">
    <span class="portfolio-badge"><?= $this->text->e(t('Total Tasks')) ?>: <?= $this->text->e((string) ((int) ($counts['total'] ?? 0))) ?></span>
    <span class="portfolio-badge"><?= $this->text->e(t('Active Tasks')) ?>: <?= $this->text->e((string) ((int) ($counts['active'] ?? 0))) ?></span>
    <span class="portfolio-badge"><?= $this->text->e(t('Closed Tasks')) ?>: <?= $this->text->e((string) ((int) ($counts['closed'] ?? 0))) ?></span>
    <span class="portfolio-badge"><?= $this->text->e(t('Blocked Tasks')) ?>: <?= $this->text->e((string) ((int) ($counts['blocked'] ?? 0))) ?></span>
</div>

<form method="get" action="<?= $this->url->href('PortfolioViewController', 'tasks', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="portfolio-task-filter-form">
    <div class="portfolio-form-row">
        <label for="form-status-id"><?= $this->text->e(t('Status')) ?></label>
        <select id="form-status-id" name="status_id">
            <option value=""<?= ($filters['status_id'] ?? '') === '' ? ' selected' : '' ?>><?= $this->text->e(t('All Statuses')) ?></option>
            <option value="1"<?= ($filters['status_id'] ?? '') === '1' ? ' selected' : '' ?>><?= $this->text->e(t('Active')) ?></option>
            <option value="0"<?= ($filters['status_id'] ?? '') === '0' ? ' selected' : '' ?>><?= $this->text->e(t('Closed')) ?></option>
        </select>
    </div>

    <div class="portfolio-form-row">
        <label for="form-assignee-id"><?= $this->text->e(t('Assignee')) ?></label>
        <select id="form-assignee-id" name="assignee_id">
            <option value=""><?= $this->text->e(t('All Users')) ?></option>
            <?php $selectedAssignee = (string) ($filters['assignee_id'] ?? ''); ?>
            <?php foreach (($users ?? []) as $userId => $userName): ?>
                <option value="<?= $this->text->e((string) $userId) ?>"<?= (string) $userId === $selectedAssignee ? ' selected' : '' ?>>
                    <?= $this->text->e($userName) ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="portfolio-form-row">
        <label for="form-project-id"><?= $this->text->e(t('Project')) ?></label>
        <select id="form-project-id" name="project_id">
            <option value=""<?= ($filters['project_id'] ?? '') === '' ? ' selected' : '' ?>><?= $this->text->e(t('All Projects')) ?></option>
            <?php foreach ($projects as $project): ?>
                <?php $projectId = (string) ((int) ($project['id'] ?? 0)); ?>
                <option value="<?= $this->text->e($projectId) ?>"<?= ($filters['project_id'] ?? '') === $projectId ? ' selected' : '' ?>>
                    <?= $this->text->e((string) ($project['name'] ?? '')) ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="portfolio-form-row">
        <label for="form-milestone-id"><?= $this->text->e(t('Milestone')) ?></label>
        <select id="form-milestone-id" name="milestone_id">
            <option value=""<?= ($filters['milestone_id'] ?? '') === '' ? ' selected' : '' ?>><?= $this->text->e(t('All Milestones')) ?></option>
            <?php foreach ($milestones as $milestone): ?>
                <?php $milestoneId = (string) ((int) ($milestone['id'] ?? 0)); ?>
                <option value="<?= $this->text->e($milestoneId) ?>"<?= ($filters['milestone_id'] ?? '') === $milestoneId ? ' selected' : '' ?>>
                    <?= $this->text->e((string) ($milestone['name'] ?? '')) ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="portfolio-form-row">
        <label for="form-has-dependencies"><?= $this->text->e(t('Dependencies')) ?></label>
        <select id="form-has-dependencies" name="has_dependencies">
            <option value=""<?= ($filters['has_dependencies'] ?? '') === '' ? ' selected' : '' ?>><?= $this->text->e(t('All Tasks')) ?></option>
            <option value="1"<?= ($filters['has_dependencies'] ?? '') === '1' ? ' selected' : '' ?>><?= $this->text->e(t('With Dependencies')) ?></option>
        </select>
    </div>

    <div class="portfolio-form-row">
        <label for="form-sort"><?= $this->text->e(t('Sort')) ?></label>
        <select id="form-sort" name="sort">
            <option value="priority"<?= ($filters['sort'] ?? 'priority') === 'priority' ? ' selected' : '' ?>><?= $this->text->e(t('Priority')) ?></option>
            <option value="date_due"<?= ($filters['sort'] ?? 'priority') === 'date_due' ? ' selected' : '' ?>><?= $this->text->e(t('Due Date')) ?></option>
            <option value="project"<?= ($filters['sort'] ?? 'priority') === 'project' ? ' selected' : '' ?>><?= $this->text->e(t('Project')) ?></option>
            <option value="date_creation"<?= ($filters['sort'] ?? 'priority') === 'date_creation' ? ' selected' : '' ?>><?= $this->text->e(t('Creation Date')) ?></option>
        </select>
    </div>

    <div class="portfolio-form-row">
        <label for="form-direction"><?= $this->text->e(t('Direction')) ?></label>
        <select id="form-direction" name="direction">
            <option value="DESC"<?= ($filters['direction'] ?? 'DESC') === 'DESC' ? ' selected' : '' ?>><?= $this->text->e(t('Descending')) ?></option>
            <option value="ASC"<?= ($filters['direction'] ?? 'DESC') === 'ASC' ? ' selected' : '' ?>><?= $this->text->e(t('Ascending')) ?></option>
        </select>
    </div>

    <div class="portfolio-form-row">
        <label for="form-limit"><?= $this->text->e(t('Results per page')) ?></label>
        <input type="number" id="form-limit" name="limit" min="1" max="500" value="<?= $this->text->e((string) ($filters['limit'] ?? '50')) ?>">
    </div>

    <input type="hidden" name="offset" value="0">

    <div class="portfolio-form-actions">
        <button type="submit" class="btn btn-blue"><?= $this->text->e(t('Apply Filters')) ?></button>
        <a href="<?= $this->url->href('PortfolioViewController', 'tasks', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="btn">
            <?= $this->text->e(t('Clear Filters')) ?>
        </a>
    </div>
</form>

<?php if (empty($tasks)): ?>
    <p class="portfolio-empty-state"><?= $this->text->e(t('No tasks found for the selected filters.')) ?></p>
<?php else: ?>
    <table class="table-striped portfolio-task-list-table">
        <thead>
        <tr>
            <th><?= $this->text->e(t('ID')) ?></th>
            <th><?= $this->text->e(t('Task')) ?></th>
            <th><?= $this->text->e(t('Project')) ?></th>
            <th><?= $this->text->e(t('Column')) ?></th>
            <th><?= $this->text->e(t('Assignee')) ?></th>
            <th><?= $this->text->e(t('Status')) ?></th>
            <th><?= $this->text->e(t('Priority')) ?></th>
            <th><?= $this->text->e(t('Due Date')) ?></th>
            <th><?= $this->text->e(t('Blocked')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tasks as $task): ?>
            <?php
            $taskStatus = ((int) ($task['is_active'] ?? 1) === 1) ? t('Active') : t('Closed');
            $taskDueDate = (int) ($task['date_due'] ?? 0);
            $assignee = (string) ($task['assignee_name'] ?? '');
            if ($assignee === '') {
                $assignee = (string) ($task['assignee_username'] ?? '');
            }
            ?>
            <tr>
                <td>#<?= $this->text->e((string) ((int) ($task['id'] ?? 0))) ?></td>
                <td><?= $this->text->e((string) ($task['title'] ?? '')) ?></td>
                <td><?= $this->text->e((string) ($task['project_name'] ?? '')) ?></td>
                <td><?= $this->text->e((string) ($task['column_title'] ?? '')) ?></td>
                <td><?= $this->text->e($assignee) ?></td>
                <td><?= $this->text->e($taskStatus) ?></td>
                <td><?= $this->text->e((string) ((int) ($task['priority'] ?? 0))) ?></td>
                <td><?= $this->text->e($taskDueDate > 0 ? date('Y-m-d', $taskDueDate) : t('No due date')) ?></td>
                <td>
                    <?php if ((bool) ($task['is_blocked'] ?? false)): ?>
                        <span class="portfolio-badge portfolio-task-blocked">
                            <?= $this->text->e(t('Blocked')) ?> (<?= $this->text->e((string) ((int) ($task['blocked_by_count'] ?? 0))) ?>)
                        </span>
                    <?php else: ?>
                        <?= $this->text->e(t('No')) ?>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>

    <div class="portfolio-task-pagination">
        <?php if ($previous_offset !== null): ?>
            <a class="btn" href="<?= $this->url->href('PortfolioViewController', 'tasks', array_merge(['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio'], $pagination_query, ['offset' => (int) $previous_offset])) ?>">
                <?= $this->text->e(t('Previous')) ?>
            </a>
        <?php endif ?>

        <?php if ($next_offset !== null): ?>
            <a class="btn" href="<?= $this->url->href('PortfolioViewController', 'tasks', array_merge(['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio'], $pagination_query, ['offset' => (int) $next_offset])) ?>">
                <?= $this->text->e(t('Next')) ?>
            </a>
        <?php endif ?>
    </div>
<?php endif ?>

    </div>
</section>
