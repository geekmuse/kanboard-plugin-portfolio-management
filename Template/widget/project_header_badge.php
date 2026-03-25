<?php
/**
 * Project header — portfolio membership badge.
 *
 * Variables pre-fetched by Plugin::attachCallable():
 *
 * @var array<int, array<string, mixed>> $portfolios  Portfolios that contain this project.
 *                                                    Empty when the project belongs to no portfolios.
 */
?>
<?php if (! empty($portfolios)): ?>
<div class="portfolio-widget-project-badge">
    <span class="portfolio-badge portfolio-widget-badge-label"><?= $this->text->e(t('Portfolios')) ?></span>
    <?php foreach ($portfolios as $key => $portfolio): ?>
    <?php if ($key > 0): ?><span class="portfolio-widget-badge-separator">,</span> <?php endif ?>
    <a
        href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>"
        class="portfolio-widget-badge-link"
    ><?= $this->text->e((string) ($portfolio['name'] ?? '')) ?></a>
    <?php endforeach ?>
</div>
<?php endif ?>
