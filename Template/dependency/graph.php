<section class="sidebar-container">
    <?php $sidebar_active = "dependencies"; require __DIR__ . "/../portfolio/_sidebar.php"; ?>

    <div class="sidebar-content">
<div class="page-header">
    <h2 class="portfolio-dependency-graph-title">
        <?= $this->text->e($title ?? t('Dependency Graph')) ?>:
        <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
    </h2>

    </div>

<div class="portfolio-dependency-filter-bar">
    <span class="portfolio-dependency-filter-label"><?= $this->text->e(t('Filter')) ?>:</span>
    <?php $crossProjectOnly = (bool) ($cross_project_only ?? true) ?>
    <?php if ($crossProjectOnly): ?>
        <strong><?= $this->text->e(t('Cross-project only')) ?></strong>
        ·
        <a href="<?= $this->url->href('DependencyController', 'graph', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'cross_project_only' => 0, 'plugin' => 'Portfolio']) ?>">
            <?= $this->text->e(t('Show all')) ?>
        </a>
    <?php else: ?>
        <a href="<?= $this->url->href('DependencyController', 'graph', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'cross_project_only' => 1, 'plugin' => 'Portfolio']) ?>">
            <?= $this->text->e(t('Cross-project only')) ?>
        </a>
        ·
        <strong><?= $this->text->e(t('Show all')) ?></strong>
    <?php endif ?>
</div>

<?php
$graphData = $graph ?? ['nodes' => [], 'edges' => [], 'critical_path' => []];
$nodeCount = count((array) ($graphData['nodes'] ?? []));
$edgeCount = count((array) ($graphData['edges'] ?? []));
$criticalPathLength = count((array) ($graphData['critical_path'] ?? []));
?>

<div class="portfolio-dependency-summary">
    <span class="portfolio-badge"><?= $this->text->e(t('Tasks')) ?>: <?= $this->text->e((string) $nodeCount) ?></span>
    <span class="portfolio-badge"><?= $this->text->e(t('Dependencies')) ?>: <?= $this->text->e((string) $edgeCount) ?></span>
    <span class="portfolio-badge"><?= $this->text->e(t('Critical Path Length')) ?>: <?= $this->text->e((string) $criticalPathLength) ?></span>
</div>

<?php if ($nodeCount === 0): ?>
    <p class="portfolio-empty-state"><?= $this->text->e(t('No dependencies found for this portfolio.')) ?></p>
<?php else: ?>
    <div
        id="portfolio-dependency-graph"
        class="portfolio-dependency-graph"
        data-graph="<?= htmlspecialchars((string) json_encode($graphData), ENT_QUOTES, 'UTF-8') ?>"
        data-graph-data-url="<?= $this->text->e($graph_data_url ?? '') ?>"
        aria-label="<?= $this->text->e(t('Dependency Graph')) ?>"
    ></div>
    <script src="<?= $this->text->e($this->url->href('PortfolioController', 'asset', ['file' => 'Asset/js/d3.v7.min.js', 'plugin' => 'Portfolio'])) ?>"></script>
    <script src="<?= $this->text->e($this->url->href('PortfolioController', 'asset', ['file' => 'Asset/js/portfolio-graph.js', 'plugin' => 'Portfolio'])) ?>"></script>
<?php endif ?>

<div class="portfolio-dependency-legend">
    <h3 class="portfolio-dependency-legend-title"><?= $this->text->e(t('Legend')) ?></h3>
    <ul class="portfolio-dependency-legend-list">
        <li><span class="portfolio-dependency-node portfolio-dependency-node--active"></span> <?= $this->text->e(t('Active Task')) ?></li>
        <li><span class="portfolio-dependency-node portfolio-dependency-node--closed"></span> <?= $this->text->e(t('Closed Task')) ?></li>
        <li><span class="portfolio-dependency-node portfolio-dependency-node--critical"></span> <?= $this->text->e(t('Critical Path Task')) ?></li>
        <li><span class="portfolio-dependency-edge portfolio-dependency-edge--blocks"></span> <?= $this->text->e(t('Blocks')) ?></li>
        <li><span class="portfolio-dependency-edge portfolio-dependency-edge--resolved"></span> <?= $this->text->e(t('Resolved')) ?></li>
    </ul>
</div>

    </div>
</section>
