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

    <?= $this->asset->js('plugins/Portfolio/Asset/js/d3.v7.min.js') ?>
    <?= $this->asset->js('plugins/Portfolio/Asset/js/portfolio-gantt.js') ?>
    <script>
    // D3 and portfolio-gantt.js are loaded with "defer", so they execute
    // after parsing but before DOMContentLoaded. Use DOMContentLoaded to
    // ensure both scripts have executed before we call renderGantt().
    document.addEventListener('DOMContentLoaded', function () {
        var chartElement = document.getElementById('portfolio-gantt-chart');
        if (!chartElement || !window.PortfolioGantt || typeof window.PortfolioGantt.renderGantt !== 'function') {
            return;
        }

        var raw = chartElement.getAttribute('data-gantt') || '{}';
        try {
            var ganttData = JSON.parse(raw);
            window.PortfolioGantt.renderGantt(chartElement, ganttData);
        } catch (e) {
            chartElement.innerHTML = '<p class="portfolio-empty-state">Failed to parse Gantt data.</p>';
        }
    });
    </script>
<?php endif ?>

    </div>
</section>
