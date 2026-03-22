<div class="page-header">
    <h2 class="portfolio-dashboard-title">
        <?= $this->text->e($title ?? t('Portfolio Dashboard')) ?>:
        <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
    </h2>

    <div class="portfolio-view-switcher">
        <a href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
            <?= $this->text->e(t('Dashboard')) ?>
        </a>
        ·
        <a href="<?= $this->url->href('PortfolioViewController', 'tasks', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
            <?= $this->text->e(t('Tasks')) ?>
        </a>
        ·
        <a href="<?= $this->url->href('MilestoneController', 'index', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
            <?= $this->text->e(t('Milestones')) ?>
        </a>
        ·
        <a href="<?= $this->url->href('PortfolioViewController', 'board', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
            <?= $this->text->e(t('Board')) ?>
        </a>
        ·
        <a href="<?= $this->url->href('PortfolioViewController', 'timeline', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
            <?= $this->text->e(t('Timeline')) ?>
        </a>
        ·
        <a href="<?= $this->url->href('PortfolioModificationController', 'settings', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
            <?= $this->text->e(t('Portfolio Settings')) ?>
        </a>
    </div>
</div>

<div class="portfolio-dashboard-metrics">
    <h3><?= $this->text->e(t('Overview Metrics')) ?></h3>
    <ul class="portfolio-dashboard-metric-list">
        <li class="portfolio-dashboard-metric-item"><?= $this->text->e(t('Projects')) ?>: <?= $this->text->e((string) ((int) ($overview['project_count'] ?? 0))) ?></li>
        <li class="portfolio-dashboard-metric-item"><?= $this->text->e(t('Total Tasks')) ?>: <?= $this->text->e((string) ((int) ($overview['task_counts']['total'] ?? 0))) ?></li>
        <li class="portfolio-dashboard-metric-item"><?= $this->text->e(t('Active Tasks')) ?>: <?= $this->text->e((string) ((int) ($overview['task_counts']['active'] ?? 0))) ?></li>
        <li class="portfolio-dashboard-metric-item"><?= $this->text->e(t('Closed Tasks')) ?>: <?= $this->text->e((string) ((int) ($overview['task_counts']['closed'] ?? 0))) ?></li>
        <li class="portfolio-dashboard-metric-item"><?= $this->text->e(t('Blocked Tasks')) ?>: <?= $this->text->e((string) ((int) ($overview['task_counts']['blocked'] ?? 0))) ?></li>
        <li class="portfolio-dashboard-metric-item"><?= $this->text->e(t('At-Risk Milestones')) ?>: <?= $this->text->e((string) ((int) ($overview['at_risk_milestones'] ?? 0))) ?></li>
        <li class="portfolio-dashboard-metric-item"><?= $this->text->e(t('Overdue Milestones')) ?>: <?= $this->text->e((string) ((int) ($overview['overdue_milestones'] ?? 0))) ?></li>
        <li class="portfolio-dashboard-metric-item"><?= $this->text->e(t('Critical Path Length')) ?>: <?= $this->text->e((string) ((int) ($overview['critical_path_length'] ?? 0))) ?></li>
    </ul>
</div>

<div class="portfolio-dashboard-projects">
    <h3><?= $this->text->e(t('Project Summary')) ?></h3>

    <?php if (empty($overview['projects'])): ?>
        <p class="portfolio-empty-state"><?= $this->text->e(t('No projects assigned to this portfolio.')) ?></p>
    <?php else: ?>
        <table class="table-striped portfolio-dashboard-project-table">
            <thead>
            <tr>
                <th><?= $this->text->e(t('Project')) ?></th>
                <th><?= $this->text->e(t('Status')) ?></th>
                <th><?= $this->text->e(t('Position')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($overview['projects'] as $project): ?>
                <tr>
                    <td><?= $this->text->e((string) ($project['name'] ?? '')) ?></td>
                    <td><?= $this->text->e(((int) ($project['is_active'] ?? 1) === 1) ? t('Active') : t('Inactive')) ?></td>
                    <td><?= $this->text->e((string) ((int) ($project['position'] ?? 0))) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<div class="portfolio-dashboard-milestones">
    <h3><?= $this->text->e(t('Milestone Health')) ?></h3>

    <?php if (empty($overview['milestones'])): ?>
        <p class="portfolio-empty-state"><?= $this->text->e(t('No milestones found.')) ?></p>
    <?php else: ?>
        <table class="table-striped portfolio-dashboard-milestone-table">
            <thead>
            <tr>
                <th><?= $this->text->e(t('Milestone')) ?></th>
                <th><?= $this->text->e(t('Target Date')) ?></th>
                <th><?= $this->text->e(t('Progress')) ?></th>
                <th><?= $this->text->e(t('Health')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($overview['milestones'] as $milestone): ?>
                <?php
                $targetDate = (int) ($milestone['target_date'] ?? 0);
                $healthLabel = t('On Track');

                if ((bool) ($milestone['is_overdue'] ?? false)) {
                    $healthLabel = t('Overdue');
                } elseif ((bool) ($milestone['is_at_risk'] ?? false)) {
                    $healthLabel = t('At Risk');
                }
                ?>
                <tr>
                    <td><?= $this->text->e((string) ($milestone['name'] ?? '')) ?></td>
                    <td><?= $this->text->e($targetDate > 0 ? date('Y-m-d', $targetDate) : t('No target date')) ?></td>
                    <td><?= $this->text->e((string) ((float) ($milestone['percent'] ?? 0))) ?>%</td>
                    <td><?= $this->text->e($healthLabel) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
