<section class="sidebar-container">
    <?php $sidebar_active = "milestones"; require __DIR__ . "/../portfolio/_sidebar.php"; ?>

    <div class="sidebar-content">
<div class="page-header">
    <h2 class="portfolio-milestone-list-title">
        <?= $this->text->e($title ?? t('Portfolio Milestones')) ?>:
        <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
    </h2>

    <div class="portfolio-milestone-list-actions">
        <a href="<?= $this->url->href('MilestoneController', 'create', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="btn btn-blue">
            <?= $this->text->e(t('Create Milestone')) ?>
        </a>
        <a href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="btn">
            <?= $this->text->e(t('Dashboard')) ?>
        </a>
    </div>
</div>

<?php if (empty($milestones)): ?>
    <p class="portfolio-empty-state"><?= $this->text->e(t('No milestones defined.')) ?></p>
<?php else: ?>
    <table class="table-striped portfolio-milestone-list-table">
        <thead>
        <tr>
            <th><?= $this->text->e(t('Milestone Name')) ?></th>
            <th><?= $this->text->e(t('Target Date')) ?></th>
            <th><?= $this->text->e(t('Status')) ?></th>
            <th><?= $this->text->e(t('Progress')) ?></th>
            <th><?= $this->text->e(t('Actions')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($milestones as $milestone): ?>
            <?php
            $milestoneId = (int) ($milestone['id'] ?? 0);
            $targetDate = (int) ($milestone['target_date'] ?? 0);
            $progress = $progress_map[$milestoneId] ?? [];
            $statusId = (int) ($milestone['status'] ?? 1);
            $statusLabel = t('Active');
            if ($statusId === 0) {
                $statusLabel = t('Completed');
            } elseif ($statusId === 2) {
                $statusLabel = t('Archived');
            }
            ?>
            <tr>
                <td><?= $this->text->e((string) ($milestone['name'] ?? '')) ?></td>
                <td><?= $this->text->e($targetDate > 0 ? date('Y-m-d', $targetDate) : t('No target date')) ?></td>
                <td><?= $this->text->e($statusLabel) ?></td>
                <td>
                    <?= $this->text->e((string) ((float) ($progress['percent'] ?? 0))) ?>%
                    (<?= $this->text->e((string) ((int) ($progress['completed'] ?? 0))) ?>/<?= $this->text->e((string) ((int) ($progress['total'] ?? 0))) ?>)
                </td>
                <td>
                    <a href="<?= $this->url->href('MilestoneController', 'show', ['milestone_id' => $milestoneId, 'plugin' => 'Portfolio']) ?>">
                        <?= $this->text->e(t('View')) ?>
                    </a>
                    ·
                    <a href="<?= $this->url->href('MilestoneController', 'edit', ['milestone_id' => $milestoneId, 'plugin' => 'Portfolio']) ?>">
                        <?= $this->text->e(t('Edit')) ?>
                    </a>
                    ·
                    <a href="<?= $this->url->href('MilestoneController', 'remove', ['milestone_id' => $milestoneId, 'plugin' => 'Portfolio']) ?>">
                        <?= $this->text->e(t('Remove')) ?>
                    </a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

    </div>
</section>
