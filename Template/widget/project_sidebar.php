<?php
/**
 * Project sidebar — portfolio membership for this project.
 *
 * @var array<string, mixed> $project
 */
$projectId = (int) ($project['id'] ?? 0);
$portfolios = ($projectId > 0) ? $this->portfolioHelper->getPortfoliosForProject($projectId) : [];
?>
<?php if (! empty($portfolios)): ?>
<div class="portfolio-widget-project-sidebar">
    <strong class="portfolio-widget-section-label"><?= $this->text->e(t('Portfolios')) ?></strong>
    <ul class="portfolio-widget-list">
        <?php foreach ($portfolios as $portfolio): ?>
        <li class="portfolio-widget-list-item">
            <a href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>">
                <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
            </a>
        </li>
        <?php endforeach ?>
    </ul>
</div>
<?php endif ?>
