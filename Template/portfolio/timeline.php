<section class="sidebar-container">
    <?php $sidebar_active = "timeline"; require __DIR__ . "/_sidebar.php"; ?>

    <div class="sidebar-content">
<div class="page-header">
    <h2 class="portfolio-timeline-title">
        <?= $this->text->e($title ?? t('Portfolio Timeline')) ?>:
        <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
    </h2>

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

<?= $this->asset->js('plugins/Portfolio/Asset/js/portfolio-gantt.js') ?>

    </div>
</section>
