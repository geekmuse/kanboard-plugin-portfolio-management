<?php
$widgetEnabled = true;

if (is_object($this->configModel) && method_exists($this->configModel, 'get')) {
    $widgetEnabled = (int) $this->configModel->get('portfolio_dashboard_widget_enabled', 1) === 1;
}

$portfolios = $widgetEnabled ? $this->portfolioHelper->getAllPortfolios() : [];
$atRiskMilestones = $widgetEnabled ? $this->portfolioHelper->getGlobalAtRiskMilestones() : [];
?>
<?php if (! empty($portfolios)): ?>
<div class="portfolio-widget-dashboard">
    <h3 class="portfolio-widget-title"><?= $this->text->e(t('Portfolios')) ?></h3>
    <ul class="portfolio-widget-list">
        <?php foreach ($portfolios as $portfolio): ?>
        <li class="portfolio-widget-list-item">
            <a href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
                <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
            </a>
        </li>
        <?php endforeach ?>
    </ul>

    <?php if (! empty($atRiskMilestones)): ?>
    <h4 class="portfolio-widget-subtitle"><?= $this->text->e(t('At-Risk Milestones')) ?></h4>
    <ul class="portfolio-widget-list portfolio-widget-list--at-risk">
        <?php foreach ($atRiskMilestones as $ms): ?>
        <li class="portfolio-widget-list-item">
            <a href="<?= $this->url->href('MilestoneController', 'show', ['milestone_id' => (int) ($ms['id'] ?? 0)], 'Portfolio') ?>">
                <?= $this->text->e((string) ($ms['name'] ?? '')) ?>
            </a>
            <span class="portfolio-badge portfolio-badge--warning">
                <?= $this->text->e((string) ($ms['portfolio_name'] ?? '')) ?>
            </span>
            <span class="portfolio-badge">
                <?= $this->text->e((string) (int) (($ms['progress'] ?? [])['percent'] ?? 0)) ?>%
            </span>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>

    <p class="portfolio-widget-footer">
        <a href="<?= $this->url->href('PortfolioListController', 'index', [], 'Portfolio') ?>">
            <?= $this->text->e(t('View all portfolios')) ?>
        </a>
        &nbsp;·&nbsp;
        <a href="<?= $this->url->href('PortfolioModificationController', 'create', [], 'Portfolio') ?>">
            <?= $this->text->e(t('Create Portfolio')) ?>
        </a>
    </p>
</div>
<?php endif ?>
