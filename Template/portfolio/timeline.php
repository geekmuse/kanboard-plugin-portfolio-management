<div class="page-header">
    <h2 class="portfolio-timeline-title">
        <?= $this->text->e($title ?? t('Portfolio Timeline')) ?>:
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

<?php if (! empty($timeline_data['has_items'])): ?>
    <p class="portfolio-timeline-range">
        <?= $this->text->e(t('Timeline Range')) ?>:
        <?= $this->text->e((string) ($timeline_data['min_date_label'] ?? '')) ?>
        →
        <?= $this->text->e((string) ($timeline_data['max_date_label'] ?? '')) ?>
    </p>
<?php endif ?>

<div
    id="portfolio-timeline-chart"
    class="portfolio-timeline-chart"
    data-items="<?= $this->text->e((string) ($timeline_json ?? '[]')) ?>"
></div>

<?php if (empty($timeline_data['items'])): ?>
    <p class="portfolio-empty-state"><?= $this->text->e(t('No timeline items found. Add due dates or milestone target dates to populate the timeline.')) ?></p>
<?php else: ?>
    <table class="table-striped portfolio-timeline-table">
        <thead>
        <tr>
            <th><?= $this->text->e(t('Date')) ?></th>
            <th><?= $this->text->e(t('Type')) ?></th>
            <th><?= $this->text->e(t('Item')) ?></th>
            <th><?= $this->text->e(t('Project')) ?></th>
            <th><?= $this->text->e(t('Status')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($timeline_data['items'] as $item): ?>
            <tr>
                <td><?= $this->text->e((string) ($item['date_label'] ?? '')) ?></td>
                <td><?= $this->text->e((string) ($item['type_label'] ?? '')) ?></td>
                <td>#<?= $this->text->e((string) ((int) ($item['id'] ?? 0))) ?> — <?= $this->text->e((string) ($item['name'] ?? '')) ?></td>
                <td><?= $this->text->e((string) ($item['project_name'] ?? '')) ?></td>
                <td><?= $this->text->e((string) ($item['status'] ?? '')) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<script src="plugins/Portfolio/Asset/js/portfolio-gantt.js"></script>
<script>
(function () {
    var chartElement = document.getElementById('portfolio-timeline-chart');
    if (!chartElement || !window.PortfolioGantt || typeof window.PortfolioGantt.render !== 'function') {
        return;
    }

    window.PortfolioGantt.render(chartElement);
})();
</script>
