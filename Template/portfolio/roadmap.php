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
<?php endif ?>

<?= $this->asset->js('plugins/Portfolio/Asset/js/d3.v7.min.js') ?>
<?= $this->asset->js('plugins/Portfolio/Asset/js/portfolio-gantt.js') ?>

    </div>
</section>
