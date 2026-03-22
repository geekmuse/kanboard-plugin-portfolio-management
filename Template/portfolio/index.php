<div class="page-header">
    <h2 class="portfolio-list-title"><?= $this->text->e($title ?? t('Portfolios')) ?></h2>
    <div class="portfolio-list-actions">
        <a href="<?= $this->url->href('PortfolioModificationController', 'create', [], 'Portfolio') ?>" class="btn btn-blue">
            <?= $this->text->e(t('Create Portfolio')) ?>
        </a>
    </div>
</div>

<?php if (empty($portfolios)): ?>
    <p class="portfolio-empty-state"><?= $this->text->e(t('No portfolios found.')) ?></p>
<?php else: ?>
    <table class="table-striped portfolio-list-table">
        <thead>
        <tr>
            <th><?= $this->text->e(t('Portfolio Name')) ?></th>
            <th><?= $this->text->e(t('Portfolio Description')) ?></th>
            <th><?= $this->text->e(t('Status')) ?></th>
            <th><?= $this->text->e(t('Actions')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($portfolios as $portfolio): ?>
            <tr>
                <td><?= $this->text->e((string) ($portfolio['name'] ?? '')) ?></td>
                <td><?= $this->text->e((string) ($portfolio['description'] ?? '')) ?></td>
                <td>
                    <?= $this->text->e(((int) ($portfolio['is_active'] ?? 1) === 1) ? t('Active') : t('Inactive')) ?>
                </td>
                <td>
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
                    ·
                    <a href="<?= $this->url->href('PortfolioModificationController', 'edit', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
                        <?= $this->text->e(t('Edit')) ?>
                    </a>
                    ·
                    <a href="<?= $this->url->href('PortfolioModificationController', 'remove', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
                        <?= $this->text->e(t('Remove')) ?>
                    </a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
