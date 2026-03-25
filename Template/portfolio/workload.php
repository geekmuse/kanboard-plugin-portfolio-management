<?php
/**
 * Portfolio Team Workload View
 *
 * @var array<string, mixed>       $portfolio
 * @var array<string, mixed>       $workload   { users: array, unassigned: array }
 * @var int                        $threshold  Overload threshold (active tasks)
 */

$users      = is_array($workload['users'] ?? null)      ? ($workload['users'] ?? [])      : [];
$unassigned = is_array($workload['unassigned'] ?? null)  ? ($workload['unassigned'] ?? [])  : [];

$portfolioId   = (int) ($portfolio['id'] ?? 0);
$portfolioName = (string) ($portfolio['name'] ?? '');

$hasData = $users !== [] || (int) ($unassigned['task_count'] ?? 0) > 0;
?>
<section class="sidebar-container">
    <?php $sidebar_active = 'workload'; require __DIR__ . '/_sidebar.php'; ?>

    <div class="sidebar-content">
        <div class="page-header">
            <h2><?= $this->text->e($title ?? t('Portfolio Team Workload')) ?>:
                <?= $this->text->e($portfolioName) ?>
            </h2>
        </div>

        <?php if (! $hasData): ?>
            <p class="alert portfolio-workload-empty">
                <?= $this->text->e(t('No tasks found for this portfolio.')) ?>
            </p>
        <?php else: ?>

        <table class="portfolio-workload-table">
            <thead>
                <tr class="portfolio-workload-header">
                    <th><?= $this->text->e(t('User')) ?></th>
                    <th><?= $this->text->e(t('Active Tasks')) ?></th>
                    <th><?= $this->text->e(t('Overdue')) ?></th>
                    <th><?= $this->text->e(t('Blocked')) ?></th>
                    <th><?= $this->text->e(t('Score')) ?></th>
                    <th><?= $this->text->e(t('Est. Hours')) ?></th>
                    <th><?= $this->text->e(t('Spent Hours')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $userId       = (int) ($user['user_id'] ?? 0);
                    $userName     = (string) ($user['name'] ?? '');
                    $username     = (string) ($user['username'] ?? '');
                    $displayName  = $userName !== '' ? $userName : $username;
                    $activeCount  = (int) ($user['active_task_count'] ?? 0);
                    $isOverloaded = $activeCount > $threshold;
                    ?>
                    <tr class="portfolio-workload-row<?= $isOverloaded ? ' portfolio-workload-row--overloaded' : '' ?>">
                        <td class="portfolio-workload-user">
                            <a href="<?= $this->url->href(
                                'PortfolioViewController',
                                'tasks',
                                ['portfolio_id' => $portfolioId, 'assignee_id' => $userId, 'plugin' => 'Portfolio']
                            ) ?>">
                                <?= $this->text->e($displayName) ?>
                            </a>
                            <?php if ($isOverloaded): ?>
                                <span class="portfolio-workload-overload-indicator" title="<?= $this->text->e(t('Overloaded')) ?>">&#9888;</span>
                            <?php endif ?>
                        </td>
                        <td class="portfolio-workload-active"><?= $this->text->e((string) $activeCount) ?></td>
                        <td class="portfolio-workload-overdue"><?= $this->text->e((string) ((int) ($user['overdue_task_count'] ?? 0))) ?></td>
                        <td class="portfolio-workload-blocked"><?= $this->text->e((string) ((int) ($user['blocked_task_count'] ?? 0))) ?></td>
                        <td class="portfolio-workload-score"><?= $this->text->e((string) ((int) ($user['total_score'] ?? 0))) ?></td>
                        <td class="portfolio-workload-estimated"><?= $this->text->e((string) ((int) ($user['total_estimated_hours'] ?? 0))) ?></td>
                        <td class="portfolio-workload-spent"><?= $this->text->e((string) ((int) ($user['total_spent_hours'] ?? 0))) ?></td>
                    </tr>
                <?php endforeach ?>

                <?php if ((int) ($unassigned['task_count'] ?? 0) > 0): ?>
                    <tr class="portfolio-workload-row portfolio-workload-row--unassigned">
                        <td class="portfolio-workload-user portfolio-workload-unassigned-label">
                            <?= $this->text->e(t('Unassigned')) ?>
                        </td>
                        <td class="portfolio-workload-active"><?= $this->text->e((string) ((int) ($unassigned['active_task_count'] ?? 0))) ?></td>
                        <td class="portfolio-workload-overdue"><?= $this->text->e((string) ((int) ($unassigned['overdue_task_count'] ?? 0))) ?></td>
                        <td class="portfolio-workload-blocked"><?= $this->text->e((string) ((int) ($unassigned['blocked_task_count'] ?? 0))) ?></td>
                        <td class="portfolio-workload-score"><?= $this->text->e((string) ((int) ($unassigned['total_score'] ?? 0))) ?></td>
                        <td class="portfolio-workload-estimated"><?= $this->text->e((string) ((int) ($unassigned['total_estimated_hours'] ?? 0))) ?></td>
                        <td class="portfolio-workload-spent"><?= $this->text->e((string) ((int) ($unassigned['total_spent_hours'] ?? 0))) ?></td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>

        <?php if ($threshold > 0): ?>
        <p class="portfolio-workload-legend">
            <span class="portfolio-workload-overload-indicator">&#9888;</span>
            <?= $this->text->e(t('Overloaded: active tasks exceed threshold of %d', $threshold)) ?>
        </p>
        <?php endif ?>

        <?php endif ?>
    </div>
</section>
