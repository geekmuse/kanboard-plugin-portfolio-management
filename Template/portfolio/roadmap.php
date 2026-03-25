<section class="sidebar-container">
    <?php $sidebar_active = "roadmap"; require __DIR__ . "/_sidebar.php"; ?>

    <div class="sidebar-content">
<div class="page-header">
    <h2 class="portfolio-roadmap-title">
        <?= $this->text->e($title ?? t('Portfolio Roadmap')) ?>:
        <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
    </h2>
</div>

<?php if (empty($has_items)): ?>
    <p class="portfolio-empty-state"><?= $this->text->e(t('No milestones with target dates found. Add a target date to a milestone to populate the roadmap.')) ?></p>
<?php else: ?>
    <div
        id="portfolio-roadmap-chart"
        class="portfolio-roadmap-chart"
        data-roadmap="<?= $this->text->e((string) ($roadmap_json ?? '[]')) ?>"
    ></div>
    <div class="portfolio-roadmap-legend">
        <span class="portfolio-roadmap-legend-item">
            <span class="portfolio-roadmap-legend-dot portfolio-roadmap-legend-dot--on-track"></span>
            <?= $this->text->e(t('On Track')) ?>
        </span>
        <span class="portfolio-roadmap-legend-item">
            <span class="portfolio-roadmap-legend-dot portfolio-roadmap-legend-dot--at-risk"></span>
            <?= $this->text->e(t('At Risk')) ?>
        </span>
        <span class="portfolio-roadmap-legend-item">
            <span class="portfolio-roadmap-legend-dot portfolio-roadmap-legend-dot--overdue"></span>
            <?= $this->text->e(t('Overdue')) ?>
        </span>
        <span class="portfolio-roadmap-legend-today">
            <span class="portfolio-roadmap-legend-today-line"></span>
            <?= $this->text->e(t('Today')) ?>
        </span>
    </div>
<?php endif ?>

<?= $this->asset->js('plugins/Portfolio/Asset/js/d3.v7.min.js') ?>
<?= $this->asset->js('plugins/Portfolio/Asset/js/portfolio-gantt.js') ?>

    </div>
</section>
