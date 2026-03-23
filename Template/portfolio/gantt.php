<section class="sidebar-container">
    <?php $sidebar_active = "gantt"; require __DIR__ . "/_sidebar.php"; ?>

    <div class="sidebar-content">
<div class="page-header">
    <h2 class="portfolio-gantt-title">
        <?= $this->text->e($title ?? t('Portfolio Gantt')) ?>:
        <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
    </h2>
</div>

<?php if (empty($has_items)): ?>
    <p class="portfolio-empty-state"><?= $this->text->e(t('No items found. Add due dates or milestone target dates to populate the Gantt chart.')) ?></p>
<?php else: ?>
    <div
        id="portfolio-gantt-chart"
        class="portfolio-gantt-chart"
        data-gantt="<?= $this->text->e((string) ($gantt_json ?? '{}')) ?>"
    ></div>
<?php endif ?>

<?= $this->asset->js('plugins/Portfolio/Asset/js/d3.v7.min.js') ?>
<?= $this->asset->js('plugins/Portfolio/Asset/js/portfolio-gantt.js') ?>
<script>
(function () {
    var chartElement = document.getElementById('portfolio-gantt-chart');
    if (!chartElement || !window.PortfolioGantt || typeof window.PortfolioGantt.renderGantt !== 'function') {
        return;
    }

    var raw = chartElement.getAttribute('data-gantt') || '{}';
    var ganttData;
    try {
        ganttData = JSON.parse(raw);
    } catch (e) {
        return;
    }

    window.PortfolioGantt.renderGantt(chartElement, ganttData);
})();
</script>

    </div>
</section>
