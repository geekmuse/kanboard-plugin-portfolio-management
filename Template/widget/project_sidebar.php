<?php
/**
 * Project sidebar — portfolio membership for this project.
 *
 * Variables pre-fetched by Plugin::attachCallable():
 * @var array<int, array<string, mixed>> $portfolios
 */
?>
<?php if (! empty($portfolios)): ?>
<div class="portfolio-widget-project-sidebar">
    <strong class="portfolio-widget-section-label"><?= $this->text->e(t('Portfolios')) ?></strong>
    <ul class="portfolio-widget-list">
        <?php foreach ($portfolios as $portfolio): ?>
        <li class="portfolio-widget-list-item">
            <a href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
            </a>
        </li>
        <?php endforeach ?>
    </ul>
</div>
<?php endif ?>
