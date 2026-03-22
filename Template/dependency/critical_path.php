<div class="page-header">
    <h2 class="portfolio-critical-path-title">
        <?= $this->text->e($title ?? t('Critical Path')) ?>:
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
        <a href="<?= $this->url->href('DependencyController', 'graph', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
            <?= $this->text->e(t('Dependency Graph')) ?>
        </a>
        ·
        <a href="<?= $this->url->href('DependencyController', 'blocked', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
            <?= $this->text->e(t('Blocked Tasks')) ?>
        </a>
        ·
        <a href="<?= $this->url->href('DependencyController', 'criticalPath', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
            <?= $this->text->e(t('Critical Path')) ?>
        </a>
        ·
        <a href="<?= $this->url->href('PortfolioModificationController', 'settings', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
            <?= $this->text->e(t('Portfolio Settings')) ?>
        </a>
    </div>
</div>

<?php $criticalPath = $critical_path ?? [] ?>

<?php if (empty($criticalPath)): ?>
    <p class="portfolio-empty-state"><?= $this->text->e(t('No critical path found. Add cross-project task dependencies to compute the critical path.')) ?></p>
<?php else: ?>
    <div class="portfolio-critical-path-summary">
        <span class="portfolio-badge">
            <?= $this->text->e(t('Critical Path Length')) ?>: <?= $this->text->e((string) count($criticalPath)) ?>
        </span>
    </div>

    <ol class="portfolio-critical-path-list">
        <?php foreach ($criticalPath as $task): ?>
            <?php
            $chainPosition = (int) ($task['chain_position'] ?? 0);
            $downstreamCount = (int) ($task['downstream_count'] ?? 0);
            $isActive = (int) ($task['is_active'] ?? 1);
            ?>
            <li class="portfolio-critical-path-item<?= $isActive === 0 ? ' portfolio-critical-path-item--closed' : '' ?>">
                <div class="portfolio-critical-path-item-header">
                    <span class="portfolio-critical-path-position"><?= $this->text->e((string) $chainPosition) ?>.</span>
                    <strong class="portfolio-critical-path-task-title">
                        #<?= $this->text->e((string) ((int) ($task['id'] ?? 0))) ?>
                        <?= $this->text->e((string) ($task['title'] ?? '')) ?>
                    </strong>
                    <?php if ($isActive === 0): ?>
                        <span class="portfolio-badge portfolio-task-closed"><?= $this->text->e(t('Closed')) ?></span>
                    <?php endif ?>
                </div>

                <div class="portfolio-critical-path-item-meta">
                    <span class="portfolio-critical-path-project">
                        <?= $this->text->e(t('Project')) ?>: <?= $this->text->e((string) ($task['project_name'] ?? '')) ?>
                    </span>

                    <?php $assignee = (string) ($task['assignee'] ?? '') ?>
                    <?php if ($assignee !== ''): ?>
                        ·
                        <span class="portfolio-critical-path-assignee">
                            <?= $this->text->e(t('Assignee')) ?>: <?= $this->text->e($assignee) ?>
                        </span>
                    <?php endif ?>

                    <?php if ($downstreamCount > 0): ?>
                        ·
                        <span class="portfolio-critical-path-downstream">
                            <?= $this->text->e(t('Downstream Tasks')) ?>: <?= $this->text->e((string) $downstreamCount) ?>
                        </span>
                    <?php endif ?>
                </div>

                <?php if ($chainPosition < count($criticalPath)): ?>
                    <div class="portfolio-critical-path-connector" aria-hidden="true">↓</div>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ol>
<?php endif ?>
